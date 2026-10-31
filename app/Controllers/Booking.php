<?php

namespace App\Controllers;

use App\Services\Availability;

class Booking extends BaseController
{
    private const PROOF_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const PROOF_MAX_BYTES = 8 * 1024 * 1024;

    public function index(): string
    {
        $availability = service('availability');
        $settings     = service('siteSettings');

        $today   = business_date();
        $maxDate = date('Y-m-d', strtotime('+' . $settings->int('booking.advance_days', 14) . ' days'));

        $date = (string) ($this->request->getGet('date') ?? $today);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < $today || $date > $maxDate) {
            $date = $today;
        }

        $courts   = $this->db->table('courts')->where('deleted_at', null)->where('status', 'active')->orderBy('sort_order')->get()->getResultArray();
        $courtId  = (int) ($this->request->getGet('court') ?? ($courts[0]['id'] ?? 0));
        if (! in_array($courtId, array_map('intval', array_column($courts, 'id')), true)) {
            $courtId = (int) ($courts[0]['id'] ?? 0);
        }

        $duration = (int) ($this->request->getGet('duration') ?? $settings->int('booking.min_minutes', 60));
        $steps    = range($settings->int('booking.min_minutes', 60), $settings->int('booking.max_minutes', 180), $availability->slotMinutes());
        if (! in_array($duration, $steps, true)) {
            $duration = $settings->int('booking.min_minutes', 60);
        }

