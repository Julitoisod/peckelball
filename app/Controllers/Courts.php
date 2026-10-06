<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

class Courts extends BaseController
{
    public function index(): string
    {
        $board = service('availability')->board(business_date());

        // board() deliberately selects a lean column set; this page also needs
        // the prose fields, so merge the full rows onto the live state.
        $full = array_column(
            $this->db->table('courts')->where('deleted_at', null)->orderBy('sort_order')->get()->getResultArray(),
            null,
            'id'
        );
        $courts = array_map(
            static fn (array $c): array => ($full[$c['id']] ?? []) + $c,
            $board['courts']
        );

        return $this->page('pages/courts', [
            'title'  => 'The courts | Go Paddle, Trento',
            'meta'   => 'Three covered and two outdoor pickleball courts in Trento, Agusan del Sur. Surfaces, rates, lighting and live availability.',
            'courts' => $courts,
            'board'  => $board,
        ]);
    }

    public function show(string $slug): string
    {
        $court = $this->db->table('courts')->where('slug', $slug)->where('deleted_at', null)->get()->getRowArray();
        if ($court === null) {
            throw PageNotFoundException::forPageNotFound('No court by that name.');
        }

        $date  = business_date();
        $board = service('availability')->board($date, [(int) $court['id']]);
        $live  = $board['courts'][0] ?? null;

        return $this->page('pages/court_detail', [
            'title' => $court['name'] . ' | Go Paddle',
            'meta'  => mb_strimwidth((string) $court['description'], 0, 155, '…'),
            'court' => $court,
            'live'  => $live,
            'board' => $board,
            'hours' => $this->db->table('court_schedules')->where('court_id', $court['id'])->orderBy('day_of_week')->get()->getResultArray(),
            'maintenance' => $this->db->table('court_maintenance')
                ->where('court_id', $court['id'])
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->where('scheduled_end >=', date('Y-m-d H:i:s'))
                ->orderBy('scheduled_start')->get()->getResultArray(),
            'upcoming' => $this->db->table('bookings')
                ->select('booking_date, start_time, end_time, booking_type')
                ->where('court_id', $court['id'])
                ->where('booking_date >=', $date)
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->orderBy('booking_date')->orderBy('start_time')
                ->limit(6)->get()->getResultArray(),
        ]);
    }
}
