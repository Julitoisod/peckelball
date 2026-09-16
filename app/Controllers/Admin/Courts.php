<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Courts extends BaseController
{
    /** `surface_type` is where the court sits; `surface` is free text for what it is made of. */
    private const SURFACES = ['indoor', 'covered', 'outdoor'];
    private const STATES   = ['active', 'maintenance', 'closed'];

    public function index(): string
    {
        $today = business_date();
        $board = service('availability')->board($today);

        $revenue = array_column(
            $this->db->table('bookings')
                ->select('court_id, SUM(total_amount) AS total, COUNT(*) AS n')
                ->where('booking_date >=', date('Y-m-01'))
                ->whereNotIn('status', ['cancelled'])
                ->groupBy('court_id')->get()->getResultArray(),
            null,
            'court_id'
        );

        return $this->page('admin/courts', [
            'title'   => 'Court management | Bernie Pickleball',
            'courts'  => $board['courts'],
            'revenue' => $revenue,
            'maintenance' => $this->db->table('court_maintenance cm')
                ->select('cm.*, courts.code AS court_code, u.first_name, u.last_name')
                ->join('courts', 'courts.id = cm.court_id')
                ->join('users u', 'u.id = cm.assigned_to', 'left')
                ->orderBy('cm.scheduled_start', 'DESC')->limit(12)->get()->getResultArray(),
            // `courts.manage` gated nothing until now: rates and status could
            // only be changed in SQL. The editor renders only for holders.
            'canManage' => $this->auth->can('courts.manage'),
            'editing'   => $this->request->getGet('edit') !== null
                ? $this->db->table('courts')->where('id', (int) $this->request->getGet('edit'))
                    ->where('deleted_at', null)->get()->getRowArray()
                : null,
            'surfaces'  => self::SURFACES,
            'states'    => self::STATES,
        ], 'layouts/admin');
    }

    public function update(int $id)
    {
        if (! $this->validate([
            'name'         => 'required|max_length[120]',
            'surface_type' => 'required|in_list[' . implode(',', self::SURFACES) . ']',
            'surface'      => 'permit_empty|max_length[120]',
            'status'       => 'required|in_list[' . implode(',', self::STATES) . ']',
            'hourly_rate'  => 'required|numeric|greater_than[0]',
            'peak_rate'    => 'required|numeric|greater_than[0]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $court = $this->db->table('courts')->where('id', $id)->where('deleted_at', null)->get()->getRowArray();
        if ($court === null) {
            return redirect()->to('admin/courts')->with('error', 'No such court.');
        }

        $hourly = (float) $this->request->getPost('hourly_rate');
        $peak   = (float) $this->request->getPost('peak_rate');
        if ($peak < $hourly) {
            return redirect()->back()->withInput()->with('error', 'The peak rate cannot be lower than the off-peak rate.');
        }

        $data = [
            'name'         => trim((string) $this->request->getPost('name')),
            'surface_type' => (string) $this->request->getPost('surface_type'),
            'surface'      => trim((string) $this->request->getPost('surface')) ?: null,
            'status'       => (string) $this->request->getPost('status'),
            'hourly_rate'  => $hourly,
            'peak_rate'    => $peak,
            'updated_at'   => date('Y-m-d H:i:s'),
        ];

        $this->db->table('courts')->where('id', $id)->update($data);

        service('audit')->log('court.updated', 'court', $id, sprintf(
            '%s %s, %s, %s off-peak and %s peak',
            $court['code'],
            $data['name'],
            $data['status'],
            peso($hourly),
            peso($peak)
        ), $this->auth->id(), $data);

        // Taking a court out of service is not a quiet change: the board stops
        // offering it from the next request onward.
        $note = $data['status'] === 'active'
            ? ''
            : ' It is off the public board while it is ' . $data['status'] . '.';

        return redirect()->to('admin/courts')->with('success', $court['code'] . ' updated.' . $note);
    }
}
