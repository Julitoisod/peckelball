<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index(): string
    {
        $today = business_date();
        $board = service('availability')->board($today);

        // The session window, not the calendar day: cash taken at 01:00 belongs
        // to the night that opened at 16:00 yesterday.
        $paidToday = (float) ($this->db->table('payments')
            ->selectSum('amount', 't')->where('status', 'paid')
            ->where('paid_at >=', $today . ' 00:00:00')
            ->where('paid_at <', date('Y-m-d H:i:s', service('availability')->closesAt($today)))
            ->get()->getRow('t') ?? 0);

        $paidMonth = (float) ($this->db->table('payments')
            ->selectSum('amount', 't')->where('status', 'paid')
            ->where('paid_at >=', date('Y-m-01 00:00:00'))->get()->getRow('t') ?? 0);

        // Peak-hour analytics: slots sold per hour over the last 30 days.
        $peak = $this->db->query(
            'SELECT HOUR(slot_start) AS hr, COUNT(*) AS n
             FROM booking_slots
             WHERE slot_start >= ? AND slot_start < ?
             GROUP BY hr ORDER BY hr',
            [date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('+1 day'))]
        )->getResultArray();

        return $this->page('admin/dashboard', [
            'title' => 'Dashboard | Bernie Pickleball',
            'board' => $board,
            'today' => $today,
            'metrics' => [
                'today_bookings' => $this->db->table('bookings')->where('booking_date', $today)->whereNotIn('status', ['cancelled'])->countAllResults(),
                'on_court'       => $this->db->table('bookings')->where('booking_date', $today)->where('status', 'checked_in')->countAllResults(),
                'awaiting'       => $this->db->table('bookings')->where('booking_date >=', $today)->where('payment_status', 'unpaid')->whereNotIn('status', ['cancelled', 'no_show'])->countAllResults(),
                'revenue_today'  => $paidToday,
                'revenue_month'  => $paidMonth,
                'utilisation'    => $board['totals']['slots'] === 0 ? 0 : round($board['totals']['booked'] / $board['totals']['slots'] * 100),
                'members'        => $this->db->table('memberships')->where('status', 'active')->countAllResults(),
                'low_stock'      => $this->db->table('products')->where('is_active', 1)->where('stock_qty <= reorder_level', null, false)->countAllResults(),
            ],
            'peak' => $peak,
            'arrivals' => $this->db->table('bookings b')
                ->select('b.*, courts.code AS court_code, courts.name AS court_name, users.first_name, users.last_name')
                ->join('courts', 'courts.id = b.court_id')
                ->join('users', 'users.id = b.user_id', 'left')
                ->where('b.booking_date', $today)
                ->whereIn('b.status', ['pending', 'confirmed', 'checked_in'])
                ->orderBy('b.start_time')
                ->limit(12)->get()->getResultArray(),
            'maintenance' => $this->db->table('court_maintenance cm')
                ->select('cm.*, courts.code AS court_code')
                ->join('courts', 'courts.id = cm.court_id')
                ->whereIn('cm.status', ['scheduled', 'in_progress'])
                ->orderBy('cm.scheduled_start')->limit(5)->get()->getResultArray(),
            'recent' => $this->db->table('audit_logs al')
                ->select('al.*, users.first_name, users.last_name')
                ->join('users', 'users.id = al.user_id', 'left')
                ->orderBy('al.created_at', 'DESC')->limit(8)->get()->getResultArray(),
        ], 'layouts/admin');
    }

    public function audit(): string
    {
        return $this->page('admin/audit', [
            'title' => 'Audit log | Bernie Pickleball',
            'logs'  => $this->db->table('audit_logs al')
                ->select('al.*, users.first_name, users.last_name, users.email')
                ->join('users', 'users.id = al.user_id', 'left')
                ->orderBy('al.created_at', 'DESC')
                ->limit(100)->get()->getResultArray(),
        ], 'layouts/admin');
    }
}
