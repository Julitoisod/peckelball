<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Builds the availability board: courts × time-slots for one date.
 *
 * Three queries total regardless of court count — occupancy, operating hours
 * and maintenance windows — then the grid is assembled in PHP. The board is
 * rendered on the landing page, the availability page and the dashboard, so
 * it has to be cheap.
 */
class Availability
{
    public const OPEN   = 'open';
    public const BOOKED = 'booked';
    public const MAINT  = 'maintenance';
    public const CLOSED = 'closed';
    public const PAST   = 'past';

    private BaseConnection $db;
    private Settings $settings;

    public function __construct()
    {
        $this->db       = Database::connect();
        $this->settings = service('siteSettings');
    }

    public function slotMinutes(): int
    {
        return max(15, $this->settings->int('booking.slot_minutes', 30));
    }

    /**
     * Every slot start time the facility runs in one session, as `H:i`, in
     * playing order.
     *
     * A session may run past midnight (16:00 → 06:00). The date a board is
     * asked for is the date the session *opens*; slots after midnight belong
     * to it but happen on the next calendar day — see slotTimestamp().
     *
     * @return list<string>
     */
    public function slotTimes(): array
    {
        $step  = $this->slotMinutes();
        $open  = $this->openMinutes();
        $times = [];

        for ($off = 0; $off < $this->sessionMinutes(); $off += $step) {
            $times[] = $this->hhmm($open + $off);
        }

        return $times;
    }

    /** Minutes after opening time, wrapping past midnight. `05:00` in a 16:00 session → 780. */
    public function offset(string $time): int
    {
        return $this->span($this->openMinutes(), $this->minutes($time));
    }

    /** Real Unix time of a slot in the session that opens on $date. */
    public function slotTimestamp(string $date, string $time): int
    {
        return strtotime($date . ' 00:00') + ($this->openMinutes() + $this->offset($time)) * 60;
    }

    /** When the session that opens on $date closes. */
    public function closesAt(string $date): int
    {
        return strtotime($date . ' 00:00') + ($this->openMinutes() + $this->sessionMinutes()) * 60;
    }

    /**
     * The session "today" means. At 02:00 in a 16:00 → 06:00 session that is
     * still yesterday's date: the players on court arrived last night.
     */
    public function businessDate(?int $now = null): string
    {
        $now ??= time();
        $yesterday = date('Y-m-d', strtotime('-1 day', $now));

        return $now < $this->closesAt($yesterday) ? $yesterday : date('Y-m-d', $now);
    }

    private function openMinutes(): int
    {
        return $this->minutes($this->settings->get('hours.open', '06:00'));
    }

    /** Length of one session. Open == close means round the clock. */
    private function sessionMinutes(): int
    {
        return $this->span($this->openMinutes(), $this->minutes($this->settings->get('hours.close', '22:00'))) ?: 1440;
    }

    /** Minutes from $from forward to $to on a 24h clock. */
    private function span(int $from, int $to): int
    {
        return (($to - $from) % 1440 + 1440) % 1440;
    }

