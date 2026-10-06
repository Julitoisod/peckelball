<?php

namespace App\Commands;

use App\Services\Availability;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Self-check for the "right now" derivation in Availability::board().
 *
 * The in-progress slot always started before now, so cellState() calls it PAST.
 * It used to be reclassified as BOOKED, which made a free court read Booked all
 * day and the green hero state unreachable. Run: php spark check:availability
 */
class CheckAvailability extends BaseCommand
{
    protected $group       = 'GoPaddle';
    protected $name        = 'check:availability';
    protected $description = 'Assert now_state reflects real occupancy at three hours of the session.';

    public function run(array $params)
    {
        $svc   = new Availability();
        $date  = $svc->businessDate();
        $fails = 0;

        // Early, late, and after midnight: the last one is the next calendar day.
        foreach (['16:15', '21:45', '02:15'] as $clock) {
            $now      = $svc->slotTimestamp($date, $clock);
            $board    = $svc->board($date, null, $now);
            $step     = $svc->slotMinutes();
            $occupied = $svc->occupiedSlots($date, array_column($board['courts'], 'id'));

            foreach ($board['courts'] as $c) {
                $cell = null;
                foreach ($c['cells'] as $x) {
                    $ts = $svc->slotTimestamp($date, $x['time']);
                    if ($ts <= $now && $now < $ts + $step * 60) {
                        $cell = $x;
                        break;
                    }
                }

                if ($cell === null) {
                    continue;
                }

                $taken  = isset($occupied[$c['id'] . '|' . $cell['time']]);
                $expect = $cell['state'] === Availability::CLOSED || $cell['state'] === Availability::MAINT
                    ? $cell['state']
                    : ($taken ? Availability::BOOKED : Availability::OPEN);

                $ok = $c['now_state'] === $expect;
                $fails += $ok ? 0 : 1;
                CLI::write(sprintf(
                    '  %s %s %s slot=%s taken=%s now_state=%s expected=%s',
                    $ok ? '[ok]  ' : '[FAIL]',
                    $clock,
                    $c['code'],
                    $cell['time'],
                    $taken ? 'yes' : 'no',
                    $c['now_state'],
                    $expect
                ), $ok ? 'green' : 'red');
            }
        }

        if ($fails > 0) {
            CLI::error("{$fails} court(s) reported the wrong live state.");

            return EXIT_ERROR;
        }

        CLI::write('All courts report their real live state.', 'green');

        return EXIT_SUCCESS;
    }
}
