<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * A coach's own teaching diary.
 *
 * The `sessions.own` permission existed from the start and gated nothing, which
 * meant the four coach accounts, the largest group of staff, signed in to a
 * dashboard with nothing on it for them. This is that screen.
 *
 * "Own" is resolved from the signed-in user's coach profile, never from a
 * parameter, so one coach cannot read another's diary by editing the URL.
 * Someone holding `coaches.manage` is a supervisor rather than a coach, so they
 * get a picker instead of an empty page.
 */
class Sessions extends BaseController
{
    public function index(): string
    {
        $mine = $this->db->table('coaches')->select('id, hourly_rate, group_rate')
            ->where('user_id', $this->auth->id())->where('deleted_at', null)
            ->get()->getRowArray();

        $supervisor = $this->auth->can('coaches.manage');
        $coachId    = (int) ($mine['id'] ?? 0);

        // A supervisor may look at any coach; a coach only ever sees their own.
        if ($supervisor) {
            $asked = (int) ($this->request->getGet('coach') ?? 0);
            if ($asked > 0) {
                $coachId = $asked;
            } elseif ($coachId === 0) {
                $coachId = (int) ($this->db->table('coaches')->select('id')
                    ->where('deleted_at', null)->orderBy('sort_order')
                    ->get()->getRowArray()['id'] ?? 0);
            }
        }

        $today = business_date();
        $rows  = [];

        if ($coachId > 0) {
            $rows = $this->db->table('coaching_sessions cs')
                ->select('cs.*, b.reference, b.start_time AS booking_start, b.guest_name,
                          courts.code AS court_code, courts.name AS court_name,
                          u.first_name, u.last_name, u.phone, u.skill_level')
                ->join('bookings b', 'b.id = cs.booking_id', 'left')
                ->join('courts', 'courts.id = b.court_id', 'left')
                ->join('users u', 'u.id = cs.user_id', 'left')
                ->where('cs.coach_id', $coachId)
                ->orderBy('cs.session_date', 'DESC')->orderBy('cs.start_time', 'DESC')
                ->limit(120)->get()->getResultArray();
        }

        $upcoming = array_values(array_filter($rows, static fn ($r) => $r['session_date'] >= $today && $r['status'] !== 'cancelled'));
        $past     = array_values(array_filter($rows, static fn ($r) => $r['session_date'] < $today || $r['status'] === 'cancelled'));

        return $this->page('admin/sessions', [
            'title'      => 'My sessions | Go Paddle',
            'coachId'    => $coachId,
            'isCoach'    => $mine !== null,
            'supervisor' => $supervisor,
            'coaches'    => $supervisor
                ? $this->db->table('coaches c')->select('c.id, u.first_name, u.last_name')
                    ->join('users u', 'u.id = c.user_id')
                    ->where('c.deleted_at', null)->orderBy('c.sort_order')->get()->getResultArray()
                : [],
            'upcoming'   => $upcoming,
            'past'       => array_slice($past, 0, 30),
            'earned'     => array_sum(array_map(
                static fn ($r) => $r['status'] === 'completed' ? (float) $r['rate'] : 0,
                $rows
            )),
            'taught'     => count(array_filter($rows, static fn ($r) => $r['status'] === 'completed')),
        ], 'layouts/admin');
    }
}
