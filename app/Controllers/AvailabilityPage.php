<?php

namespace App\Controllers;

class AvailabilityPage extends BaseController
{
    public function index(): string
    {
        $availability = service('availability');
        $advance      = service('siteSettings')->int('booking.advance_days', 14);

        $today = business_date();
        $date  = (string) ($this->request->getGet('date') ?? $today);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < $today || $date > date('Y-m-d', strtotime("+{$advance} days"))) {
            $date = $today;
        }

        $days = [];
        for ($i = 0; $i <= min($advance, 13); $i++) {
            $d = date('Y-m-d', strtotime("{$today} +{$i} days"));
            $days[] = ['date' => $d, 'label' => day_label($d), 'dow' => date('D', strtotime($d))];
        }

        return $this->page('pages/availability', [
            'title' => 'Live court availability | Go Paddle',
            'meta'  => 'Which of the five courts at Go Paddle in Trento is free, slot by slot, for the next two weeks.',
            'board' => $availability->board($date),
            'date'  => $date,
            'days'  => $days,
        ]);
    }
}