    private function hhmm(int $minutes): string
    {
        $minutes = ($minutes % 1440 + 1440) % 1440;

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * The full board for one date.
     *
     * @param list<int>|null $courtIds restrict to these courts
     * @param int|null       $now      override "now" (tests only)
     *
     * @return array{
     *     date: string,
     *     times: list<string>,
     *     courts: list<array<string, mixed>>,
     *     totals: array{open:int, booked:int, slots:int, courts_open_now:int}
     * }
     */
    public function board(string $date, ?array $courtIds = null, ?int $now = null): array
    {
        $times = $this->slotTimes();
        $step  = $this->slotMinutes();
        $now ??= time();
        $dow   = (int) date('w', strtotime($date));

        $courtQuery = $this->db->table('courts')
            ->select('id, code, name, slug, surface_type, status, hourly_rate, peak_rate, map_x, map_y, image_path')
            ->where('deleted_at', null)
            ->orderBy('sort_order');
        if ($courtIds !== null) {
            $courtQuery->whereIn('id', $courtIds);
        }
        $courts = $courtQuery->get()->getResultArray();

        if ($courts === []) {
            return ['date' => $date, 'times' => $times, 'courts' => [], 'totals' => ['open' => 0, 'booked' => 0, 'slots' => 0, 'courts_open_now' => 0]];
        }

        $ids      = array_column($courts, 'id');
        $occupied = $this->occupiedSlots($date, $ids);
        $hours    = $this->operatingHours($ids, $dow);
        $maint    = $this->maintenanceWindows($date, $ids);

        // Peak start == end means no peak window: every hour is the base rate.
        $peakStart = $this->minutes($this->settings->get('pricing.peak_start', '17:00'));
        $peakLen   = $this->span($peakStart, $this->minutes($this->settings->get('pricing.peak_end', '21:00')));

        $totalOpen = 0;
        $totalBooked = 0;
        $openNow   = 0;

        foreach ($courts as &$court) {
            $cid   = (int) $court['id'];
            $cells = [];
            $openCount = 0;
            $openAhead = 0;
            $nextOpen  = null;
            $currentState = self::CLOSED;

            foreach ($times as $time) {
                $mins    = $this->minutes($time);
                $slotTs  = $this->slotTimestamp($date, $time);
                $isPeak  = $this->span($peakStart, $mins) < $peakLen;
                $state   = $this->cellState($cid, $date, $time, $mins, $slotTs, $now, $hours, $occupied, $maint, $court['status']);

                if ($state === self::OPEN) {
                    $openCount++;
                    $openAhead++;
                    $nextOpen ??= $time;
                } elseif ($state === self::BOOKED) {
                    $totalBooked++;
                }

                // "Right now" is the slot containing the current minute. That slot
                // always started before now, so cellState() calls it PAST — for
                // booking it is gone, but for live status what matters is only
                // whether anyone is on the court.
                if ($slotTs <= $now && $now < $slotTs + $step * 60) {
                    $currentState = $state === self::PAST
                        ? (isset($occupied[$cid . '|' . $time]) ? self::BOOKED : self::OPEN)
                        : $state;
                }

                $cells[] = [
                    'time'  => $time,
                    'state' => $state,
                    'peak'  => $isPeak,
                    'rate'  => (float) ($isPeak ? $court['peak_rate'] : $court['hourly_rate']),
                ];
            }

            $totalOpen += $openCount;
            $bookable   = max(1, count(array_filter($cells, static fn ($c) => $c['state'] !== self::PAST && $c['state'] !== self::CLOSED)));
            $freeRatio  = $openCount / $bookable;

            $court['cells']       = $cells;
            $court['open_slots']  = $openCount;
            $court['next_open']   = $nextOpen;
            $court['now_state']   = $currentState;
            $court['day_state']   = $openAhead === 0 ? self::BOOKED : ($freeRatio < 0.34 ? 'filling' : self::OPEN);
            $court['free_ratio']  = round($freeRatio, 3);

            if ($currentState === self::OPEN) {
                $openNow++;
            }
        }
        unset($court);

        return [
            'date'   => $date,
            'times'  => $times,
            'courts' => $courts,
            'totals' => [
                'open'            => $totalOpen,
                'booked'          => $totalBooked,
                'slots'           => count($times) * count($courts),
                'courts_open_now' => $openNow,
            ],
        ];
    }

    /**
     * @param array<int, array{opens:int, closes:int, closed:bool}> $hours
     * @param array<string, true>                                   $occupied
     * @param array<int, list<array{start:int, end:int}>>           $maint
     */
    private function cellState(int $courtId, string $date, string $time, int $mins, int $slotTs, int $now, array $hours, array $occupied, array $maint, string $courtStatus): string
    {
        if ($courtStatus === 'closed') {
            return self::CLOSED;
        }

        // Per-court hours may wrap midnight too, so compare distances from the
        // court's own opening time rather than raw clock minutes.
        $h = $hours[$courtId] ?? null;
        if ($h === null || $h['closed'] || $this->span($h['opens'], $mins) >= ($this->span($h['opens'], $h['closes']) ?: 1440)) {
            return self::CLOSED;
        }

        if (isset($occupied[$courtId . '|' . $time])) {
            return $slotTs < $now ? self::PAST : self::BOOKED;
        }

        foreach ($maint[$courtId] ?? [] as $w) {
            if ($slotTs < $w['end'] && $slotTs + $this->slotMinutes() * 60 > $w['start']) {
                return self::MAINT;
            }
        }

        if ($slotTs < $now) {
            return self::PAST;
        }

        return $courtStatus === 'maintenance' ? self::MAINT : self::OPEN;
    }

    /**
     * @param list<int> $courtIds
     *
     * @return array<string, true> keyed "courtId|H:i"
     */
    public function occupiedSlots(string $date, array $courtIds): array
    {
        $step = $this->slotMinutes();
        $rows = $this->db->table('booking_slots bs')
            ->select('bs.court_id, bs.slot_start')
            ->join('bookings b', 'b.id = bs.booking_id')
            ->whereIn('bs.court_id', $courtIds)
            ->where('bs.slot_start >=', date('Y-m-d H:i:s', strtotime($date . ' 00:00') + $this->openMinutes() * 60))
            ->where('bs.slot_start <', date('Y-m-d H:i:s', $this->closesAt($date)))
            ->whereNotIn('b.status', ['cancelled', 'no_show'])
            ->get()->getResultArray();

        // Snapped down onto this grid: a row written under an older slot length
        // (a 30-minute 18:30) still blocks the hour cell it falls inside.
        $map = [];
        foreach ($rows as $r) {
            $off = $this->offset(date('H:i', strtotime($r['slot_start'])));
            $map[$r['court_id'] . '|' . $this->hhmm($this->openMinutes() + $off - $off % $step)] = true;
        }

        return $map;
    }

    /**
     * @param list<int> $courtIds
     *
     * @return array<int, array{opens:int, closes:int, closed:bool}>
     */
    private function operatingHours(array $courtIds, int $dow): array
    {
        $rows = $this->db->table('court_schedules')
            ->whereIn('court_id', $courtIds)
            ->where('day_of_week', $dow)
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['court_id']] = [
                'opens'  => $this->minutes($r['opens_at']),
                'closes' => $this->minutes($r['closes_at']),
                'closed' => (bool) $r['is_closed'],
            ];
        }

