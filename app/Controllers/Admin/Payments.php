<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * The till. Two questions get asked here: what came in today, and who still
 * owes. The first is the drawer count at closing, the second is the list the
 * desk works through.
 *
 * Amounts are never taken from the form as gospel: `Payments::record()` clamps
 * every payment to the booking's own outstanding balance.
 */
class Payments extends BaseController
{
    private const METHODS  = ['cash', 'gcash', 'maya', 'card', 'bank_transfer', 'credits'];
    private const STATUSES = ['pending', 'paid', 'failed', 'refunded'];
    private const PROOF_TYPES = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    public function index(): string
    {
        $from   = $this->date((string) $this->request->getGet('from'), date('Y-m-d'));
        $to     = $this->date((string) $this->request->getGet('to'), $from);
        $method = (string) ($this->request->getGet('method') ?? '');
        $status = (string) ($this->request->getGet('status') ?? '');

        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        $ledger = $this->db->table('payments p')
            ->select('p.*, b.reference AS booking_reference, b.booking_date, b.guest_name,
                      courts.code AS court_code, u.first_name, u.last_name,
                      staff.first_name AS staff_first, staff.last_name AS staff_last')
            ->join('bookings b', 'b.id = p.booking_id', 'left')
            ->join('courts', 'courts.id = b.court_id', 'left')
            ->join('users u', 'u.id = p.user_id', 'left')
            ->join('users staff', 'staff.id = p.received_by', 'left')
            ->where('DATE(p.created_at) >=', $from)
            ->where('DATE(p.created_at) <=', $to);

        if (in_array($method, self::METHODS, true)) {
            $ledger->where('p.method', $method);
        }
        if (in_array($status, self::STATUSES, true)) {
            $ledger->where('p.status', $status);
        }

        $rows = $ledger->orderBy('p.created_at', 'DESC')->limit(200)->get()->getResultArray();

        // The drawer count only ever means settled money, so refunds and
        // failures are excluded from the mix rather than netted into it.
        $mix = $this->db->table('payments')
            ->select('method, COUNT(*) AS n, SUM(amount) AS total')
            ->where('DATE(created_at) >=', $from)
            ->where('DATE(created_at) <=', $to)
            ->where('status', 'paid')
            ->groupBy('method')->orderBy('total', 'DESC')->get()->getResultArray();

        $owing = $this->db->table('bookings b')
            ->select('b.id, b.reference, b.booking_date, b.start_time, b.total_amount, b.payment_status,
                      b.guest_name, courts.code AS court_code, u.first_name, u.last_name')
            ->join('courts', 'courts.id = b.court_id')
            ->join('users u', 'u.id = b.user_id', 'left')
            ->whereIn('b.payment_status', ['unpaid', 'partial'])
            ->whereNotIn('b.status', ['cancelled'])
            // Waiting for approval is the panel above, not a debt to chase.
            ->where("NOT (b.status = 'pending' AND b.gcash_reference IS NOT NULL)", null, false)
            ->where('b.booking_date <=', date('Y-m-d'))
            ->orderBy('b.booking_date', 'DESC')->orderBy('b.start_time', 'DESC')
            ->limit(40)->get()->getResultArray();

        // Oldest first, and no limit: a player is waiting on each of these. Only
        // the ones with a GCash reference: older pending rows have nothing to check.
        $review = $this->db->table('bookings b')
            ->select('b.id, b.reference, b.booking_date, b.start_time, b.total_amount, b.gcash_reference,
                      b.payment_proof, b.guest_name, b.guest_phone, courts.code AS court_code,
                      u.first_name, u.last_name, u.phone')
            ->join('courts', 'courts.id = b.court_id')
            ->join('users u', 'u.id = b.user_id', 'left')
            ->where('b.status', 'pending')
            ->where('b.gcash_reference IS NOT NULL', null, false)
            ->orderBy('b.created_at')->get()->getResultArray();

        $focusId = (int) ($this->request->getGet('booking') ?? 0);

        return $this->page('admin/payments', [
            'title'   => 'Payments | Go Paddle',
            'rows'    => $rows,
            'mix'     => $mix,
            'owing'   => $owing,
            'review'  => $review,
            'from'    => $from,
            'to'      => $to,
            'method'  => $method,
            'status'  => $status,
            'methods' => self::METHODS,
            'statuses' => self::STATUSES,
            'focus'   => $focusId > 0 ? $this->focusBooking($focusId) : null,
            'takings' => array_sum(array_map(static fn ($m) => (float) $m['total'], $mix)),
        ], 'layouts/admin');
    }

    public function record()
    {
        if (! $this->validate([
            'booking_id' => 'required|is_natural_no_zero',
            'method'     => 'required|in_list[' . implode(',', self::METHODS) . ']',
            'amount'     => 'permit_empty|numeric|greater_than[0]',
            'notes'      => 'permit_empty|max_length[255]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $amount = $this->request->getPost('amount');

        $result = service('payments')->record(
            (int) $this->request->getPost('booking_id'),
            (string) $this->request->getPost('method'),
            $amount === null || $amount === '' ? null : (float) $amount,
            (int) $this->auth->id(),
            trim((string) $this->request->getPost('notes')) ?: null
        );

        if (! $result['ok']) {
            return redirect()->back()->withInput()->with('error', $result['error']);
        }

        return redirect()->to('admin/payments')->with('success', sprintf(
            'Took %s. Receipt %s.',
            peso($result['amount']),
            $result['reference']
        ));
    }

    public function refund(int $id)
    {
        $reason = trim((string) $this->request->getPost('reason'));
        if ($reason === '') {
            return redirect()->back()->with('error', 'A refund needs a reason on the record.');
        }

        $result = service('payments')->refund($id, (int) $this->auth->id(), $reason);

        return redirect()->back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? 'Refunded and the booking is owing again.' : $result['error']
        );
    }

    public function approve(int $id)
    {
        $result = service('bookings')->approve($id, (int) $this->auth->id());

        return redirect()->to('admin/payments')->with(
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? 'Payment approved and the booking confirmed.' : $result['error']
        );
    }

    public function reject(int $id)
    {
        $reason = trim((string) $this->request->getPost('reason'));
        if ($reason === '') {
            return redirect()->to('admin/payments')->with('error', 'A rejection needs a reason on the record.');
        }

        $ok = service('bookings')->reject($id, $reason, (int) $this->auth->id());

        return redirect()->to('admin/payments')->with(
            $ok ? 'success' : 'error',
            $ok ? 'Booking rejected and the slot released.' : 'That booking is not waiting for approval.'
        );
    }

    /** Receipts hold names and numbers, so they leave writable/ only through here. */
    public function proof(int $id)
    {
        $booking = $this->db->table('bookings')->select('payment_proof')->where('id', $id)->get()->getRowArray();
        $name    = basename((string) ($booking['payment_proof'] ?? ''));
        $path    = WRITEPATH . 'uploads/payment-proofs/' . $name;

        if ($name === '' || ! is_file($path)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('No screenshot for that booking.');
        }

        return $this->response
            ->setHeader('Content-Type', self::PROOF_TYPES[pathinfo($name, PATHINFO_EXTENSION)] ?? 'application/octet-stream')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody((string) file_get_contents($path));
    }

    /** @return array<string, mixed>|null */
    private function focusBooking(int $id)
    {
        $booking = $this->db->table('bookings b')
            ->select('b.id, b.reference, b.total_amount, b.payment_status, b.guest_name, b.booking_date,
                      b.start_time, courts.code AS court_code, u.first_name, u.last_name')
            ->join('courts', 'courts.id = b.court_id')
            ->join('users u', 'u.id = b.user_id', 'left')
            ->where('b.id', $id)->get()->getRowArray();

        if ($booking !== null) {
            $booking['due'] = service('payments')->outstanding($id);
        }

        return $booking;
    }

    private function date(string $value, string $fallback): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false
            ? $value
            : $fallback;
    }
}
