<?php

namespace App\Controllers;

class Membership extends BaseController
{
    public function index(): string
    {
        return $this->page('pages/membership', [
            'title' => 'Membership plans | Go Paddle',
            'meta'  => 'Court credits, standing discounts and earlier booking windows. Four membership plans at Go Paddle, Trento.',
            'plans' => $this->db->table('membership_plans')->where('is_active', 1)->orderBy('sort_order')->get()->getResultArray(),
            'current' => $this->auth->check()
                ? $this->db->table('memberships m')
                    ->select('m.*, membership_plans.name AS plan_name')
                    ->join('membership_plans', 'membership_plans.id = m.plan_id')
                    ->where('m.user_id', $this->auth->id())->where('m.status', 'active')
                    ->get()->getRowArray()
                : null,
            'memberCount' => $this->db->table('memberships')->where('status', 'active')->countAllResults(),
        ]);
    }

    public function join()
    {
        if (! $this->auth->check()) {
            return redirect()->to('/register')->with('error', 'Create an account first. A membership needs somewhere to live.');
        }

        $plan = $this->db->table('membership_plans')
            ->where('id', (int) $this->request->getPost('plan_id'))->where('is_active', 1)
            ->get()->getRowArray();

        if ($plan === null) {
            return redirect()->back()->with('error', 'That plan is not available.');
        }

        $userId = (int) $this->auth->id();

        // Pending counts too: the row this inserts is pending, so checking only
        // for active let a double submit stack two requests on the desk.
        $existing = $this->db->table('memberships')->where('user_id', $userId)
            ->whereIn('status', ['active', 'pending'])->get()->getRowArray();

        if ($existing !== null) {
            return redirect()->to('/account')->with('error', $existing['status'] === 'active'
                ? 'You already have an active membership. Talk to the desk about switching plans.'
                : 'You already have a membership request waiting at the desk.');
        }

        $months = ['monthly' => 1, 'quarterly' => 3, 'annual' => 12][$plan['billing_period']] ?? 1;
        $start  = date('Y-m-d');

        $this->db->table('memberships')->insert([
            'user_id'   => $userId,
            'plan_id'   => (int) $plan['id'],
            'reference' => 'MB-' . strtoupper(bin2hex(random_bytes(3))),
            'starts_on' => $start,
            'ends_on'   => date('Y-m-d', strtotime($start . " +{$months} months")),
            // Pending until the desk takes payment — nothing here charges a card.
            'status'    => 'pending',
            'credits_remaining' => (int) $plan['court_credit_hours'],
            'auto_renew' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        service('audit')->log('membership.requested', 'membership', (int) $this->db->insertID(), $plan['name']);
        service('bernieNotify')->push($userId, 'membership', $plan['name'] . ' membership requested', 'Settle ' . peso($plan['price']) . ' at the desk and we will switch it on.', '/account');

        return redirect()->to('/account')->with(
            'success',
            $plan['name'] . ' requested. Pay ' . peso($plan['price']) . ' at the desk and it goes live the same day.'
        );
    }
}
