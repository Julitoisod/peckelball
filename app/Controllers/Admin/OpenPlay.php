<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Open play from the desk: schedule a window on a court, switch it off again,
 * and keep the sign-up sheet.
 *
 * The form posts plain selects rather than querying availability in the
 * browser — the service checks the hours are free and hands back a sentence
 * saying so if they are not, which is the same answer a live picker would
 * have given a moment later anyway.
 */
class OpenPlay extends BaseController
{
    public function index(): string
    {
        $openPlay = service('openPlay');
        $today    = business_date();
        $sessions = $openPlay->listing($today);

        $sheets = [];
        foreach ($sessions as $session) {
            $sheets[(int) $session['id']] = $openPlay->participants((int) $session['id']);
        }

        $step = service('availability')->slotMinutes();

        return $this->page('admin/open_play', [
            'title'    => 'Open play | Go Paddle',
            'sessions' => $sessions,
            'sheets'   => $sheets,
            'courts'   => $this->db->table('courts')->select('id, code, name')
                ->where('deleted_at', null)->where('status', 'active')
                ->orderBy('sort_order')->get()->getResultArray(),
            'times'    => service('availability')->slotTimes(),
            'lengths'  => range($step, service('siteSettings')->int('booking.max_minutes', 180), $step),
            'today'    => $today,
            'maxDate'  => date('Y-m-d', strtotime($today . ' +' . service('siteSettings')->int('booking.advance_days', 14) . ' days')),
            'past'     => $openPlay->listing(date('Y-m-d', strtotime($today . ' -30 days'))),
        ], 'layouts/admin');
    }

    public function open()
    {
        if (! $this->validate([
            'court_id'   => 'required|is_natural_no_zero',
            'date'       => 'required|valid_date[Y-m-d]',
            'start_time' => 'required|regex_match[/^([01]\d|2[0-3]):[0-5]\d$/]',
            'duration'   => 'required|is_natural_no_zero',
            'fee'        => 'required|numeric|greater_than_equal_to[0]',
            'capacity'   => 'required|is_natural_no_zero|less_than_equal_to[64]',
            'title'      => 'permit_empty|max_length[120]',
            'notes'      => 'permit_empty|max_length[255]',
        ], [
            'capacity' => ['less_than_equal_to' => 'Sixty-four places is the most one session can hold.'],
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $result = service('openPlay')->open([
            'court_id'   => (int) $this->request->getPost('court_id'),
            'date'       => (string) $this->request->getPost('date'),
            'start_time' => (string) $this->request->getPost('start_time'),
            'duration'   => (int) $this->request->getPost('duration'),
            'fee'        => (float) $this->request->getPost('fee'),
            'capacity'   => (int) $this->request->getPost('capacity'),
            'title'      => trim((string) $this->request->getPost('title')) ?: null,
            'notes'      => trim((string) $this->request->getPost('notes')) ?: null,
            'created_by' => $this->auth->id(),
        ]);

        if (! $result['ok']) {
            return redirect()->back()->withInput()->with('error', $result['error']);
        }

        return redirect()->to('admin/open-play')->with('success', 'Open play is on. The court is held for it.');
    }

    public function close(int $id)
    {
        return service('openPlay')->close($id, $this->auth->id())
            ? redirect()->to('admin/open-play')->with('success', 'Open play is off and the court is free again.')
            : redirect()->to('admin/open-play')->with('error', 'That session was already off.');
    }

    public function reopen(int $id)
    {
        $result = service('openPlay')->reopen($id, $this->auth->id());

        return $result['ok']
            ? redirect()->to('admin/open-play')->with('success', 'Open play is back on.')
            : redirect()->to('admin/open-play')->with('error', $result['error']);
    }

    public function register(int $id)
    {
        if (! $this->validate([
            'name'           => 'required|max_length[120]',
            'phone'          => 'permit_empty|max_length[30]',
            'payment_method' => 'required|in_list[cash,gcash]',
        ], [
            'name' => ['required' => 'A name is needed, even if it is only a first name.'],
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $result = service('openPlay')->register($id, [
            'name'           => (string) $this->request->getPost('name'),
            'phone'          => trim((string) $this->request->getPost('phone')),
            'payment_method' => (string) $this->request->getPost('payment_method'),
            'paid'           => (bool) $this->request->getPost('paid'),
            'created_by'     => $this->auth->id(),
        ]);

        return $result['ok']
            ? redirect()->to('admin/open-play')->with('success', $this->request->getPost('name') . ' is on the sheet.')
            : redirect()->to('admin/open-play')->with('error', $result['error']);
    }

    public function paid(int $participantId)
    {
        return service('openPlay')->markPaid($participantId, $this->auth->id())
            ? redirect()->to('admin/open-play')->with('success', 'Marked as paid.')
            : redirect()->to('admin/open-play')->with('error', 'That one was already paid.');
    }

    public function remove(int $participantId)
    {
        return service('openPlay')->removeParticipant($participantId, $this->auth->id())
            ? redirect()->to('admin/open-play')->with('success', 'Taken off the sheet.')
            : redirect()->to('admin/open-play')->with('error', 'Nothing to remove.');
    }
}
