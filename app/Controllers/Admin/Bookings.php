<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Bookings extends BaseController
{
    public function index(): string
    {
        $date = (string) ($this->request->getGet('date') ?? business_date());
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
            $date = business_date();
        }

        $status = (string) ($this->request->getGet('status') ?? '');
        $query  = $this->db->table('bookings b')
            ->select('b.*, courts.code AS court_code, courts.name AS court_name, users.first_name, users.last_name, users.phone')
            ->join('courts', 'courts.id = b.court_id')
            ->join('users', 'users.id = b.user_id', 'left')
            ->where('b.booking_date', $date);

        if (in_array($status, ['pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'no_show'], true)) {
            $query->where('b.status', $status);
        }

        $rows = $query->orderBy('b.start_time')->orderBy('courts.sort_order')->get()->getResultArray();

        return $this->page('admin/bookings', [
            'title'  => 'Booking calendar | Go Paddle',
            'date'   => $date,
            'status' => $status,
            'board'  => service('availability')->board($date),
            'bookings' => $rows,
            'totals' => [
                'count'   => count($rows),
                'revenue' => array_sum(array_map(static fn ($r) => (float) $r['total_amount'], array_filter($rows, static fn ($r) => $r['status'] !== 'cancelled'))),
                'unpaid'  => count(array_filter($rows, static fn ($r) => $r['payment_status'] === 'unpaid' && $r['status'] !== 'cancelled')),
            ],
        ], 'layouts/admin');
    }

    public function cancel(int $id)
    {
        $ok = service('bookings')->cancel(
            $id,
            (string) ($this->request->getPost('reason') ?: 'Cancelled at the desk'),
            (int) $this->auth->id()
        );

        return redirect()->back()->with(
            $ok ? 'success' : 'error',
            $ok ? 'Booking cancelled and the slot released.' : 'That booking could not be cancelled.'
        );
    }

    public function checkIn(int $id)
    {
        $booking = $this->db->table('bookings')->select('qr_token, reference')->where('id', $id)->get()->getRowArray();
        if ($booking === null) {
            return redirect()->back()->with('error', 'No such booking.');
        }

        $result = service('bookings')->checkIn($booking['qr_token']);

        return redirect()->back()->with(
            $result === null ? 'error' : 'success',
            $result === null
                ? 'That booking cannot be checked in. It may already be on court or cancelled.'
                : $booking['reference'] . ' checked in.'
        );
    }
}