        return $out;
    }

    /**
     * @param list<int> $courtIds
     *
     * @return array<int, list<array{start:int, end:int}>>
     */
    private function maintenanceWindows(string $date, array $courtIds): array
    {
        $rows = $this->db->table('court_maintenance')
            ->whereIn('court_id', $courtIds)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->where('scheduled_start <', date('Y-m-d H:i:s', $this->closesAt($date)))
            ->where('scheduled_end >', $date . ' 00:00:00')
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['court_id']][] = [
                'start' => strtotime($r['scheduled_start']),
                'end'   => strtotime($r['scheduled_end']),
            ];
        }

        return $out;
    }

    /**
     * Consecutive open slots on one court, for the duration picker.
     *
     * @param int $leadMinutes leave out starts sooner than this. Online booking
     *                         refuses them, and a player pays before submitting,
     *                         so offering one would take their money for nothing.
     *
     * @return list<array{start:string, end:string, price:float}>
     */
    public function startTimesFor(string $date, int $courtId, int $durationMinutes, int $leadMinutes = 0): array
    {
        $step   = $this->slotMinutes();
        $needed = (int) ceil($durationMinutes / $step);
        $own    = count($this->slotTimes());
        $cells  = $this->cellRun($date, $courtId, $needed - 1);
        $out    = [];

        // Starts come from this day only; the tail may run into the next one.
        for ($i = 0; $i < $own && $i + $needed <= count($cells); $i++) {
            if ($this->slotTimestamp($cells[$i]['date'], $cells[$i]['time']) < time() + $leadMinutes * 60) {
                continue;
            }

            $price = 0.0;
            for ($j = 0; $j < $needed; $j++) {
                if ($cells[$i + $j]['state'] !== self::OPEN) {
                    continue 2;
                }
                $price += $cells[$i + $j]['rate'] * ($step / 60);
            }

            $out[] = [
                'start' => $cells[$i]['time'],
                'end'   => $this->hhmm($this->minutes($cells[$i]['time']) + $durationMinutes),
                'price' => round($price, 2),
            ];
        }

        return $out;
    }

    /**
     * One court's cells for $date, followed by the first $extra cells of the
     * next day. Round the clock a booking may run past the day boundary — only
     * the rate changes at it — so the slots it needs can straddle two boards.
     * With hours closed in between the next day does not follow on, and a
     * booking has to finish by closing time.
     *
     * @return list<array{date:string, time:string, state:string, peak:bool, rate:float}>
     */
    public function cellRun(string $date, int $courtId, int $extra): array
    {
        $run = $this->dayCells($date, $courtId);

        if ($extra > 0 && $this->sessionMinutes() === 1440) {
            $next = date('Y-m-d', strtotime($date . ' +1 day'));
            array_push($run, ...array_slice($this->dayCells($next, $courtId), 0, $extra));
        }

        return $run;
    }

    /** @return list<array{date:string, time:string, state:string, peak:bool, rate:float}> */
    private function dayCells(string $date, int $courtId): array
    {
        $board = $this->board($date, [$courtId]);
        if ($board['courts'] === []) {
            return [];
        }

        return array_map(
            static fn (array $cell): array => ['date' => $date] + $cell,
            $board['courts'][0]['cells']
        );
    }

    /** Facility-wide snapshot for the hero strip. */
    public function snapshot(?int $now = null): array
    {
        $board  = $this->board($this->businessDate($now), null, $now);
        $open   = [];
        $closed = 0;

        foreach ($board['courts'] as $c) {
            if ($c['now_state'] === self::OPEN) {
                $open[] = $c['code'];
            } elseif ($c['now_state'] === self::CLOSED) {
                $closed++;
            }
        }

        return [
            'courts_total'   => count($board['courts']),
            'courts_open'    => count($open),
            'open_codes'     => $open,
            // Every court shut = the facility is outside operating hours, which
            // is a different message from "all five are in play".
            'facility_open'  => $board['courts'] !== [] && $closed < count($board['courts']),
            'next_open'      => $this->earliestNextOpen($board),
            'peak_remaining' => $board['totals']['open'],
        ];
    }

    /** @return array{court:string, code:string, time:string}|null */
    private function earliestNextOpen(array $board): ?array
    {
        $best = null;
        foreach ($board['courts'] as $c) {
            if ($c['next_open'] === null) {
                continue;
            }
            if ($best === null || $this->offset($c['next_open']) < $this->offset($best['time'])) {
                $best = ['court' => $c['name'], 'code' => $c['code'], 'time' => $c['next_open']];
            }
        }

        return $best;
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return ((int) $h) * 60 + (int) $m;
    }
}
