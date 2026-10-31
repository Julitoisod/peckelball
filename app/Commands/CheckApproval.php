<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Self-check for GCash approval: an online booking holds the court without
 * being confirmed or paid, it cannot be checked in, approving it takes exactly
 * the booking's own total, and rejecting it gives the court back.
 *
 * Writes bookings a week out and deletes everything it wrote.
 * Run: php spark check:approval
 */
class CheckApproval extends BaseCommand
{
    protected $group       = 'GoPaddle';
    protected $name        = 'check:approval';
    protected $description = 'Assert online bookings wait for approval and move money only when approved.';

    private int $fails = 0;

    /** @var list<int> */
    private array $made = [];

    public function run(array $params)
    {
        $bookings = service('bookings');
        $date     = date('Y-m-d', strtotime('+6 days'));
        $court    = (int) service('availability')->board($date)['courts'][0]['id'];
        $online   = [
            'court_id' => $court, 'date' => $date, 'duration' => 60, 'player_count' => 2,
            'guest_name' => 'check:approval', 'guest_phone' => '0000000', 'payment_method' => 'gcash',
            'awaiting_approval' => true, 'gcash_reference' => 'CHECK0000001', 'payment_proof' => 'none.png',
        ];

        try {
            $first = $bookings->create($online + ['start_time' => '18:00']);
            $this->check('an online booking goes in', $first['ok'], $first['error'] ?? '');
            $id = (int) ($first['id'] ?? 0);
            $this->made[] = $id;

            $row = $this->row($id);
            $this->check('it is pending', $row['status'] === 'pending', $row['status']);
            $this->check('it is unpaid', $row['payment_status'] === 'unpaid', $row['payment_status']);
            $this->check('no money is on the ledger', service('payments')->outstanding($id) === (float) $row['total_amount'], service('payments')->outstanding($id));
            $this->check('it keeps the reference and screenshot', $row['gcash_reference'] === 'CHECK0000001' && $row['payment_proof'] === 'none.png', $row['gcash_reference']);
            $this->check('it holds the court', $this->cell($date, $court, '18:00') === 'booked', $this->cell($date, $court, '18:00'));

            $clash = $bookings->create($online + ['start_time' => '18:00']);
            $this->check('nobody else can take the slot', ! $clash['ok'], $clash['error'] ?? 'accepted!');
            if ($clash['ok']) {
                $this->made[] = (int) $clash['id'];
            }

            $this->check('it cannot be checked in', $bookings->checkIn((string) $row['qr_token']) === null, 'checked in!');

            $approved = $bookings->approve($id, 1);
            $this->check('approving works', $approved['ok'], $approved['error'] ?? '');
            $row = $this->row($id);
            $this->check('it is confirmed', $row['status'] === 'confirmed', $row['status']);
            $this->check('it is paid', $row['payment_status'] === 'paid', $row['payment_status']);
            $this->check('the ledger took the booking total', service('payments')->outstanding($id) === 0.0, service('payments')->outstanding($id));

            $twice = $bookings->approve($id, 1);
            $this->check('approving twice is refused', ! $twice['ok'], 'approved again!');
            $this->check('one payment on the ledger', $this->ledger($id) === 1, $this->ledger($id));
            $this->check('it can now be checked in', $bookings->checkIn((string) $row['qr_token']) !== null, 'refused');

            $second = $bookings->create(['gcash_reference' => 'CHECK0000002', 'start_time' => '19:00'] + $online);
            $this->check('a second online booking goes in', $second['ok'], $second['error'] ?? '');
            $other = (int) ($second['id'] ?? 0);
            $this->made[] = $other;

            $this->check('rejecting works', $bookings->reject($other, 'No money arrived', 1), 'refused');
            $row = $this->row($other);
            $this->check('it is cancelled with the reason', $row['status'] === 'cancelled' && str_contains((string) $row['cancellation_reason'], 'No money arrived'), $row['status']);
            $this->check('nothing was refunded or taken', $row['payment_status'] === 'unpaid' && $this->ledger($other) === 0, $row['payment_status']);
            $this->check('the court is free again', $this->cell($date, $court, '19:00') === 'open', $this->cell($date, $court, '19:00'));
            $this->check('rejecting twice is refused', ! $bookings->reject($other, 'Again', 1), 'rejected again!');
            $this->check('a cancelled booking cannot be approved', ! $bookings->approve($other, 1)['ok'], 'approved!');

            $desk = $bookings->create([
                'court_id' => $court, 'date' => $date, 'start_time' => '20:00', 'duration' => 60,
                'guest_name' => 'check:approval desk', 'guest_phone' => '0000000', 'payment_method' => 'cash', 'at_desk' => true,
            ]);
            $this->made[] = (int) ($desk['id'] ?? 0);
            $row = $this->row((int) ($desk['id'] ?? 0));
            $this->check('a desk booking still confirms straight away', ($row['status'] ?? '') === 'confirmed', $row['status'] ?? '-');
        } finally {
            $this->cleanup();
        }

        return $this->finish();
    }

    /** @return array<string, mixed> */
    private function row(int $id): array
    {
        return Database::connect()->table('bookings')->where('id', $id)->get()->getRowArray() ?? [];
    }

    private function ledger(int $id): int
    {
        return Database::connect()->table('payments')->where('booking_id', $id)->where('status', 'paid')->countAllResults();
    }

    private function cell(string $date, int $court, string $time): string
    {
        $board = service('availability')->board($date, [$court]);

        return array_column($board['courts'][0]['cells'], 'state', 'time')[$time] ?? '-';
    }

    private function cleanup(): void
    {
        $db = Database::connect();

        foreach (array_filter($this->made) as $id) {
            $db->table('payments')->where('booking_id', $id)->delete();
            $db->table('booking_slots')->where('booking_id', $id)->delete();
            $db->table('audit_logs')->where('entity_type', 'booking')->where('entity_id', $id)->delete();
            $db->table('bookings')->where('id', $id)->delete();
        }
    }

    private function check(string $label, bool $ok, mixed $detail = ''): void
    {
        CLI::write(($ok ? '  ok   ' : '  FAIL ') . $label . ($ok ? '' : ' (' . $detail . ')'), $ok ? 'green' : 'red');
        $this->fails += $ok ? 0 : 1;
    }

    private function finish(): int
    {
        CLI::write($this->fails === 0 ? 'All good.' : $this->fails . ' failed.', $this->fails === 0 ? 'green' : 'red');

        return $this->fails === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }
}
