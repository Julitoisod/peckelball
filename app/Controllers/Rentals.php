<?php

namespace App\Controllers;

class Rentals extends BaseController
{
    public function index(): string
    {
        return $this->page('pages/rentals', [
            'title' => 'Equipment rental | Bernie Pickleball',
            'meta'  => 'Paddles, balls, court shoes and portable nets to rent by the hour at Bernie Pickleball, Trento. Add them to any booking.',
            'equipment' => $this->db->table('equipment')->where('is_active', 1)->orderBy('category')->orderBy('name')->get()->getResultArray(),
            'onLoan' => (int) ($this->db->table('booking_equipment be')
                ->join('bookings b', 'b.id = be.booking_id')
                ->where('b.booking_date', business_date())
                ->whereIn('b.status', ['confirmed', 'checked_in'])
                ->selectSum('be.qty', 't')->get()->getRow('t') ?? 0),
        ]);
    }
}
