<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * The player list. Mostly a lookup: the desk is holding a phone number or half
 * a surname and needs the rest, plus whether this person is a member and
 * whether they owe anything.
 *
 * Editing here is limited to the things a receptionist can legitimately correct
 * on the counter, which is contact details and whether the account may sign in.
 * Roles live in Staff and passwords are the account holder's own business.
 */
class Customers extends BaseController
{
    private const SKILLS   = ['beginner', 'intermediate', 'advanced', 'pro'];
    private const STATUSES = ['active', 'inactive', 'suspended'];

    public function index(): string
    {
        $q      = trim((string) $this->request->getGet('q'));
        $status = (string) ($this->request->getGet('status') ?? '');

        $rows = $this->db->table('users u')
            ->select('u.id, u.first_name, u.last_name, u.email, u.phone, u.skill_level, u.status,
                      u.last_login_at, u.created_at,
                      COUNT(DISTINCT b.id) AS bookings,
                      COALESCE(SUM(CASE WHEN b.status <> "cancelled" THEN b.total_amount END), 0) AS spend,
                      MAX(b.booking_date) AS last_played,
                      m.status AS membership_status, mp.name AS plan_name')
            ->join('roles r', 'r.id = u.role_id')
            ->join('bookings b', 'b.user_id = u.id', 'left')
            ->join('memberships m', 'm.user_id = u.id AND m.status = "active"', 'left')
            ->join('membership_plans mp', 'mp.id = m.plan_id', 'left')
            ->where('r.is_staff', 0)
            ->where('u.deleted_at', null);

        if ($q !== '') {
            $rows->groupStart()
                ->like('u.first_name', $q)
                ->orLike('u.last_name', $q)
                ->orLike('u.email', $q)
                ->orLike('u.phone', $q)
                ->orLike('CONCAT(u.first_name, " ", u.last_name)', $q)
                ->groupEnd();
        }
        if (in_array($status, self::STATUSES, true)) {
            $rows->where('u.status', $status);
        }

        return $this->page('admin/customers', [
            'title'    => 'Customers | Bernie Pickleball',
            'rows'     => $rows->groupBy('u.id')->orderBy('u.first_name')->limit(100)->get()->getResultArray(),
            'q'        => $q,
            'status'   => $status,
            'statuses' => self::STATUSES,
            'skills'   => self::SKILLS,
            'viewing'  => $this->profile((int) ($this->request->getGet('id') ?? 0)),
        ], 'layouts/admin');
    }

    public function update(int $id)
    {
        if (! $this->validate([
            'first_name'  => 'required|max_length[60]',
            'last_name'   => 'required|max_length[60]',
            'email'       => 'required|valid_email|max_length[160]',
            'phone'       => 'permit_empty|max_length[30]',
            'skill_level' => 'required|in_list[' . implode(',', self::SKILLS) . ']',
            'status'      => 'required|in_list[' . implode(',', self::STATUSES) . ']',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $user = $this->db->table('users u')->select('u.*, r.is_staff')
            ->join('roles r', 'r.id = u.role_id')
            ->where('u.id', $id)->where('u.deleted_at', null)->get()->getRowArray();

        if ($user === null || (int) $user['is_staff'] === 1) {
            return redirect()->to('admin/customers')->with('error', 'That is not a customer account.');
        }

        $email = strtolower(trim((string) $this->request->getPost('email')));
        $taken = $this->db->table('users')->where('email', $email)->where('id !=', $id)->countAllResults();
        if ($taken > 0) {
            return redirect()->back()->withInput()->with('error', $email . ' belongs to another account.');
        }

        $data = [
            'first_name'  => trim((string) $this->request->getPost('first_name')),
            'last_name'   => trim((string) $this->request->getPost('last_name')),
            'email'       => $email,
            'phone'       => trim((string) $this->request->getPost('phone')) ?: null,
            'skill_level' => (string) $this->request->getPost('skill_level'),
            'status'      => (string) $this->request->getPost('status'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        $this->db->table('users')->where('id', $id)->update($data);
        service('audit')->log('customer.updated', 'user', $id, $data['first_name'] . ' ' . $data['last_name'], $this->auth->id(), $data);

        return redirect()->to('admin/customers?id=' . $id)->with('success', $data['first_name'] . ' updated.');
    }

    /** @return array<string, mixed>|null */
    private function profile(int $id)
    {
        if ($id < 1) {
            return null;
        }

        $user = $this->db->table('users u')->select('u.*, r.is_staff, r.name AS role_name')
            ->join('roles r', 'r.id = u.role_id')
            ->where('u.id', $id)->where('u.deleted_at', null)->get()->getRowArray();

        if ($user === null || (int) $user['is_staff'] === 1) {
            return null;
        }

        $user['bookings'] = $this->db->table('bookings b')
            ->select('b.id, b.reference, b.booking_date, b.start_time, b.status, b.payment_status, b.total_amount, courts.code AS court_code')
            ->join('courts', 'courts.id = b.court_id')
            ->where('b.user_id', $id)
            ->orderBy('b.booking_date', 'DESC')->orderBy('b.start_time', 'DESC')
            ->limit(10)->get()->getResultArray();

        $user['membership'] = $this->db->table('memberships m')
            ->select('m.*, mp.name AS plan_name, mp.price')
            ->join('membership_plans mp', 'mp.id = m.plan_id')
            ->where('m.user_id', $id)
            ->orderBy('m.created_at', 'DESC')->limit(1)->get()->getRowArray();

        $user['owed'] = (float) ($this->db->table('bookings')
            ->selectSum('total_amount', 'owed')
            ->where('user_id', $id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereNotIn('status', ['cancelled'])
            ->get()->getRow('owed') ?? 0);

        return $user;
    }
}
