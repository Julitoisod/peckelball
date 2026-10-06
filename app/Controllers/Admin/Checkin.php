<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Arrivals desk.
 *
 * The input takes either the 32-character `qr_token` a scanner types or the
 * short reference a player reads off their phone, because both end up as
 * keystrokes in the same box. The write itself goes through
 * `Bookings::checkIn()` so there is one place that moves a booking to
 * checked_in, but the reason a check-in is refused is worked out here: the
 * service returns null for every failure, and "already on court" and "no such
 * code" need different answers at a counter.
 */
class Checkin extends BaseController
{
    public function index(): string
    {
        $today = business_date();

        $rows = $this->db->table('bookings b')
            ->select('b.id, b.reference, b.start_time, b.end_time, b.status, b.payment_status, b.total_amount,
                      b.player_count, b.player_names, b.checked_in_at, b.guest_name, b.guest_phone,
                      courts.code AS court_code, courts.name AS court_name,
                      u.first_name, u.last_name, u.phone')
            ->join('courts', 'courts.id = b.court_id')
            ->join('users u', 'u.id = b.user_id', 'left')
            ->where('b.booking_date', $today)
            ->whereNotIn('b.status', ['cancelled'])
            ->orderBy('b.start_time')
            ->get()->getResultArray();

        return $this->page('admin/checkin', [
            'title'    => 'QR check-in | Go Paddle',
            'expected' => array_values(array_filter($rows, static fn ($r) => $r['status'] !== 'checked_in')),
            'arrived'  => array_values(array_filter($rows, static fn ($r) => $r['status'] === 'checked_in')),
            'found'    => session()->getFlashdata('checkinFound'),
        ], 'layouts/admin');
    }

    public function scan()
    {
        $code = trim((string) $this->request->getPost('code'));
        if ($code === '') {
            return redirect()->to('admin/checkin')->with('error', 'Scan a code or type a reference.');
        }

        $booking = $this->find($code);
        if ($booking === null) {
            // Not escaped here: the layout escapes flash text, and doing it
            // twice prints &quot; at the desk.
            return redirect()->to('admin/checkin')->with('error', 'Nothing matches "' . $code . '". Check the reference on their phone.');
        }

        return $this->admit($booking);
    }

    public function byId(int $id)
    {
        $booking = $this->db->table('bookings b')
            ->select('b.*, courts.code AS court_code, courts.name AS court_name')
            ->join('courts', 'courts.id = b.court_id')
            ->where('b.id', $id)->get()->getRowArray();

        if ($booking === null) {
            return redirect()->to('admin/checkin')->with('error', 'No such booking.');
        }

        return $this->admit($booking);
    }

    /** @param array<string, mixed> $booking */
    private function admit(array $booking)
    {
        // Every refusal below is a thing that actually happens at a counter, and
        // each one needs its own sentence: "that did not work" sends the player
        // and the receptionist round in circles.
        if ($booking['status'] === 'checked_in') {
            return redirect()->to('admin/checkin')->with('error', sprintf(
                '%s is already on %s, checked in at %s.',
                $booking['reference'],
                $booking['court_code'],
                clock(date('H:i', strtotime((string) $booking['checked_in_at'])))
            ));
        }

        if (in_array($booking['status'], ['cancelled', 'no_show'], true)) {
            return redirect()->to('admin/checkin')->with('error', $booking['reference'] . ' was ' . $booking['status'] . '. Book them in as a walk-in instead.');
        }

        if ($booking['status'] === 'completed') {
            return redirect()->to('admin/checkin')->with('error', $booking['reference'] . ' is a finished session.');
        }

        if ($booking['booking_date'] !== business_date()) {
            return redirect()->to('admin/checkin')->with('error', sprintf(
                '%s is for %s, not today. Nothing was changed.',
                $booking['reference'],
                day_label((string) $booking['booking_date'])
            ));
        }

        $result = service('bookings')->checkIn((string) $booking['qr_token']);
        if ($result === null) {
            return redirect()->to('admin/checkin')->with('error', 'That booking could not be checked in.');
        }

        $due = service('payments')->outstanding((int) $booking['id']);

        return redirect()->to('admin/checkin')
            ->with('checkinFound', [
                'reference' => $booking['reference'],
                'court'     => $booking['court_code'] . ' ' . $booking['court_name'],
                'time'      => clock((string) $booking['start_time']) . ' to ' . clock((string) $booking['end_time']),
                'who'       => $booking['guest_name'] ?: 'Account holder',
                'players'   => (int) $booking['player_count'],
                'due'       => $due,
                'id'        => (int) $booking['id'],
            ])
            ->with('success', $booking['reference'] . ' checked in on ' . $booking['court_code'] . '.');
    }

    /** @return array<string, mixed>|null */
    private function find(string $code)
    {
        $query = $this->db->table('bookings b')
            ->select('b.*, courts.code AS court_code, courts.name AS court_name')
            ->join('courts', 'courts.id = b.court_id');

        // A scanner types the token; a person reads the reference aloud. Match
        // the token exactly, and the reference without caring about case.
        return preg_match('/^[a-f0-9]{32}$/i', $code) === 1
            ? $query->where('b.qr_token', strtolower($code))->get()->getRowArray()
            : $query->where('UPPER(b.reference)', strtoupper($code))->get()->getRowArray();
    }
}
