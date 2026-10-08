<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;
use Throwable;

/**
 * Open play: a court opened for a few hours, with a per-head fee and a sign-up
 * sheet.
 *
 * The court is held by an ordinary `block` booking this session owns, so the
 * UNIQUE key on booking_slots is the guard — a normal booking cannot land on
 * an open-play hour and open play cannot be scheduled over a booking. Opening
 * and closing a session is therefore creating and deleting that booking; the
 * slots cascade with it.
 *
 * The block carries no money. Open play is paid per participant, and the
 * participant rows are the record of that.
 */
class OpenPlay
{
    private BaseConnection $db;
    private Availability $availability;

    public function __construct()
    {
        $this->db           = Database::connect();
        $this->availability = service('availability');
    }

    /**
     * Schedule a session and hold the court for it.
     *
     * @param array{court_id:int, date:string, start_time:string, duration:int,
     *              fee:float, capacity:int, title?:string|null, notes?:string|null,
     *              created_by?:int|null} $input
     *
     * @return array{ok:bool, id?:int, error?:string}
     */
    public function open(array $input): array
    {
        $courtId  = (int) ($input['court_id'] ?? 0);
        $date     = (string) ($input['date'] ?? '');
        $start    = substr((string) ($input['start_time'] ?? ''), 0, 5);
        $duration = (int) ($input['duration'] ?? 0);
        $step     = $this->availability->slotMinutes();

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['ok' => false, 'error' => 'Pick a date.'];
        }
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start)) {
            return ['ok' => false, 'error' => 'Pick a start time.'];
        }
        if ($duration < $step || $duration % $step !== 0) {
            return ['ok' => false, 'error' => sprintf('Length has to be whole %d-minute slots.', $step)];
        }

        $court = $this->db->table('courts')->where('id', $courtId)->where('deleted_at', null)->get()->getRowArray();
        if ($court === null) {
            return ['ok' => false, 'error' => 'Pick a court.'];
        }

        // Every hour it would take has to be free right now. The insert below
        // is what actually decides, the same way a booking works.
        $needed = intdiv($duration, $step);
        $run    = $this->availability->cellRun($date, $courtId, $needed - 1);
        $from   = null;
        foreach ($run as $i => $cell) {
            if ($cell['date'] === $date && $cell['time'] === $start) {
                $from = $i;
                break;
            }
        }

        $slots = [];
        for ($i = 0; $i < $needed; $i++) {
            $cell = $from === null ? null : ($run[$from + $i] ?? null);
            if ($cell === null || $cell['state'] !== Availability::OPEN) {
                return ['ok' => false, 'error' => sprintf(
                    '%s is not free for the whole %s from %s.',
                    $court['name'],
                    length_label($duration, true),
                    clock($start)
                )];
            }
            $slots[] = date('Y-m-d H:i:s', $this->availability->slotTimestamp($cell['date'], $cell['time']));
        }

        $endMinutes = ($this->minutes($start) + $duration) % 1440;
        $now        = date('Y-m-d H:i:s');

        $this->db->transBegin();

        try {
            $this->db->table('bookings')->insert([
                'reference'        => 'OP-' . date('ymd', strtotime($date)) . '-' . strtoupper(bin2hex(random_bytes(2))),
                'court_id'         => $courtId,
                'booking_date'     => $date,
                'start_time'       => $start . ':00',
                'end_time'         => sprintf('%02d:%02d:00', intdiv($endMinutes, 60), $endMinutes % 60),
                'duration_minutes' => $duration,
                'player_count'     => 1,
                'booking_type'     => 'block',
                'status'           => 'confirmed',
                'court_subtotal'   => 0,
                'total_amount'     => 0,
                'payment_method'   => 'cash',
                'payment_status'   => 'paid',
                'guest_name'       => $input['title'] ?? 'Open play',
                'notes'            => 'Open play session.',
                'qr_token'         => bin2hex(random_bytes(16)),
                'created_by'       => $input['created_by'] ?? null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
            $bookingId = (int) $this->db->insertID();

            $rows = array_map(static fn (string $slot): array => [
                'booking_id' => $bookingId,
                'court_id'   => $courtId,
                'slot_start' => $slot,
            ], $slots);

            $inserted = $this->db->table('booking_slots')->insertBatch($rows);
            if ($inserted === false || (int) $inserted !== count($rows)) {
                throw new RuntimeException('slot_conflict');
            }

            $this->db->table('open_play_sessions')->insert([
                'court_id'         => $courtId,
                'booking_id'       => $bookingId,
                'session_date'     => $date,
                'start_time'       => $start . ':00',
                'end_time'         => sprintf('%02d:%02d:00', intdiv($endMinutes, 60), $endMinutes % 60),
                'duration_minutes' => $duration,
                'fee'              => max(0, (float) ($input['fee'] ?? 0)),
                'capacity'         => max(1, min(255, (int) ($input['capacity'] ?? 12))),
                'status'           => 'open',
                'title'            => $input['title'] ?? null,
                'notes'            => $input['notes'] ?? null,
                'created_by'       => $input['created_by'] ?? null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
            $sessionId = (int) $this->db->insertID();

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('slot_conflict');
            }

            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();

            $taken = $e->getMessage() === 'slot_conflict'
                || str_contains($e->getMessage(), 'Duplicate entry')
                || str_contains($e->getMessage(), '1062');

            return ['ok' => false, 'error' => $taken
                ? 'Those hours went while you were setting this up. The board below is up to date.'
                : 'Could not open that session. Nothing was saved.'];
        }

        service('audit')->log('openplay.opened', 'open_play_session', $sessionId, sprintf(
            '%s · %s %s for %s · %s a head, %d places',
            $court['code'],
            $date,
            clock($start),
            length_label($duration, true),
            peso((float) ($input['fee'] ?? 0)),
            (int) ($input['capacity'] ?? 12)
        ), $input['created_by'] ?? null);

        return ['ok' => true, 'id' => $sessionId];
    }

    /**
     * Switch a session off: the court is released, the sign-ups are kept so
     * the desk can still see who had registered and who had paid.
     */
    public function close(int $sessionId, ?int $actorId = null): bool
    {
        $session = $this->find($sessionId);
        if ($session === null || $session['status'] === 'closed') {
            return false;
        }

        if ($session['booking_id'] !== null) {
            // The slots cascade with it, which is what frees the court.
            $this->db->table('bookings')->where('id', $session['booking_id'])->delete();
        }

        $this->db->table('open_play_sessions')->where('id', $sessionId)->update([
            'status'     => 'closed',
            'booking_id' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        service('audit')->log('openplay.closed', 'open_play_session', $sessionId, sprintf(
            '%s %s released',
            $session['session_date'],
            clock($session['start_time'])
        ), $actorId);

        return true;
    }

    /** Switch it back on, if those hours are still free. */
    public function reopen(int $sessionId, ?int $actorId = null): array
    {
        $session = $this->find($sessionId);
        if ($session === null) {
            return ['ok' => false, 'error' => 'That session is gone.'];
        }
        if ($session['status'] === 'open') {
            return ['ok' => true, 'id' => $sessionId];
        }

        $result = $this->open([
            'court_id'   => (int) $session['court_id'],
            'date'       => $session['session_date'],
            'start_time' => substr($session['start_time'], 0, 5),
            'duration'   => (int) $session['duration_minutes'],
            'fee'        => (float) $session['fee'],
            'capacity'   => (int) $session['capacity'],
            'title'      => $session['title'],
            'notes'      => $session['notes'],
            'created_by' => $actorId,
        ]);

        if (! $result['ok']) {
            return $result;
        }

        // The reopened row is the one that holds the court; move the sign-ups
        // onto it and drop the shell, so a session is never listed twice.
        $this->db->table('open_play_participants')
            ->where('session_id', $sessionId)
            ->update(['session_id' => $result['id'], 'updated_at' => date('Y-m-d H:i:s')]);
        $this->db->table('open_play_sessions')->where('id', $sessionId)->delete();

        return $result;
    }

    /**
     * Sign somebody up.
     *
     * @param array{name:string, phone?:string|null, user_id?:int|null,
     *              payment_method?:string, paid?:bool, created_by?:int|null} $player
     *
     * @return array{ok:bool, id?:int, error?:string}
     */
    public function register(int $sessionId, array $player): array
    {
        $session = $this->find($sessionId);
        if ($session === null || $session['status'] !== 'open') {
            return ['ok' => false, 'error' => 'That session is not open.'];
        }

        $name = trim(preg_replace('/\s+/', ' ', (string) ($player['name'] ?? '')));
        if ($name === '') {
            return ['ok' => false, 'error' => 'A name is needed.'];
        }

        if ($this->placesLeft($sessionId) < 1) {
            return ['ok' => false, 'error' => 'That session is full.'];
        }

        $method = ($player['payment_method'] ?? 'cash') === 'gcash' ? 'gcash' : 'cash';
        // GCash is treated as settled the moment it is taken, the same way a
        // GCash booking is; cash is only settled when the desk says so.
        $paid = $method === 'gcash' || ! empty($player['paid']);
        $now  = date('Y-m-d H:i:s');

        $this->db->table('open_play_participants')->insert([
            'session_id'     => $sessionId,
            'user_id'        => $player['user_id'] ?? null,
            'name'           => mb_substr($name, 0, 120),
            'phone'          => ($player['phone'] ?? '') !== '' ? mb_substr(trim((string) $player['phone']), 0, 30) : null,
            'amount'         => (float) $session['fee'],
            'payment_method' => $method,
            'payment_status' => $paid ? 'paid' : 'unpaid',
            'created_by'     => $player['created_by'] ?? null,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
        $id = (int) $this->db->insertID();

        service('audit')->log('openplay.registered', 'open_play_session', $sessionId, sprintf(
            '%s signed up for %s %s',
            $name,
            $session['session_date'],
            clock($session['start_time'])
        ), $player['created_by'] ?? null);

        return ['ok' => true, 'id' => $id];
    }

    public function removeParticipant(int $participantId, ?int $actorId = null): bool
    {
        $row = $this->db->table('open_play_participants')->where('id', $participantId)->get()->getRowArray();
        if ($row === null) {
            return false;
        }

        $this->db->table('open_play_participants')->where('id', $participantId)->delete();
        service('audit')->log('openplay.withdrawn', 'open_play_session', (int) $row['session_id'], $row['name'] . ' withdrawn', $actorId);

        return true;
    }

    public function markPaid(int $participantId, ?int $actorId = null): bool
    {
        $row = $this->db->table('open_play_participants')->where('id', $participantId)->get()->getRowArray();
        if ($row === null || $row['payment_status'] === 'paid') {
            return false;
        }

        $this->db->table('open_play_participants')->where('id', $participantId)->update([
            'payment_status' => 'paid',
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
        service('audit')->log('openplay.paid', 'open_play_session', (int) $row['session_id'], $row['name'] . ' paid ' . peso($row['amount']), $actorId);

        return true;
    }

    public function placesLeft(int $sessionId): int
    {
        $session = $this->find($sessionId);
        if ($session === null) {
            return 0;
        }

        $taken = $this->db->table('open_play_participants')->where('session_id', $sessionId)->countAllResults();

        return max(0, (int) $session['capacity'] - $taken);
    }

    /** @return array<string, mixed>|null */
    public function find(int $sessionId): ?array
    {
        return $this->db->table('open_play_sessions')->where('id', $sessionId)->get()->getRowArray();
    }

    /**
     * Sessions from $from onwards, newest day first or soonest first.
     *
     * @return list<array<string, mixed>>
     */
    public function listing(?string $from = null, bool $openOnly = false): array
    {
        $query = $this->db->table('open_play_sessions s')
            ->select('s.*, courts.code AS court_code, courts.name AS court_name,
                      (SELECT COUNT(*) FROM open_play_participants p WHERE p.session_id = s.id) AS taken,
                      (SELECT COUNT(*) FROM open_play_participants p WHERE p.session_id = s.id AND p.payment_status = "paid") AS paid_count,
                      (SELECT COALESCE(SUM(p.amount), 0) FROM open_play_participants p WHERE p.session_id = s.id AND p.payment_status = "paid") AS taken_amount')
            ->join('courts', 'courts.id = s.court_id')
            ->orderBy('s.session_date')
            ->orderBy('s.start_time');

        if ($from !== null) {
            $query->where('s.session_date >=', $from);
        }
        if ($openOnly) {
            $query->where('s.status', 'open');
        }

        return $query->get()->getResultArray();
    }

    /** @return list<array<string, mixed>> */
    public function participants(int $sessionId): array
    {
        return $this->db->table('open_play_participants')
            ->where('session_id', $sessionId)
            ->orderBy('created_at')
            ->get()->getResultArray();
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return ((int) $h) * 60 + (int) $m;
    }
}
