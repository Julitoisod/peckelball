<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Self-check for open play: opening a session holds the court, a booking
 * cannot land on it, sign-ups stop at the capacity, closing releases the
 * court while keeping the sheet, and reopening takes the hours back.
 *
 * Writes a session a week out and deletes everything it wrote.
 * Run: php spark check:openplay
 */
class CheckOpenPlay extends BaseCommand
{
    protected $group       = 'GoPaddle';
    protected $name        = 'check:openplay';
    protected $description = 'Assert open play holds the court, caps sign-ups and releases cleanly.';

    private int $fails = 0;

    public function run(array $params)
    {
        $openPlay = service('openPlay');
        $date     = date('Y-m-d', strtotime('+6 days'));
        $court    = (int) service('availability')->board($date)['courts'][0]['id'];

        $open = $openPlay->open([
            'court_id' => $court, 'date' => $date, 'start_time' => '18:00', 'duration' => 180,
            'fee' => 100, 'capacity' => 3, 'title' => 'check:openplay',
        ]);
        $this->check('session opens', $open['ok'], $open['error'] ?? '');
        if (! $open['ok']) {
            return $this->finish();
        }
        $id = (int) $open['id'];

        try {
            $cells = $this->cells($date, $court);
            $this->check('18:00-20:00 held on the board', ($cells['18:00'] ?? '') === 'booked' && ($cells['19:00'] ?? '') === 'booked' && ($cells['20:00'] ?? '') === 'booked', implode(',', [$cells['18:00'] ?? '-', $cells['19:00'] ?? '-', $cells['20:00'] ?? '-']));
            $this->check('21:00 left alone', ($cells['21:00'] ?? '') === 'open', $cells['21:00'] ?? '-');

            $clash = service('bookings')->create([
                'court_id' => $court, 'date' => $date, 'start_time' => '19:00', 'duration' => 60,
                'guest_name' => 'check:openplay clash', 'guest_phone' => '0000000', 'payment_method' => 'cash',
            ]);
            $this->check('a booking over it is refused', ! $clash['ok'], $clash['error'] ?? 'accepted!');
            if ($clash['ok']) {
                $this->wipeBooking((int) $clash['id']);
            }

            foreach ([['Ana', 'cash', false], ['Ben', 'gcash', false], ['Cora', 'cash', true]] as [$name, $method, $paid]) {
                $r = $openPlay->register($id, ['name' => $name, 'phone' => '0917', 'payment_method' => $method, 'paid' => $paid]);
                $this->check("{$name} signed up", $r['ok'], $r['error'] ?? '');
            }
            $this->check('no places left at capacity 3', $openPlay->placesLeft($id) === 0, $openPlay->placesLeft($id));

            $full = $openPlay->register($id, ['name' => 'Dino']);
            $this->check('the fourth is turned away', ! $full['ok'], $full['error'] ?? 'accepted!');

            $rows = $openPlay->participants($id);
            $gcash = array_values(array_filter($rows, static fn ($p) => $p['name'] === 'Ben'))[0] ?? [];
            $cash  = array_values(array_filter($rows, static fn ($p) => $p['name'] === 'Ana'))[0] ?? [];
            $this->check('GCash sign-up counts as paid', ($gcash['payment_status'] ?? '') === 'paid', $gcash['payment_status'] ?? '-');
            $this->check('cash sign-up starts unpaid', ($cash['payment_status'] ?? '') === 'unpaid', $cash['payment_status'] ?? '-');
            $this->check('each owes the session fee', (float) ($cash['amount'] ?? 0) === 100.0, $cash['amount'] ?? '-');

            $openPlay->markPaid((int) $cash['id']);
            $listing = array_values(array_filter($openPlay->listing($date), static fn ($s) => (int) $s['id'] === $id))[0] ?? [];
            $this->check('listing counts 3 in, 3 paid, ₱300 taken', (int) ($listing['taken'] ?? 0) === 3 && (int) ($listing['paid_count'] ?? 0) === 3 && (float) ($listing['taken_amount'] ?? 0) === 300.0, sprintf('%s in / %s paid / %s', $listing['taken'] ?? '-', $listing['paid_count'] ?? '-', $listing['taken_amount'] ?? '-'));

            $openPlay->close($id);
            $cells = $this->cells($date, $court);
            $this->check('closing frees the court', ($cells['18:00'] ?? '') === 'open', $cells['18:00'] ?? '-');
            $this->check('the sheet survives closing', count($openPlay->participants($id)) === 3, count($openPlay->participants($id)));

            $again = $openPlay->reopen($id);
            $this->check('reopening takes the hours back', $again['ok'], $again['error'] ?? '');
            $id = (int) ($again['id'] ?? $id);
            $cells = $this->cells($date, $court);
            $this->check('held again after reopening', ($cells['18:00'] ?? '') === 'booked', $cells['18:00'] ?? '-');
            $this->check('sign-ups came with it', count($openPlay->participants($id)) === 3, count($openPlay->participants($id)));
        } finally {
            $this->cleanup($id);
        }

        return $this->finish();
    }

    /** @return array<string, string> */
    private function cells(string $date, int $court): array
    {
        $board = service('availability')->board($date, [$court]);

        return array_column($board['courts'][0]['cells'], 'state', 'time');
    }

    private function cleanup(int $sessionId): void
    {
        $db      = Database::connect();
        $session = $db->table('open_play_sessions')->where('id', $sessionId)->get()->getRowArray();

        if ($session !== null && $session['booking_id'] !== null) {
            $this->wipeBooking((int) $session['booking_id']);
        }

        $db->table('open_play_participants')->where('session_id', $sessionId)->delete();
        $db->table('open_play_sessions')->where('id', $sessionId)->delete();
        $db->table('audit_logs')->where('entity_type', 'open_play_session')->where('entity_id', $sessionId)->delete();
    }

    private function wipeBooking(int $bookingId): void
    {
        $db = Database::connect();
        $db->table('booking_slots')->where('booking_id', $bookingId)->delete();
        $db->table('audit_logs')->where('entity_type', 'booking')->where('entity_id', $bookingId)->delete();
        $db->table('bookings')->where('id', $bookingId)->delete();
    }

    private function check(string $label, bool $ok, mixed $actual): void
    {
        $this->fails += $ok ? 0 : 1;
        CLI::write(sprintf('  %s %s%s', $ok ? '[ok]  ' : '[FAIL]', $label, $ok ? '' : "  (got: {$actual})"), $ok ? 'green' : 'red');
    }

    private function finish(): int
    {
        if ($this->fails > 0) {
            CLI::error("{$this->fails} check(s) failed.");

            return EXIT_ERROR;
        }

        CLI::write('Open play holds the court, caps sign-ups and releases cleanly.', 'green');

        return EXIT_SUCCESS;
    }
}
