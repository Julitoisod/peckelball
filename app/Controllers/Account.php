<?php

namespace App\Controllers;

class Account extends BaseController
{
    public function index(): string
    {
        $userId = (int) $this->auth->id();

        return $this->page('pages/account', [
            'title' => 'My account | Bernie Pickleball',
            'user'  => $this->auth->user(),
            'upcoming' => $this->bookingQuery($userId)
                ->where('bookings.booking_date >=', business_date())
                ->whereIn('bookings.status', ['pending', 'confirmed', 'checked_in'])
                ->orderBy('bookings.booking_date')->orderBy('bookings.start_time')
                ->limit(5)->get()->getResultArray(),
            'membership' => $this->db->table('memberships m')
                ->select('m.*, membership_plans.name AS plan_name, membership_plans.billing_period, membership_plans.court_credit_hours')
                ->join('membership_plans', 'membership_plans.id = m.plan_id')
                ->where('m.user_id', $userId)->where('m.status', 'active')
                ->orderBy('m.ends_on', 'DESC')->get()->getRowArray(),
            'notifications' => service('bernieNotify')->forUser($userId, 6),
            'stats' => [
                'total'  => $this->db->table('bookings')->where('user_id', $userId)->whereIn('status', ['completed', 'checked_in'])->countAllResults(),
                'spend'  => (float) ($this->db->table('payments')->selectSum('amount', 't')->where('user_id', $userId)->where('status', 'paid')->get()->getRow('t') ?? 0),
                'coached' => $this->db->table('coaching_sessions')->where('user_id', $userId)->where('status', 'completed')->countAllResults(),
            ],
        ]);
    }

    public function bookings(): string
    {
        $userId = (int) $this->auth->id();

        return $this->page('pages/account_bookings', [
            'title'    => 'My bookings | Bernie Pickleball',
            'upcoming' => $this->bookingQuery($userId)
                ->where('bookings.booking_date >=', business_date())
                ->whereIn('bookings.status', ['pending', 'confirmed', 'checked_in'])
                ->orderBy('bookings.booking_date')->orderBy('bookings.start_time')
                ->get()->getResultArray(),
            'past' => $this->bookingQuery($userId)
                ->groupStart()
                    ->where('bookings.booking_date <', business_date())
                    ->orWhereIn('bookings.status', ['completed', 'cancelled', 'no_show'])
                ->groupEnd()
                ->orderBy('bookings.booking_date', 'DESC')->orderBy('bookings.start_time', 'DESC')
                ->limit(30)->get()->getResultArray(),
        ]);
    }

    public function cancelBooking(int $id)
    {
        $booking = $this->db->table('bookings')->where('id', $id)->where('user_id', $this->auth->id())->get()->getRowArray();

        if ($booking === null) {
            return redirect()->to('/account/bookings')->with('error', 'That booking is not yours.');
        }

        // Same cutoff as booking: inside it, the desk has to do it.
        $cutoff = service('siteSettings')->int('booking.cutoff_minutes', 30);
        if (strtotime($booking['booking_date'] . ' ' . $booking['start_time']) < time() + $cutoff * 60) {
            return redirect()->to('/account/bookings')
                ->with('error', "Too close to start time to cancel online. Call the desk on " . setting('facility.phone', '') . '.');
        }

        $ok = service('bookings')->cancel($id, (string) ($this->request->getPost('reason') ?: 'Cancelled by player'), (int) $this->auth->id());

        return redirect()->to('/account/bookings')->with(
            $ok ? 'success' : 'error',
            $ok ? 'Booking ' . $booking['reference'] . ' cancelled. The court is back on the board.' : 'That booking could not be cancelled.'
        );
    }

    private function bookingQuery(int $userId)
    {
        return $this->db->table('bookings')
            ->select('bookings.*, courts.name AS court_name, courts.code AS court_code, courts.slug AS court_slug')
            ->join('courts', 'courts.id = bookings.court_id')
            ->where('bookings.user_id', $userId);
    }
}
