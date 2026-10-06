<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        $availability = service('availability');
        $today        = business_date();
        $live         = $availability->board($today);

        // Once the gate is shut for the night, today's board is a wall of grey
        // and the only day anyone can still book is tomorrow, so the board
        // section rolls over. Before opening time it is still today's board
        // that matters, which is why this asks the clock and not facility_open.
        $rolled    = time() >= $availability->closesAt($today);
        $boardDate = $rolled ? date('Y-m-d', strtotime($today . ' +1 day')) : $today;

        // Live status (the hero panel, the facility map, the 3D scene) always
        // resolves against right now. Only the board looks ahead.
        $board = $rolled ? $availability->board($boardDate) : $live;

        return $this->page('pages/home', [
            'title'    => 'Go Paddle | ' . courts_word() . ' courts in Trento, booked live',
            'meta'     => 'See which of our ' . courts_word() . ' pickleball courts is free right now in Trento, Agusan del Sur, and book it in under a minute. Coaching, tournaments, rentals and membership.',
            'board'     => $board,
            'boardDate' => $boardDate,
            'snapshot'  => $availability->snapshot(),
            'courts'    => $live['courts'],
            'plans'    => $this->db->table('membership_plans')->where('is_active', 1)->orderBy('sort_order')->get()->getResultArray(),
            'coaches'  => $this->db->table('coaches')
                ->select('coaches.*, users.first_name, users.last_name')
                ->join('users', 'users.id = coaches.user_id')
                ->where('coaches.deleted_at', null)
                ->where('coaches.is_accepting', 1)
                ->orderBy('coaches.sort_order')
                ->limit(4)->get()->getResultArray(),
            'tournaments' => $this->db->table('tournaments')
                ->whereIn('status', ['open', 'ongoing'])
                ->where('ends_on >=', $today)
                ->orderBy('starts_on')
                ->limit(2)->get()->getResultArray(),
            'testimonials' => $this->db->table('testimonials')->where('is_published', 1)->orderBy('sort_order')->limit(3)->get()->getResultArray(),
            'notice' => $this->db->table('announcements')
                ->where('audience', 'public')
                ->where('published_at <=', date('Y-m-d H:i:s'))
                ->groupStart()->where('expires_at >=', date('Y-m-d H:i:s'))->orWhere('expires_at', null)->groupEnd()
                ->orderBy('is_pinned', 'DESC')->orderBy('published_at', 'DESC')
                ->get()->getRowArray(),
            'stats' => [
                'played_this_month' => $this->db->table('bookings')
                    ->where('booking_date >=', date('Y-m-01'))
                    ->whereIn('status', ['completed', 'checked_in'])->countAllResults(),
                'members'  => $this->db->table('memberships')->where('status', 'active')->countAllResults(),
                'sessions' => (int) ($this->db->table('coaches')->selectSum('sessions_taught', 't')->get()->getRow('t') ?? 0),
            ],
        ]);
    }
}
