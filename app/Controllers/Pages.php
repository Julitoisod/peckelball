<?php

namespace App\Controllers;

class Pages extends BaseController
{
    public function about(): string
    {
        return $this->page('pages/about', [
            'title' => 'About Go Paddle | Trento, Agusan del Sur',
            'meta'  => 'How ' . courts_word() . ' pickleball courts ended up on the national highway in Trento, and who runs them.',
            'courts' => $this->db->table('courts')->where('deleted_at', null)->orderBy('sort_order')->get()->getResultArray(),
            'coaches' => $this->db->table('coaches')
                ->select('coaches.*, users.first_name, users.last_name')
                ->join('users', 'users.id = coaches.user_id')
                ->where('coaches.deleted_at', null)->orderBy('coaches.sort_order')->get()->getResultArray(),
            'stats' => [
                'bookings' => $this->db->table('bookings')->whereIn('status', ['completed', 'checked_in'])->countAllResults(),
                'members'  => $this->db->table('memberships')->where('status', 'active')->countAllResults(),
                'coaching' => (int) ($this->db->table('coaches')->selectSum('sessions_taught', 't')->get()->getRow('t') ?? 0),
            ],
        ]);
    }

    public function contact(): string
    {
        return $this->page('pages/contact', [
            'title' => 'Contact & directions | Go Paddle',
            'meta'  => 'Find Go Paddle on the national highway in Trento, Agusan del Sur. Phone, email and opening hours.',
            'hours' => $this->db->table('court_schedules')
                ->select('day_of_week, MIN(opens_at) AS opens_at, MAX(closes_at) AS closes_at')
                ->groupBy('day_of_week')->orderBy('day_of_week')->get()->getResultArray(),
        ]);
    }

    public function submitContact()
    {
        if (! $this->validate([
            'name'    => 'required|min_length[2]|max_length[120]',
            'email'   => 'required|valid_email|max_length[160]',
            'message' => 'required|min_length[10]|max_length[2000]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        // Enquiries land in the dashboard as a staff notification rather than an
        // email queue this facility would have to run.
        service('bernieNotify')->push(
            null,
            'system',
            'Enquiry from ' . $this->request->getPost('name'),
            mb_strimwidth((string) $this->request->getPost('message'), 0, 400, '…') . ' — reply to ' . $this->request->getPost('email'),
            '/admin'
        );

        service('audit')->log('contact.submitted', null, null, (string) $this->request->getPost('email'));

        return redirect()->to('/contact')->with('success', 'Message received. We answer within a day, sooner if the desk is quiet.');
    }

    /**
     * The Android app: a download, not a store listing.
     *
     * The size is read off the file rather than typed, so the page cannot
     * promise a build that is not sitting there.
     */
    public function app(): string
    {
        $apk = FCPATH . 'downloads/go-paddle.apk';

        return $this->page('pages/app', [
            'title' => 'Android app | Go Paddle',
            'meta'  => 'Install Go Paddle on an Android phone. Same courts, same bookings, one tap from the home screen.',
            'size'  => is_file($apk) ? round(filesize($apk) / 1048576, 1) . ' MB' : null,
            'built' => is_file($apk) ? date('j M Y', filemtime($apk)) : null,
        ]);
    }

    public function offline(): string
    {
        return $this->page('pages/offline', [
            'title' => 'Offline | Go Paddle',
            'meta'  => 'You are offline.',
        ]);
    }
}
