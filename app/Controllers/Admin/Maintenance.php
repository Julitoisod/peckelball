<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Court maintenance windows.
 *
 * These are not notes. `Availability::maintenanceWindows()` reads every window
 * in `scheduled` or `in_progress` and takes those cells off the public board,
 * so saving one here closes a court for real. That is why an overlap with
 * bookings that are already confirmed is reported back instead of being
 * silently created: closing the court is allowed, not noticing is not.
 */
class Maintenance extends BaseController
{
    private const TYPES    = ['routine', 'repair', 'resurfacing', 'inspection', 'cleaning'];
    private const STATUSES = ['scheduled', 'in_progress', 'completed', 'cancelled'];

    public function index(): string
    {
        $editId = (int) ($this->request->getGet('edit') ?? 0);

        return $this->page('admin/maintenance', [
            'title' => 'Maintenance | Go Paddle',
            'rows'  => $this->db->table('court_maintenance cm')
                ->select('cm.*, courts.code AS court_code, courts.name AS court_name, u.first_name, u.last_name')
                ->join('courts', 'courts.id = cm.court_id')
                ->join('users u', 'u.id = cm.assigned_to', 'left')
                ->orderBy('cm.scheduled_start', 'DESC')
                ->get()->getResultArray(),
            'courts' => $this->db->table('courts')->select('id, code, name')
                ->where('deleted_at', null)->orderBy('sort_order')->get()->getResultArray(),
            'staff' => $this->db->table('users u')->select('u.id, u.first_name, u.last_name, roles.name AS role_name')
                ->join('roles', 'roles.id = u.role_id')
                ->where('roles.is_staff', 1)
                ->where('u.deleted_at', null)
                ->orderBy('u.first_name')->get()->getResultArray(),
            'editing' => $editId > 0
                ? $this->db->table('court_maintenance')->where('id', $editId)->get()->getRowArray()
                : null,
            'types'    => self::TYPES,
            'statuses' => self::STATUSES,
        ], 'layouts/admin');
    }

    public function save()
    {
        if (! $this->validate([
            'court_id'    => 'required|is_natural_no_zero',
            'title'       => 'required|max_length[160]',
            'description' => 'permit_empty|max_length[2000]',
            'type'        => 'required|in_list[' . implode(',', self::TYPES) . ']',
            'status'      => 'required|in_list[' . implode(',', self::STATUSES) . ']',
            'assigned_to' => 'permit_empty|is_natural',
            'cost'        => 'permit_empty|numeric|greater_than_equal_to[0]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $courtId = (int) $this->request->getPost('court_id');
        $court   = $this->db->table('courts')->select('id, code, name')
            ->where('id', $courtId)->where('deleted_at', null)->get()->getRowArray();

        if ($court === null) {
            return redirect()->back()->withInput()->with('error', 'That court is not on the books.');
        }

        $start = $this->toDateTime((string) $this->request->getPost('scheduled_start'));
        $end   = $this->toDateTime((string) $this->request->getPost('scheduled_end'));

        if ($start === null || $end === null) {
            return redirect()->back()->withInput()->with('error', 'Both the start and the end of the window are needed.');
        }
        if ($end <= $start) {
            return redirect()->back()->withInput()->with('error', 'The window has to end after it starts.');
        }

        $assigned = (int) ($this->request->getPost('assigned_to') ?: 0);
        if ($assigned > 0) {
            $isStaff = $this->db->table('users u')->join('roles', 'roles.id = u.role_id')
                ->where('u.id', $assigned)->where('roles.is_staff', 1)->where('u.deleted_at', null)->countAllResults();
            if ($isStaff === 0) {
                return redirect()->back()->withInput()->with('error', 'Maintenance can only be assigned to staff.');
            }
        }

        $data = [
            'court_id'        => $courtId,
            'title'           => (string) $this->request->getPost('title'),
            'description'     => (string) $this->request->getPost('description'),
            'type'            => (string) $this->request->getPost('type'),
            'scheduled_start' => $start,
            'scheduled_end'   => $end,
            'status'          => (string) $this->request->getPost('status'),
            'assigned_to'     => $assigned > 0 ? $assigned : null,
            'cost'            => (float) ($this->request->getPost('cost') ?: 0),
            'updated_at'      => date('Y-m-d H:i:s'),
        ];

        $id      = (int) ($this->request->getPost('id') ?? 0);
        $editing = $id > 0 ? $this->db->table('court_maintenance')->where('id', $id)->get()->getRowArray() : null;

        if ($editing !== null) {
            $this->db->table('court_maintenance')->where('id', $id)->update($data);
            service('audit')->log('maintenance.updated', 'court_maintenance', $id, $court['code'] . ' ' . $data['title'], $this->auth->id(), $data);
            $message = 'Window updated.';
        } else {
            $data['created_by'] = $this->auth->id();
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->table('court_maintenance')->insert($data);
            $id = (int) $this->db->insertID();
            service('audit')->log('maintenance.created', 'court_maintenance', $id, $court['code'] . ' ' . $data['title'], $this->auth->id(), $data);
            $message = $court['code'] . ' is closed for ' . $data['title'] . '.';
        }

        $clash = $this->bookingsInWindow($courtId, $start, $end);
        if ($clash > 0 && in_array($data['status'], ['scheduled', 'in_progress'], true)) {
            $message .= ' Warning: ' . $clash . ' booking' . ($clash === 1 ? '' : 's')
                . ' already sit inside that window. Call ' . ($clash === 1 ? 'them' : 'them all') . ' before the day.';
        }

        return redirect()->to('admin/maintenance')->with($clash > 0 ? 'error' : 'success', $message);
    }

    public function status(int $id)
    {
        $to  = (string) $this->request->getPost('to');
        $row = $this->db->table('court_maintenance cm')->select('cm.*, courts.code AS court_code')
            ->join('courts', 'courts.id = cm.court_id')->where('cm.id', $id)->get()->getRowArray();

        if ($row === null || ! in_array($to, self::STATUSES, true)) {
            return redirect()->to('admin/maintenance')->with('error', 'That is not a change I can make.');
        }

        $this->db->table('court_maintenance')->where('id', $id)->update([
            'status'     => $to,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        service('audit')->log('maintenance.status', 'court_maintenance', $id, $row['court_code'] . ' ' . $row['title'] . ' to ' . $to, $this->auth->id());

        // Completing or cancelling hands the slots back to the board.
        $freed = in_array($to, ['completed', 'cancelled'], true) && in_array($row['status'], ['scheduled', 'in_progress'], true);

        return redirect()->to('admin/maintenance')->with(
            'success',
            $row['court_code'] . ' marked ' . str_replace('_', ' ', $to) . '.' . ($freed ? ' Those slots are bookable again.' : '')
        );
    }

    private function bookingsInWindow(int $courtId, string $start, string $end): int
    {
        return $this->db->table('bookings')
            ->where('court_id', $courtId)
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->where('booking_date >=', date('Y-m-d', strtotime($start)))
            ->where('booking_date <=', date('Y-m-d', strtotime($end)))
            ->where("CONCAT(booking_date, ' ', start_time) <", $end)
            ->where("CONCAT(booking_date, ' ', end_time) >", $start)
            ->countAllResults();
    }

    private function toDateTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $stamp = strtotime($value);

        return $stamp === false ? null : date('Y-m-d H:i:s', $stamp);
    }
}
