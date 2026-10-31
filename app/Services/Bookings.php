<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;
use Throwable;

/**
 * Creating, cancelling and checking in reservations.
 *
 * The double-booking guard is the UNIQUE key on booking_slots(court_id,
 * slot_start). We still pre-check for a friendly error, but the insert is
 * what actually decides: two requests racing for the same 18:30 slot both
 * pass the pre-check, and exactly one survives the insert. The loser is
 * rolled back and told the slot went.
 */
class Bookings
{
    private BaseConnection $db;
    private Availability $availability;
    private Settings $settings;

    public function __construct()
    {
        $this->db           = Database::connect();
        $this->availability = service('availability');
        $this->settings     = service('siteSettings');
    }

    /**
     * @param array{
     *   court_id:int, date:string, start_time:string, duration:int,
     *   player_count?:int, player_names?:list<string>, user_id?:int|null, coach_id?:int|null,
     *   equipment?:array<int,int>, promo_code?:string|null,
     *   payment_method?:string, booking_type?:string, notes?:string|null,
     *   guest_name?:string|null, guest_phone?:string|null, guest_email?:string|null,
     *   created_by?:int|null, at_desk?:bool, awaiting_approval?:bool,
     *   gcash_reference?:string|null, payment_proof?:string|null
     * } $input
     *
     * @return array{ok:bool, reference?:string, id?:int, error?:string, field?:string}
     */
    public function create(array $input): array
    {
        try {
            $plan = $this->plan($input);
        } catch (RuntimeException $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'field' => $e->getCode() === 1 ? 'start_time' : 'court_id'];
        }

        // An online booking holds the court but is neither confirmed nor paid
        // until the desk has checked its GCash receipt. See approve().
        $awaiting = ! empty($input['awaiting_approval']);

        $this->db->transBegin();

