<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Taking and reversing money against a booking.
 *
 * Two rules. The amount is never trusted from a form: it is the booking's own
 * outstanding balance unless a smaller part payment is explicitly passed, so a
 * crafted post cannot mark a 900 peso session settled with 1 peso. And the
 * payments row and the booking's `payment_status` always move together inside
 * one transaction, because a ledger that disagrees with the booking is worse
 * than no ledger.
 */
class Payments
{
    private BaseConnection $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /** What is still owed on a booking, after everything already settled. */
    public function outstanding(int $bookingId): float
    {
        $booking = $this->db->table('bookings')->select('total_amount')->where('id', $bookingId)->get()->getRowArray();
        if ($booking === null) {
            return 0.0;
        }

        $taken = (float) ($this->db->table('payments')
            ->selectSum('amount', 'paid')
            ->where('booking_id', $bookingId)
            ->where('status', 'paid')
            ->get()->getRow('paid') ?? 0);

        return max(0.0, round((float) $booking['total_amount'] - $taken, 2));
    }

    /**
     * @return array{ok:bool, error?:string, reference?:string, amount?:float}
     */
    public function record(int $bookingId, string $method, ?float $amount, int $receivedBy, ?string $notes = null): array
    {
        $booking = $this->db->table('bookings')->where('id', $bookingId)->get()->getRowArray();
        if ($booking === null) {
            return ['ok' => false, 'error' => 'No such booking.'];
        }
        if ($booking['status'] === 'cancelled') {
            return ['ok' => false, 'error' => 'That booking is cancelled. Nothing to collect.'];
        }
        if (! in_array($method, ['cash', 'gcash', 'maya', 'card', 'bank_transfer', 'credits'], true)) {
            return ['ok' => false, 'error' => 'That is not a payment method we take.'];
        }

        $due = $this->outstanding($bookingId);
        if ($due <= 0.0) {
            return ['ok' => false, 'error' => $booking['reference'] . ' is already settled.'];
        }

        // A part payment is allowed, an overpayment is not.
        $take = $amount === null ? $due : round(min($amount, $due), 2);
        if ($take <= 0.0) {
            return ['ok' => false, 'error' => 'The amount has to be more than zero.'];
        }

        $this->db->transBegin();

        try {
            $reference = 'PY-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            $this->db->table('payments')->insert([
                'reference'   => $reference,
                'booking_id'  => $bookingId,
                'user_id'     => $booking['user_id'],
                'purpose'     => 'booking',
                'method'      => $method,
                'amount'      => $take,
                'status'      => 'paid',
                'paid_at'     => date('Y-m-d H:i:s'),
                'received_by' => $receivedBy,
                'notes'       => $notes,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);

            $this->db->table('bookings')->where('id', $bookingId)->update([
                'payment_status' => $take >= $due ? 'paid' : 'partial',
                'payment_method' => $method,
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('payment_write_failed');
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Payment write failed for booking {id}: {msg}', ['id' => $bookingId, 'msg' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'That payment did not save. Nothing was recorded, try again.'];
        }

        service('audit')->log(
            'payment.recorded',
            'booking',
            $bookingId,
            sprintf('%s %s via %s', $booking['reference'], peso($take), $method),
            $receivedBy
        );

        return ['ok' => true, 'reference' => $reference, 'amount' => $take];
    }

    /**
     * A membership is a single price on a plan, not a running balance, so it
     * gets its own path rather than bending `record()` into taking either.
     *
     * @return array{ok:bool, error?:string, reference?:string, amount?:float}
     */
    public function recordMembership(int $membershipId, string $method, int $receivedBy): array
    {
        $row = $this->db->table('memberships m')
            ->select('m.id, m.user_id, m.reference, m.status, mp.name AS plan_name, mp.price')
            ->join('membership_plans mp', 'mp.id = m.plan_id')
            ->where('m.id', $membershipId)->get()->getRowArray();

        if ($row === null) {
            return ['ok' => false, 'error' => 'No such membership.'];
        }
        if (! in_array($method, ['cash', 'gcash', 'maya', 'card', 'bank_transfer'], true)) {
            return ['ok' => false, 'error' => 'That is not a payment method we take.'];
        }

        $already = $this->db->table('payments')
            ->where('membership_id', $membershipId)->where('status', 'paid')->countAllResults();
        if ($already > 0) {
            return ['ok' => false, 'error' => $row['reference'] . ' is already paid for.'];
        }

        $reference = 'PY-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $this->db->table('payments')->insert([
            'reference'     => $reference,
            'membership_id' => $membershipId,
            'user_id'       => $row['user_id'],
            'purpose'       => 'membership',
            'method'        => $method,
            'amount'        => (float) $row['price'],
            'status'        => 'paid',
            'paid_at'       => date('Y-m-d H:i:s'),
            'received_by'   => $receivedBy,
            'notes'         => $row['plan_name'] . ' membership',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        service('audit')->log('payment.recorded', 'membership', $membershipId, sprintf(
            '%s %s via %s',
            $row['reference'],
            peso((float) $row['price']),
            $method
        ), $receivedBy);

        return ['ok' => true, 'reference' => $reference, 'amount' => (float) $row['price']];
    }

    /** @return array{ok:bool, error?:string} */
    public function refund(int $paymentId, int $actorId, string $reason): array
    {
        $payment = $this->db->table('payments')->where('id', $paymentId)->get()->getRowArray();
        if ($payment === null) {
            return ['ok' => false, 'error' => 'No such payment.'];
        }
        if ($payment['status'] !== 'paid') {
            return ['ok' => false, 'error' => 'Only a settled payment can be refunded.'];
        }

        $this->db->transBegin();

        try {
            $this->db->table('payments')->where('id', $paymentId)->update([
                'status'     => 'refunded',
                'notes'      => trim(($payment['notes'] ? $payment['notes'] . ' ' : '') . 'Refunded: ' . $reason),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // The booking goes back to owing whatever the refund took off it.
            if ($payment['booking_id'] !== null) {
                $stillDue = $this->outstanding((int) $payment['booking_id']);
                $booking  = $this->db->table('bookings')->select('total_amount')->where('id', $payment['booking_id'])->get()->getRowArray();
                $total    = (float) ($booking['total_amount'] ?? 0);

                $this->db->table('bookings')->where('id', $payment['booking_id'])->update([
                    'payment_status' => match (true) {
                        $stillDue <= 0.0      => 'paid',
                        $stillDue >= $total   => 'refunded',
                        default               => 'partial',
                    },
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('refund_write_failed');
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Refund failed for payment {id}: {msg}', ['id' => $paymentId, 'msg' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'That refund did not save. Nothing was changed.'];
        }

        service('audit')->log('payment.refunded', 'payment', $paymentId, $payment['reference'] . ' ' . $reason, $actorId);

        return ['ok' => true];
    }
}
