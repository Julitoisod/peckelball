<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * The counter booking. Someone is standing there asking what is free now, so
 * the screen answers that question first and asks for their name second.
 *
 * It runs through the same `Bookings::create()` as the public form: same slot
 * pricing, same UNIQUE key on booking_slots deciding who wins a race. The only
 * difference is `at_desk`, which lifts the online cutoff, because that rule
 * exists to stop phone bookings the desk has already given away.
 */
class Walkins extends BaseController
{
    private const MAX_OFFERED = 8;

    public function index(): string
    {
        $settings = service('siteSettings');
        $step     = service('availability')->slotMinutes();
        $min      = $settings->int('booking.min_minutes', 60);
        $max      = $settings->int('booking.max_minutes', 180);

        $duration = (int) ($this->request->getGet('duration') ?: $min);
        if ($duration < $min || $duration > $max || $duration % $step !== 0) {
            $duration = $min;
        }

        $today  = business_date();
        $courts = $this->db->table('courts')->select('id, code, name, surface_type, hourly_rate')
            ->where('deleted_at', null)->where('status', 'active')
            ->orderBy('sort_order')->get()->getResultArray();

        // Only what someone could actually start on now: the board already
        // reports anything earlier as PAST rather than OPEN.
        $offers = [];
        foreach ($courts as $court) {
            $slots = service('availability')->startTimesFor($today, (int) $court['id'], $duration);
            if ($slots !== []) {
                $offers[] = ['court' => $court, 'slots' => array_slice($slots, 0, self::MAX_OFFERED)];
            }
        }

        return $this->page('admin/walkins', [
            'title'    => 'Walk-ins | Go Paddle',
            'offers'   => $offers,
            'duration' => $duration,
            'durations' => range($min, $max, $step),
            'today'    => $today,
            'methods'  => ['cash', 'gcash', 'maya', 'card'],
            'recent'   => $this->db->table('bookings b')
                ->select('b.id, b.reference, b.start_time, b.end_time, b.total_amount, b.payment_status, b.status, b.guest_name, courts.code AS court_code')
                ->join('courts', 'courts.id = b.court_id')
                ->where('b.booking_date', $today)
                ->where('b.booking_type', 'walk_in')
                ->orderBy('b.created_at', 'DESC')->limit(8)->get()->getResultArray(),
        ], 'layouts/admin');
    }

    public function create()
    {
        if (! $this->validate([
            'slot'         => 'required|regex_match[/^\d+\|([01]\d|2[0-3]):[0-5]\d$/]',
            'duration'     => 'required|is_natural_no_zero',
            'guest_name'   => 'required|max_length[120]',
            'guest_phone'  => 'permit_empty|max_length[30]',
            'player_count' => 'required|is_natural_no_zero|less_than_equal_to[8]',
            'method'       => 'required|in_list[cash,gcash,maya,card]',
        ], [
            'slot'       => ['required' => 'Pick a court and a play time.', 'regex_match' => 'That play time is not one of the offered slots.'],
            'guest_name' => ['required' => 'A name is needed, even if it is only a first name.'],
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        [$courtId, $start] = explode('|', (string) $this->request->getPost('slot'));
        $paidNow = (bool) $this->request->getPost('paid_now');
        $method  = (string) $this->request->getPost('method');

        $result = service('bookings')->create([
            'court_id'     => (int) $courtId,
            'date'         => business_date(),
            'start_time'   => $start,
            'duration'     => (int) $this->request->getPost('duration'),
            'player_count' => (int) $this->request->getPost('player_count'),
            'guest_name'   => trim((string) $this->request->getPost('guest_name')),
            'guest_phone'  => trim((string) $this->request->getPost('guest_phone')),
            'notes'        => trim((string) $this->request->getPost('notes')),
            'booking_type' => 'walk_in',
            // Cash is recorded as taken only when the desk says it was. Every
            // other method goes through a terminal that has already collected.
            'payment_method' => $method,
            'created_by'   => $this->auth->id(),
            'at_desk'      => true,
        ]);

        if (! $result['ok']) {
            return redirect()->back()->withInput()->with('error', $result['error']);
        }

        // create() only opens a payment row for the non-cash methods it treats
        // as already settled. Cash handed over at the counter is settled too,
        // and the desk ticking the box is the only record of that.
        if ($method === 'cash' && $paidNow) {
            service('payments')->record((int) $result['id'], 'cash', null, (int) $this->auth->id(), 'Taken at the desk with the walk-in.');
        }

        return redirect()->to('admin/walkins')->with('success', sprintf(
            '%s booked on %s at %s. Reference %s.',
            trim((string) $this->request->getPost('guest_name')),
            $this->courtCode((int) $courtId),
            clock($start),
            $result['reference']
        ));
    }

    private function courtCode(int $courtId): string
    {
        return (string) ($this->db->table('courts')->select('code')->where('id', $courtId)->get()->getRowArray()['code'] ?? 'the court');
    }
}
