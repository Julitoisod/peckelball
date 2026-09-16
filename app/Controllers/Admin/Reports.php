<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Read-only aggregates over a date range. Nothing here writes.
 *
 * Two rules keep the numbers meaningful. Revenue counts `payments` rows that are
 * actually settled, never booking totals, because a booking that nobody paid for
 * is not income. And occupancy is measured against the slots the facility really
 * ran in the range, taken from the availability engine rather than an assumed
 * open-all-day figure.
 */
class Reports extends BaseController
{
    public function index(): string
    {
        $from = $this->date((string) $this->request->getGet('from'), date('Y-m-01'));
        $to   = $this->date((string) $this->request->getGet('to'), date('Y-m-d'));
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        // A very long range would make this page do a lot of work for nothing.
        $days = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
        if ($days > 186) {
            $from = date('Y-m-d', strtotime($to . ' -185 days'));
            $days = 186;
        }

        return $this->page('admin/reports', [
            'title'    => 'Reports | Bernie Pickleball',
            'from'     => $from,
            'to'       => $to,
            'days'     => $days,
            'takings'  => $this->takings($from, $to),
            'byDay'    => $this->byDay($from, $to),
            'byMethod' => $this->group($from, $to, 'method'),
            'byPurpose' => $this->group($from, $to, 'purpose'),
            'courts'   => $this->courtUse($from, $to),
            'players'  => $this->topPlayers($from, $to),
            'coaches'  => $this->coachUse($from, $to),
            'bookings' => $this->bookingMix($from, $to),
            'slots'    => count(service('availability')->slotTimes()),
        ], 'layouts/admin');
    }

    private function takings(string $from, string $to): float
    {
        return (float) ($this->db->table('payments')
            ->selectSum('amount', 'total')
            ->where('status', 'paid')
            ->where('DATE(created_at) >=', $from)->where('DATE(created_at) <=', $to)
            ->get()->getRow('total') ?? 0);
    }

    /** @return list<array<string, mixed>> */
    private function byDay(string $from, string $to): array
    {
        return $this->db->table('payments')
            ->select('DATE(created_at) AS day, SUM(amount) AS total, COUNT(*) AS n')
            ->where('status', 'paid')
            ->where('DATE(created_at) >=', $from)->where('DATE(created_at) <=', $to)
            ->groupBy('DATE(created_at)')->orderBy('day')->get()->getResultArray();
    }

    /** @return list<array<string, mixed>> */
    private function group(string $from, string $to, string $column): array
    {
        return $this->db->table('payments')
            ->select($column . ' AS label, SUM(amount) AS total, COUNT(*) AS n')
            ->where('status', 'paid')
            ->where('DATE(created_at) >=', $from)->where('DATE(created_at) <=', $to)
            ->groupBy($column)->orderBy('total', 'DESC')->get()->getResultArray();
    }

    /** @return list<array<string, mixed>> */
    private function courtUse(string $from, string $to): array
    {
        return $this->db->table('courts c')
            ->select('c.id, c.code, c.name,
                      COUNT(b.id) AS bookings,
                      COALESCE(SUM(b.duration_minutes), 0) AS minutes,
                      COALESCE(SUM(CASE WHEN b.payment_status = "paid" THEN b.total_amount END), 0) AS collected')
            ->join('bookings b', 'b.court_id = c.id AND b.booking_date >= ' . $this->db->escape($from)
                . ' AND b.booking_date <= ' . $this->db->escape($to) . ' AND b.status <> "cancelled"', 'left')
            ->where('c.deleted_at', null)
            ->groupBy('c.id')->orderBy('c.sort_order')->get()->getResultArray();
    }

    /** @return list<array<string, mixed>> */
    private function topPlayers(string $from, string $to): array
    {
        return $this->db->table('bookings b')
            ->select('u.id, u.first_name, u.last_name, u.phone,
                      COUNT(b.id) AS bookings, SUM(b.total_amount) AS spend')
            ->join('users u', 'u.id = b.user_id')
            ->where('b.booking_date >=', $from)->where('b.booking_date <=', $to)
            ->whereNotIn('b.status', ['cancelled'])
            ->groupBy('u.id')->orderBy('spend', 'DESC')->limit(10)->get()->getResultArray();
    }

    /** @return list<array<string, mixed>> */
    private function coachUse(string $from, string $to): array
    {
        return $this->db->table('coaching_sessions cs')
            ->select('u.first_name, u.last_name, COUNT(cs.id) AS sessions,
                      COALESCE(SUM(cs.rate), 0) AS fees')
            ->join('coaches c', 'c.id = cs.coach_id')
            ->join('users u', 'u.id = c.user_id')
            ->where('cs.session_date >=', $from)->where('cs.session_date <=', $to)
            ->where('cs.status !=', 'cancelled')
            ->groupBy('c.id')->orderBy('sessions', 'DESC')->get()->getResultArray();
    }

    /** @return array<string, mixed> */
    private function bookingMix(string $from, string $to): array
    {
        $rows = $this->db->table('bookings')
            ->select('booking_type, status, COUNT(*) AS n, SUM(duration_minutes) AS minutes')
            ->where('booking_date >=', $from)->where('booking_date <=', $to)
            ->groupBy('booking_type')->groupBy('status')->get()->getResultArray();

        $out = ['total' => 0, 'cancelled' => 0, 'minutes' => 0, 'types' => []];
        foreach ($rows as $r) {
            $n = (int) $r['n'];
            if ($r['status'] === 'cancelled') {
                $out['cancelled'] += $n;
                continue;
            }
            $out['total']   += $n;
            $out['minutes'] += (int) $r['minutes'];
            $out['types'][$r['booking_type']] = ($out['types'][$r['booking_type']] ?? 0) + $n;
        }

        arsort($out['types']);

        return $out;
    }

    private function date(string $value, string $fallback): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false ? $value : $fallback;
    }
}