        try {
            $reference = $this->reference($plan['date']);

            // Named players set the head count; the picker is the fallback.
            $names  = $this->playerNames($input['player_names'] ?? []);
            $people = $names === [] ? (int) ($input['player_count'] ?? 2) : count($names);

            $booking = [
                'reference'          => $reference,
                'user_id'            => $input['user_id'] ?? null,
                'court_id'           => $plan['court']['id'],
                'coach_id'           => $plan['coach']['id'] ?? null,
                'booking_date'       => $plan['date'],
                'start_time'         => $plan['start'] . ':00',
                'end_time'           => $plan['end'] . ':00',
                'duration_minutes'   => $plan['duration'],
                'player_count'       => max(1, min(8, $people)),
                'player_names'       => $names === [] ? null : implode('|', $names),
                'booking_type'       => $input['booking_type'] ?? ($plan['coach'] ? 'coaching' : 'online'),
                'status'             => $awaiting ? 'pending' : 'confirmed',
                'court_subtotal'     => $plan['court_subtotal'],
                'equipment_subtotal' => $plan['equipment_subtotal'],
                'coaching_subtotal'  => $plan['coaching_subtotal'],
                'discount_amount'    => $plan['discount'],
                'promo_code_id'      => $plan['promo']['id'] ?? null,
                'total_amount'       => $plan['total'],
                'payment_method'     => $input['payment_method'] ?? 'cash',
                'payment_status'     => $awaiting || ($input['payment_method'] ?? 'cash') === 'cash' ? 'unpaid' : 'paid',
                'gcash_reference'    => $input['gcash_reference'] ?? null,
                'payment_proof'      => $input['payment_proof'] ?? null,
                'guest_name'         => $input['guest_name'] ?? null,
                'guest_phone'        => $input['guest_phone'] ?? null,
                'guest_email'        => $input['guest_email'] ?? null,
                'notes'              => $input['notes'] ?? null,
                'qr_token'           => bin2hex(random_bytes(16)),
                'created_by'         => $input['created_by'] ?? null,
                'created_at'         => date('Y-m-d H:i:s'),
                'updated_at'         => date('Y-m-d H:i:s'),
            ];

            $this->db->table('bookings')->insert($booking);
            $bookingId = (int) $this->db->insertID();

            // The moment of truth.
            $slotRows = [];
            foreach ($plan['slots'] as $slotStart) {
                $slotRows[] = [
                    'booking_id' => $bookingId,
                    'court_id'   => $plan['court']['id'],
                    'slot_start' => $slotStart,
                ];
            }

            $inserted = $this->db->table('booking_slots')->insertBatch($slotRows);
            if ($inserted === false || (int) $inserted !== count($slotRows)) {
                throw new RuntimeException('slot_conflict');
            }

            foreach ($plan['equipment'] as $e) {
                $this->db->table('booking_equipment')->insert([
                    'booking_id'   => $bookingId,
                    'equipment_id' => $e['id'],
                    'qty'          => $e['qty'],
                    'rate'         => $e['rate'],
                    'hours'        => $plan['duration'] / 60,
                    'subtotal'     => $e['subtotal'],
                ]);
            }

            if ($plan['coach'] !== null) {
                $this->db->table('coaching_sessions')->insert([
                    'booking_id'   => $bookingId,
                    'coach_id'     => $plan['coach']['id'],
                    'user_id'      => $input['user_id'] ?? null,
                    'session_type' => ($input['player_count'] ?? 2) > 2 ? 'group' : 'private',
                    'session_date' => $plan['date'],
                    'start_time'   => $plan['start'] . ':00',
                    'end_time'     => $plan['end'] . ':00',
                    'participants' => max(1, (int) ($input['player_count'] ?? 1)),
                    'rate'         => $plan['coach']['hourly_rate'],
                    'status'       => $awaiting ? 'pending' : 'confirmed',
                    'created_at'   => date('Y-m-d H:i:s'),
                    'updated_at'   => date('Y-m-d H:i:s'),
                ]);
            }

            if ($plan['promo'] !== null) {
                $this->db->table('promo_codes')
                    ->where('id', $plan['promo']['id'])
                    ->set('used_count', 'used_count + 1', false)
                    ->update();
            }

            if ($booking['payment_status'] === 'paid') {
                $this->db->table('payments')->insert([
                    'reference'  => 'PY-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
                    'booking_id' => $bookingId,
                    'user_id'    => $input['user_id'] ?? null,
                    'purpose'    => 'booking',
                    'method'     => $booking['payment_method'],
                    'amount'     => $plan['total'],
                    'status'     => 'paid',
                    'paid_at'    => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('slot_conflict');
            }

            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();

            $duplicate = $e->getMessage() === 'slot_conflict'
                || str_contains($e->getMessage(), 'Duplicate entry')
                || str_contains($e->getMessage(), '1062');

            return [
                'ok'    => false,
                'field' => 'start_time',
                'error' => $duplicate
                    ? 'That time was taken while you were booking. Pick another slot. The board below is up to date.'
                    : 'We could not complete the booking. Please try again.',
            ];
        }

        service('audit')->log('booking.created', 'booking', $bookingId, sprintf(
            '%s · %s %s-%s · %s',
            $plan['court']['code'],
            $plan['date'],
            $plan['start'],
            $plan['end'],
            peso($plan['total'])
        ));

        service('bernieNotify')->push(
            $input['user_id'] ?? null,
            'booking',
            ($awaiting ? 'Waiting for approval: ' : 'Booking confirmed: ') . $plan['court']['name'],
            sprintf('%s, %s-%s. Reference %s.', day_label($plan['date']), $plan['start'], $plan['end'], $reference),
            '/account/bookings'
        );

        return ['ok' => true, 'reference' => $reference, 'id' => $bookingId];
    }

    /**
     * Validate and price a request without writing anything. Used by create()
     * and by the live summary panel in the booking form.
     *
     * @return array<string, mixed>
     */
    public function plan(array $input): array
    {
        $step     = $this->availability->slotMinutes();
        $date     = $input['date'] ?? '';
        $start    = substr((string) ($input['start_time'] ?? ''), 0, 5);
        $duration = (int) ($input['duration'] ?? $this->settings->int('booking.min_minutes', 60));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
            throw new RuntimeException('Pick a date.', 0);
        }
        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $start)) {
            throw new RuntimeException('Pick a play time.', 1);
        }

        $minDuration = $this->settings->int('booking.min_minutes', 60);
        $maxDuration = $this->settings->int('booking.max_minutes', 180);
        if ($duration < $minDuration || $duration > $maxDuration || $duration % $step !== 0) {
            throw new RuntimeException(sprintf('Sessions run from %d to %d minutes, in %d-minute steps.', $minDuration, $maxDuration, $step), 1);
        }

        $advance = $this->settings->int('booking.advance_days', 14);
        if ($date > date('Y-m-d', strtotime("+{$advance} days"))) {
            throw new RuntimeException("Bookings open {$advance} days ahead.", 0);
        }

        // Real time, not `$date $start`: a 01:00 start in the session that opens
        // on $date is 01:00 the next morning.
        $startTs = $this->availability->slotTimestamp($date, $start);
        $cutoff  = $this->settings->int('booking.cutoff_minutes', 30);

        // The cutoff is an *online* policy: it stops someone booking 18:30 from
        // their phone at 18:20 and arriving to a court the desk has already
        // given away. It is the wrong rule for a walk-in, where the player is
        // standing at the counter. Slots that have already started are still
        // refused, because the board reads them PAST rather than OPEN below.
        if (empty($input['at_desk']) && $startTs < time() + $cutoff * 60) {
            throw new RuntimeException("Online booking closes {$cutoff} minutes before start. Call the desk for anything sooner.", 1);
        }

        $court = $this->db->table('courts')->where('id', (int) ($input['court_id'] ?? 0))->where('deleted_at', null)->get()->getRowArray();
        if ($court === null) {
            throw new RuntimeException('Pick a court.', 0);
        }
        if ($court['status'] !== 'active') {
            throw new RuntimeException($court['name'] . ' is closed right now.', 0);
        }

        // Every slot this booking would occupy must currently read OPEN. The run
        // carries on into the next day, so a booking may cross the day boundary
        // as well as midnight — the rate changes there, nothing else does.
        $needed = intdiv($duration, $step);
        $run    = $this->availability->cellRun($date, (int) $court['id'], $needed - 1);
        $from   = null;
        foreach ($run as $i => $cell) {
            if ($cell['time'] === $start && $cell['date'] === $date) {
                $from = $i;
                break;
            }
        }

        $slots      = [];
        $courtTotal = 0.0;
        for ($i = 0; $i < $needed; $i++) {
            $cell = $from === null ? null : ($run[$from + $i] ?? null);

            if ($cell === null || $cell['state'] !== Availability::OPEN) {
                throw new RuntimeException(
                    sprintf('%s is not free for the full %d minutes from %s.', $court['name'], $duration, $start),
                    1
                );
            }

            $slots[]    = date('Y-m-d H:i:s', $this->availability->slotTimestamp($cell['date'], $cell['time']));
            $courtTotal += $cell['rate'] * ($step / 60);
        }

        // ------------------------------------------------------------- coaching
        $coach = null;
        $coachTotal = 0.0;
        if (! empty($input['coach_id'])) {
            $coach = $this->db->table('coaches')
                ->select('coaches.id, coaches.hourly_rate, coaches.group_rate, users.first_name, users.last_name')
                ->join('users', 'users.id = coaches.user_id')
                ->where('coaches.id', (int) $input['coach_id'])
                ->where('coaches.is_accepting', 1)
                ->where('coaches.deleted_at', null)
                ->get()->getRowArray();

            if ($coach === null) {
                throw new RuntimeException('That coach is not taking sessions.', 0);
            }
            if ($this->coachBusy((int) $coach['id'], $date, $start, $duration)) {
                throw new RuntimeException(sprintf('Coach %s is already booked at %s.', $coach['first_name'], $start), 1);
            }

            $coachTotal = round((float) $coach['hourly_rate'] * ($duration / 60), 2);
        }

        // ------------------------------------------------------------ equipment
        $equipment  = [];
        $equipTotal = 0.0;
        foreach ($input['equipment'] ?? [] as $equipmentId => $qty) {
            $qty = (int) $qty;
            if ($qty < 1) {
                continue;
            }

            $item = $this->db->table('equipment')->where('id', (int) $equipmentId)->where('is_active', 1)->get()->getRowArray();
            if ($item === null) {
                continue;
            }
            if ($qty > (int) $item['available_qty']) {
                throw new RuntimeException(sprintf('Only %d × %s available.', $item['available_qty'], $item['name']), 0);
            }

            $sub = round((float) $item['rental_rate_hourly'] * $qty * ($duration / 60), 2);
            $equipment[] = ['id' => (int) $item['id'], 'name' => $item['name'], 'qty' => $qty, 'rate' => (float) $item['rental_rate_hourly'], 'subtotal' => $sub];
            $equipTotal += $sub;
        }

        // ---------------------------------------------------------------- promo
        $subtotal = round($courtTotal + $coachTotal + $equipTotal, 2);
        $promo    = null;
        $discount = 0.0;

        if (! empty($input['promo_code'])) {
            $promo = $this->validPromo((string) $input['promo_code'], $subtotal);
            if ($promo === null) {
                throw new RuntimeException('That promo code is not valid for this booking.', 0);
            }
            $discount = $promo['discount_type'] === 'percent'
                ? round($subtotal * ((float) $promo['discount_value'] / 100), 2)
                : min($subtotal, (float) $promo['discount_value']);
        }

        $endMin = ($this->toMinutes($start) + $duration) % 1440;

        return [
            'court'              => $court,
            'coach'              => $coach,
            'equipment'          => $equipment,
            'promo'              => $promo,
            'date'               => $date,
            'start'              => $start,
            'end'                => sprintf('%02d:%02d', intdiv($endMin, 60), $endMin % 60),
            'duration'           => $duration,
            'slots'              => $slots,
            'court_subtotal'     => round($courtTotal, 2),
            'coaching_subtotal'  => $coachTotal,
            'equipment_subtotal' => $equipTotal,
            'subtotal'           => $subtotal,
            'discount'           => $discount,
            'total'              => round($subtotal - $discount, 2),
        ];
    }

    public function cancel(int $bookingId, string $reason, ?int $actorId = null): bool
    {
        $booking = $this->db->table('bookings')->where('id', $bookingId)->get()->getRowArray();
        if ($booking === null || in_array($booking['status'], ['cancelled', 'completed'], true)) {
            return false;
        }

        $this->db->transBegin();
        try {
            // Removing the slot rows is what actually frees the court.
            $this->db->table('booking_slots')->where('booking_id', $bookingId)->delete();
            $this->db->table('bookings')->where('id', $bookingId)->update([
                'status'              => 'cancelled',
                'cancelled_at'        => date('Y-m-d H:i:s'),
                'cancellation_reason' => mb_substr($reason, 0, 255),
                'payment_status'      => $booking['payment_status'] === 'paid' ? 'refunded' : 'unpaid',
                'updated_at'          => date('Y-m-d H:i:s'),
            ]);
            $this->db->table('coaching_sessions')->where('booking_id', $bookingId)->update(['status' => 'cancelled']);
            $this->db->transCommit();
        } catch (Throwable) {
            $this->db->transRollback();

            return false;
        }

        service('audit')->log('booking.cancelled', 'booking', $bookingId, $booking['reference'] . ' — ' . $reason, $actorId);

        return true;
    }

    /**
     * The desk has seen the money arrive: take the payment and confirm the
     * booking. The amount is the booking's own balance, never a form's.
     *
     * @return array{ok:bool, error?:string}
     */
    public function approve(int $bookingId, int $actorId): array
    {
        $booking = $this->awaitingApproval($bookingId);
        if ($booking === null) {
            return ['ok' => false, 'error' => 'That booking is not waiting for approval.'];
        }

        // A promo can take the total to nothing, and record() refuses that.
        $payments = service('payments');
        if ($payments->outstanding($bookingId) > 0.0) {
            $paid = $payments->record($bookingId, 'gcash', null, $actorId, 'GCash ref ' . $booking['gcash_reference']);
            if (! $paid['ok']) {
                return ['ok' => false, 'error' => $paid['error']];
            }
        }

        $this->db->table('bookings')->where('id', $bookingId)->update([
            'status'         => 'confirmed',
            'payment_status' => 'paid',
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('coaching_sessions')->where('booking_id', $bookingId)->update(['status' => 'confirmed']);

        service('audit')->log('booking.approved', 'booking', $bookingId, $booking['reference'] . ' GCash ref ' . $booking['gcash_reference'], $actorId);

        service('bernieNotify')->push(
            $booking['user_id'] === null ? null : (int) $booking['user_id'],
            'booking',
            'Booking confirmed: ' . $booking['reference'],
            sprintf('%s at %s. Your GCash payment was approved.', day_label($booking['booking_date']), clock($booking['start_time'])),
            '/account/bookings'
        );

        return ['ok' => true];
    }

    /** The payment did not check out, so the booking goes and the court comes back. */
    public function reject(int $bookingId, string $reason, int $actorId): bool
    {
        $booking = $this->awaitingApproval($bookingId);
        if ($booking === null) {
            return false;
        }

        if (! $this->cancel($bookingId, 'Payment not approved: ' . $reason, $actorId)) {
            return false;
        }

        service('bernieNotify')->push(
            $booking['user_id'] === null ? null : (int) $booking['user_id'],
            'booking',
            'Booking not approved: ' . $booking['reference'],
            $reason,
            '/account/bookings'
        );

        return true;
    }

    /**
     * Pending with a reference: older pending rows have no receipt to check.
     *
     * @return array<string, mixed>|null
     */
    private function awaitingApproval(int $bookingId): ?array
    {
        return $this->db->table('bookings')
            ->where('id', $bookingId)
            ->where('status', 'pending')
            ->where('gcash_reference IS NOT NULL', null, false)
            ->get()->getRowArray();
    }

    public function checkIn(string $qrToken): ?array
    {
        $booking = $this->db->table('bookings')->where('qr_token', $qrToken)->get()->getRowArray();
        if ($booking === null || $booking['status'] !== 'confirmed') {
            return null;
        }

        $this->db->table('bookings')->where('id', $booking['id'])->update([
            'status'        => 'checked_in',
            'checked_in_at' => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        service('audit')->log('booking.checked_in', 'booking', (int) $booking['id'], $booking['reference']);

        return $booking;
    }

    private function coachBusy(int $coachId, string $date, string $start, int $duration): bool
    {
        // Offsets from opening time, so a session crossing midnight still orders.
        $startTs = $this->availability->offset($start);
        $endTs   = $startTs + $duration;

        $rows = $this->db->table('bookings')
            ->select('start_time, end_time')
            ->where('coach_id', $coachId)
            ->where('booking_date', $date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->get()->getResultArray();

        foreach ($rows as $r) {
            $rStart = $this->availability->offset(substr($r['start_time'], 0, 5));
            if ($startTs < $rStart + $this->span($r['start_time'], $r['end_time']) && $endTs > $rStart) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed>|null */
    public function validPromo(string $code, float $subtotal): ?array
    {
        $promo = $this->db->table('promo_codes')
            ->where('code', strtoupper(trim($code)))
            ->where('is_active', 1)
            ->get()->getRowArray();

        if ($promo === null) {
            return null;
        }
        $today = date('Y-m-d');
        if ($promo['starts_on'] !== null && $promo['starts_on'] > $today) {
            return null;
        }
        if ($promo['ends_on'] !== null && $promo['ends_on'] < $today) {
            return null;
        }
        if ((int) $promo['max_uses'] > 0 && (int) $promo['used_count'] >= (int) $promo['max_uses']) {
            return null;
        }
        if ($subtotal < (float) $promo['min_amount']) {
            return null;
        }

        return $promo;
    }

    private function reference(string $date): string
    {
        for ($i = 0; $i < 5; $i++) {
            $ref = 'BP-' . date('ymd', strtotime($date)) . '-' . strtoupper(bin2hex(random_bytes(2)));
            if ($this->db->table('bookings')->where('reference', $ref)->countAllResults() === 0) {
                return $ref;
            }
        }

        throw new RuntimeException('Could not allocate a booking reference.');
    }

    /** Minutes from one clock time forward to another, wrapping midnight. */
    private function span(string $from, string $to): int
    {
        return (($this->toMinutes($to) - $this->toMinutes($from)) % 1440 + 1440) % 1440;
    }

    /**
     * Trimmed, de-duplicated, at most eight. Empty rows from an "add another"
     * field are dropped rather than stored as blank players.
     *
     * @param  mixed $input
     * @return list<string>
     */
    private function playerNames(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $names = [];
        foreach ($input as $name) {
            $name = trim(preg_replace('/\s+/', ' ', (string) $name));
            // A pipe would split one player into two when the column is read.
            $name = str_replace('|', '/', $name);
            if ($name !== '' && ! in_array($name, $names, true)) {
                $names[] = mb_substr($name, 0, 120);
            }
        }

        return array_slice($names, 0, 8);
    }

    private function toMinutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return ((int) $h) * 60 + (int) $m;
    }
}