        return $this->page('pages/booking', [
            'title'   => 'Book a court | Go Paddle',
            'meta'    => 'Pick a court, a time and a duration. Live availability across all ' . courts_word() . ' courts in Trento.',
            'courts'  => $courts,
            'court'   => array_values(array_filter($courts, static fn ($c) => (int) $c['id'] === $courtId))[0] ?? null,
            'courtId' => $courtId,
            'date'    => $date,
            'maxDate' => $maxDate,
            'duration' => $duration,
            'durations' => $steps,
            'slots'   => $availability->startTimesFor($date, $courtId, $duration),
            'selectedStart' => (string) ($this->request->getGet('start') ?? ''),
            'board'   => $availability->board($date),
            'old'     => session()->getFlashdata('old') ?? [],
        ]);
    }

    public function store()
    {
        // The quick-book card sends the players as a list and the first of them
        // is the contact, so guest_name is derived from it. Validated as a data
        // array rather than by mutating the request: setGlobal('post') does not
        // reach the validator, which read guest_name as empty and refused every
        // booking made from the card.
        $post  = $this->request->getPost();
        $names = array_values(array_filter(array_map(
            static fn ($n) => trim((string) $n),
            (array) ($post['player_names'] ?? [])
        ), static fn ($n) => $n !== ''));

        if ($names !== [] && trim((string) ($post['guest_name'] ?? '')) === '') {
            $post['guest_name'] = $names[0];
        }

        $rules = [
            'court_id'       => 'required|is_natural_no_zero',
            'date'           => 'required|valid_date[Y-m-d]',
            'start_time'     => 'required|regex_match[/^([01]\d|2[0-3]):[0-5]\d$/]',
            'duration'       => 'required|is_natural_no_zero',
            'player_count'   => 'required|is_natural_no_zero|less_than_equal_to[8]',
        ];

        if (! $this->auth->check()) {
            $rules['guest_name']  = 'required|min_length[2]|max_length[120]';
            $rules['guest_phone'] = 'required|min_length[7]|max_length[30]';
            $rules['guest_email'] = 'permit_empty|valid_email|max_length[160]';
        }

        if (! $this->validateData($post, $rules)) {
            return redirect()->back()->withInput()
                ->with('error', implode(' ', $this->validator->getErrors()));
        }

        // Online bookings are GCash only. GCash prints the reference in groups,
        // so the spacing is dropped and one receipt reads the same however typed.
        $gcashReference = strtoupper((string) preg_replace('/[\s-]+/', '', (string) ($post['gcash_reference'] ?? '')));
        if (preg_match('/^[A-Z0-9]{6,40}$/', $gcashReference) !== 1) {
            return redirect()->back()->withInput()
                ->with('error', 'Enter the reference number from your GCash receipt.');
        }

        // A rejected booking frees its reference, so a bad screenshot can be resent.
        $used = $this->db->table('bookings')
            ->where('gcash_reference', $gcashReference)
            ->where('status !=', 'cancelled')
            ->countAllResults();
        if ($used > 0) {
            return redirect()->back()->withInput()
                ->with('error', 'That GCash reference is already on another booking.');
        }

        $proof = $this->saveProof();
        if ($proof === null) {
            return redirect()->back()->withInput()
                ->with('error', 'Attach a screenshot of your GCash receipt: JPG, PNG or WebP, up to 8 MB.');
        }

        $equipment = [];
        foreach ((array) $this->request->getPost('equipment') as $id => $qty) {
            if ((int) $qty > 0) {
                $equipment[(int) $id] = (int) $qty;
            }
        }

        $result = service('bookings')->create([
            'court_id'       => (int) $this->request->getPost('court_id'),
            'date'           => (string) $this->request->getPost('date'),
            'start_time'     => (string) $this->request->getPost('start_time'),
            'duration'       => (int) $this->request->getPost('duration'),
            'player_count'   => (int) $this->request->getPost('player_count'),
            'player_names'   => $names,
            'user_id'        => $this->auth->id(),
            'coach_id'       => (int) $this->request->getPost('coach_id') ?: null,
            'equipment'      => $equipment,
            'promo_code'     => trim((string) $this->request->getPost('promo_code')) ?: null,
            'payment_method' => 'gcash',
            'awaiting_approval' => true,
            'gcash_reference'   => $gcashReference,
            'payment_proof'     => $proof,
            'notes'          => trim((string) $this->request->getPost('notes')) ?: null,
            'guest_name'     => $this->auth->check() ? null : trim((string) ($post['guest_name'] ?? '')),
            'guest_phone'    => $this->auth->check() ? null : trim((string) $this->request->getPost('guest_phone')),
            'guest_email'    => $this->auth->check() ? null : (trim((string) $this->request->getPost('guest_email')) ?: null),
        ]);

        if (! $result['ok']) {
            @unlink(WRITEPATH . 'uploads/payment-proofs/' . $proof);

            // The player paid before pressing the button, so the money is out
            // even though no booking exists. Nothing holds their reference.
            return redirect()->to(current_url() . '?' . http_build_query([
                'court'    => (int) $this->request->getPost('court_id'),
                'date'     => (string) $this->request->getPost('date'),
                'duration' => (int) $this->request->getPost('duration'),
            ]))->withInput()->with('error', $result['error'] . ' Already sent the GCash? Book again with the same reference and screenshot.');
        }

        return redirect()->to('booking/confirmed/' . $result['reference']);
    }

    /**
     * Keeps the receipt screenshot under a random name, with the extension taken
     * from what the file really is, so nothing but an image lands on disk.
     *
     * @return string|null the stored file name, or null if the upload is unusable
     */
    private function saveProof(): ?string
    {
        $file = $this->request->getFile('payment_proof');
        if ($file === null || ! $file->isValid() || $file->getSize() > self::PROOF_MAX_BYTES) {
            return null;
        }

        $extension = self::PROOF_TYPES[$file->getMimeType()] ?? null;
        if ($extension === null) {
            return null;
        }

        $name = bin2hex(random_bytes(16)) . '.' . $extension;
        $file->move(WRITEPATH . 'uploads/payment-proofs', $name);

        return $name;
    }

    public function confirmed(string $reference): string
    {
        $booking = $this->db->table('bookings')
            ->select('bookings.*, courts.name AS court_name, courts.code AS court_code, courts.slug AS court_slug')
            ->join('courts', 'courts.id = bookings.court_id')
            ->where('bookings.reference', $reference)
            ->get()->getRowArray();

        if ($booking === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('No booking with that reference.');
        }

        // A reference alone should not expose someone else's contact details.
        $owner = $booking['user_id'] !== null && (int) $booking['user_id'] === $this->auth->id();

        return $this->page('pages/booking_confirmed', [
            'title'   => 'Booking ' . $reference . ' | Go Paddle',
            'booking' => $booking,
            'owner'   => $owner,
            'items'   => $this->db->table('booking_equipment be')
                ->select('be.*, equipment.name')
                ->join('equipment', 'equipment.id = be.equipment_id')
                ->where('be.booking_id', $booking['id'])->get()->getResultArray(),
            'coach' => $booking['coach_id'] === null ? null : $this->db->table('coaches')
                ->select('users.first_name, users.last_name')
                ->join('users', 'users.id = coaches.user_id')
                ->where('coaches.id', $booking['coach_id'])->get()->getRowArray(),
        ]);
    }
}
