<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Membership requests and the memberships they become.
 *
 * The public plan page deliberately charges nothing: it writes a `pending` row
 * and tells the player to settle at the desk. Until this screen existed there
 * was no way to finish that, so every request sat pending for ever. Activating
 * here takes the money, dates the term from today and hands over the credits.
 */
class Memberships extends BaseController
{
    private const METHODS = ['cash', 'gcash', 'maya', 'card', 'bank_transfer'];

    public function index(): string
    {
        // A term that ran out is expired whether or not anyone said so. Doing it
        // on read keeps one truth without a scheduled job to forget about.
        $this->db->table('memberships')
            ->where('status', 'active')
            ->where('ends_on <', date('Y-m-d'))
            ->update(['status' => 'expired', 'updated_at' => date('Y-m-d H:i:s')]);

        $status = (string) ($this->request->getGet('status') ?? '');

        $rows = $this->db->table('memberships m')
            ->select('m.*, mp.name AS plan_name, mp.price, mp.billing_period, mp.court_credit_hours,
                      u.first_name, u.last_name, u.email, u.phone,
                      p.reference AS payment_reference, p.method AS payment_method')
            ->join('membership_plans mp', 'mp.id = m.plan_id')
            ->join('users u', 'u.id = m.user_id')
            ->join('payments p', 'p.membership_id = m.id AND p.status = "paid"', 'left');

        if (in_array($status, ['pending', 'active', 'expired', 'cancelled'], true)) {
            $rows->where('m.status', $status);
        }

        $all = $rows->orderBy('FIELD(m.status, "pending", "active", "expired", "cancelled")')
            ->orderBy('m.created_at', 'DESC')->get()->getResultArray();

        return $this->page('admin/memberships', [
            'title'   => 'Memberships | Go Paddle',
            'pending' => array_values(array_filter($all, static fn ($r) => $r['status'] === 'pending')),
            'rows'    => array_values(array_filter($all, static fn ($r) => $r['status'] !== 'pending')),
            'status'  => $status,
            'methods' => self::METHODS,
            'plans'   => $this->db->table('membership_plans')->where('is_active', 1)->orderBy('sort_order')->get()->getResultArray(),
        ], 'layouts/admin');
    }

    public function activate(int $id)
    {
        $method = (string) $this->request->getPost('method');

        $membership = $this->db->table('memberships m')
            ->select('m.*, mp.name AS plan_name, mp.price, mp.billing_period, mp.court_credit_hours')
            ->join('membership_plans mp', 'mp.id = m.plan_id')
            ->where('m.id', $id)->get()->getRowArray();

        if ($membership === null) {
            return redirect()->to('admin/memberships')->with('error', 'No such membership.');
        }
        if ($membership['status'] === 'active') {
            return redirect()->to('admin/memberships')->with('error', $membership['reference'] . ' is already running.');
        }

        $paid = service('payments')->recordMembership($id, $method, (int) $this->auth->id());
        if (! $paid['ok']) {
            return redirect()->to('admin/memberships')->with('error', $paid['error']);
        }

        // The term starts the day it is paid for, not the day it was asked for.
        $months = ['monthly' => 1, 'quarterly' => 3, 'annual' => 12][$membership['billing_period']] ?? 1;
        $start  = date('Y-m-d');

        $this->db->table('memberships')->where('id', $id)->update([
            'status'            => 'active',
            'starts_on'         => $start,
            'ends_on'           => date('Y-m-d', strtotime($start . " +{$months} months")),
            'credits_remaining' => (int) $membership['court_credit_hours'],
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        service('audit')->log('membership.activated', 'membership', $id, $membership['reference'] . ' ' . $membership['plan_name'], $this->auth->id());
        service('bernieNotify')->push(
            (int) $membership['user_id'],
            'membership',
            $membership['plan_name'] . ' is live',
            sprintf('Paid and switched on. %d court credit hours, running to %s.', (int) $membership['court_credit_hours'], date('d M Y', strtotime($start . " +{$months} months"))),
            '/account'
        );

        return redirect()->to('admin/memberships')->with('success', sprintf(
            '%s activated. Took %s, receipt %s.',
            $membership['plan_name'],
            peso($paid['amount']),
            $paid['reference']
        ));
    }

    public function cancel(int $id)
    {
        $row = $this->db->table('memberships')->where('id', $id)->get()->getRowArray();
        if ($row === null || in_array($row['status'], ['cancelled', 'expired'], true)) {
            return redirect()->to('admin/memberships')->with('error', 'Nothing to cancel there.');
        }

        $this->db->table('memberships')->where('id', $id)->update([
            'status'     => 'cancelled',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        service('audit')->log('membership.cancelled', 'membership', $id, $row['reference'], $this->auth->id());

        return redirect()->to('admin/memberships')->with(
            'success',
            $row['reference'] . ' cancelled. Any payment already taken stays on the ledger, refund it there if it is owed back.'
        );
    }

    public function credits(int $id)
    {
        if (! $this->validate(['credits' => 'required|is_natural'])) {
            return redirect()->to('admin/memberships')->with('error', 'Credit hours have to be a whole number.');
        }

        $row = $this->db->table('memberships')->where('id', $id)->get()->getRowArray();
        if ($row === null || $row['status'] !== 'active') {
            return redirect()->to('admin/memberships')->with('error', 'Only a running membership has credits to adjust.');
        }

        $credits = (int) $this->request->getPost('credits');
        $this->db->table('memberships')->where('id', $id)->update([
            'credits_remaining' => $credits,
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
        service('audit')->log('membership.credits', 'membership', $id, $row['reference'] . ' set to ' . $credits . ' hours', $this->auth->id());

        return redirect()->to('admin/memberships')->with('success', $row['reference'] . ' now holds ' . $credits . ' credit hours.');
    }
}
