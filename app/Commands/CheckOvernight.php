<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Self-check for sessions that run past midnight (16:00 → 06:00, hourly, ₱250).
 *
 * Books a real two-hour slot across midnight a week out, proves the second
 * booking of the same hour is refused, then deletes what it wrote.
 * Run: php spark check:overnight
 */
class CheckOvernight extends BaseCommand
{
    protected $group       = 'Bernie';
    protected $name        = 'check:overnight';
    protected $description = 'Assert slot times, dates and prices for a session crossing midnight.';

    private int $fails = 0;

    public function run(array $params)
    {
        $svc  = service('availability');
        $date = date('Y-m-d', strtotime('+7 days'));
        $next = date('Y-m-d', strtotime($date . ' +1 day'));

        $times = $svc->slotTimes();
        $this->check('14 hourly slots', count($times) === 14, count($times));
        $this->check('first slot 16:00', ($times[0] ?? '') === '16:00', $times[0] ?? '-');
        $this->check('last slot 05:00', (end($times) ?: '') === '05:00', end($times) ?: '-');

        $this->check('01:00 lands on the next day', date('Y-m-d H:i', $svc->slotTimestamp($date, '01:00')) === "{$next} 01:00", date('Y-m-d H:i', $svc->slotTimestamp($date, '01:00')));
        $this->check('session closes 06:00 next day', date('Y-m-d H:i', $svc->closesAt($date)) === "{$next} 06:00", date('Y-m-d H:i', $svc->closesAt($date)));
        $this->check('02:00 belongs to last night', $svc->businessDate(strtotime("{$next} 02:00")) === $date, $svc->businessDate(strtotime("{$next} 02:00")));
        $this->check('10:00 belongs to tonight', $svc->businessDate(strtotime("{$next} 10:00")) === $next, $svc->businessDate(strtotime("{$next} 10:00")));

        $court = Database::connect()->table('courts')->where('status', 'active')->where('deleted_at', null)->orderBy('sort_order')->get()->getRowArray();
        $board = $svc->board($date, [(int) $court['id']]);
        $rates = array_unique(array_column($board['courts'][0]['cells'], 'rate'));
        $this->check('flat ₱250 every hour', $rates === [250.0], implode(',', $rates));

        $offers = array_column($svc->startTimesFor($date, (int) $court['id'], 120), null, 'start');
        $this->check('23:00 for 2h ends 01:00', ($offers['23:00']['end'] ?? '') === '01:00', $offers['23:00']['end'] ?? '-');
        $this->check('23:00 for 2h costs ₱500', (float) ($offers['23:00']['price'] ?? 0) === 500.0, $offers['23:00']['price'] ?? '-');
        $this->check('04:00 for 2h is not offered (past close)', ! isset($offers['05:00']) && isset($offers['04:00']), implode(',', array_keys($offers)));

        $this->bookAcrossMidnight((int) $court['id'], $date, $next);

        if ($this->fails > 0) {
            CLI::error("{$this->fails} check(s) failed.");

            return EXIT_ERROR;
        }

        CLI::write('Overnight booking works end to end.', 'green');

        return EXIT_SUCCESS;
    }

    private function bookAcrossMidnight(int $courtId, string $date, string $next): void
    {
        $db       = Database::connect();
        $bookings = service('bookings');
        $input    = [
            'court_id' => $courtId, 'date' => $date, 'start_time' => '23:00', 'duration' => 120,
            'guest_name' => 'check:overnight', 'guest_phone' => '0000000', 'payment_method' => 'cash',
        ];

        $first = $bookings->create($input);
        $this->check('booking 23:00-01:00 accepted', $first['ok'], $first['error'] ?? '');
        if (! $first['ok']) {
            return;
        }

        try {
            $row   = $db->table('bookings')->where('id', $first['id'])->get()->getRowArray();
            $slots = array_column($db->table('booking_slots')->where('booking_id', $first['id'])->orderBy('slot_start')->get()->getResultArray(), 'slot_start');
            $this->check('stored slots straddle midnight', $slots === ["{$date} 23:00:00", "{$next} 00:00:00"], implode(' | ', $slots));
            $this->check('stored total ₱500', (float) $row['total_amount'] === 500.0, $row['total_amount']);
            $this->check('stored end 01:00', $row['end_time'] === '01:00:00', $row['end_time']);

            $cells = array_column(service('availability')->board($date, [$courtId])['courts'][0]['cells'], 'state', 'time');
            $this->check('board shows 00:00 booked', ($cells['00:00'] ?? '') === 'booked', $cells['00:00'] ?? '-');

            $clash = $bookings->create(['start_time' => '00:00', 'duration' => 60] + $input);
            $this->check('double booking 00:00 refused', ! $clash['ok'], $clash['error'] ?? 'accepted!');
            if ($clash['ok']) {
                $this->cleanup((int) $clash['id']);
            }
        } finally {
            $this->cleanup((int) $first['id']);
        }
    }

    private function cleanup(int $bookingId): void
    {
        $db = Database::connect();
        $db->table('booking_slots')->where('booking_id', $bookingId)->delete();
        $db->table('bookings')->where('id', $bookingId)->delete();
        $db->table('audit_logs')->where('entity_type', 'booking')->where('entity_id', $bookingId)->delete();
    }

    private function check(string $label, bool $ok, mixed $actual): void
    {
        $this->fails += $ok ? 0 : 1;
        CLI::write(sprintf('  %s %s%s', $ok ? '[ok]  ' : '[FAIL]', $label, $ok ? '' : "  (got: {$actual})"), $ok ? 'green' : 'red');
    }
}
