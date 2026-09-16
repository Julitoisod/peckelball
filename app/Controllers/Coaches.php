<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

class Coaches extends BaseController
{
    public function index(): string
    {
        return $this->page('pages/coaches', [
            'title'   => 'Coaches | Bernie Pickleball',
            'meta'    => 'Four certified pickleball coaches in Trento, Agusan del Sur. Private, semi-private, group and junior sessions.',
            'coaches' => $this->roster(),
        ]);
    }

    public function show(int $id): string
    {
        $coach = $this->db->table('coaches')
            ->select('coaches.*, users.first_name, users.last_name, users.email')
            ->join('users', 'users.id = coaches.user_id')
            ->where('coaches.id', $id)->where('coaches.deleted_at', null)
            ->get()->getRowArray();

        if ($coach === null) {
            throw PageNotFoundException::forPageNotFound('No coach with that id.');
        }

        return $this->page('pages/coach_detail', [
            'title' => 'Coach ' . $coach['first_name'] . ' ' . $coach['last_name'] . ' | Bernie Pickleball',
            'meta'  => mb_strimwidth((string) $coach['bio'], 0, 155, '…'),
            'coach' => $coach,
            'availability' => $this->db->table('coach_availability')->where('coach_id', $id)->orderBy('day_of_week')->get()->getResultArray(),
            'sessions' => $this->db->table('coaching_sessions')
                ->where('coach_id', $id)->where('session_date >=', business_date())
                ->whereIn('status', ['confirmed', 'pending'])
                ->orderBy('session_date')->orderBy('start_time')->limit(6)->get()->getResultArray(),
        ]);
    }

    /** Coaching session booking — the same engine, with a coach attached. */
    public function sessions(): string
    {
        return $this->page('pages/coaching', [
            'title'   => 'Book a coaching session | Bernie Pickleball',
            'meta'    => 'Private, semi-private, group and junior pickleball coaching in Trento. Book a coach and a court in one step.',
            'coaches' => $this->roster(),
            'types'   => [
                ['key' => 'private',      'name' => 'Private',      'players' => '1 player',    'note' => 'One coach, one player, full hour of attention. The fastest way to move a level.'],
                ['key' => 'semi_private', 'name' => 'Semi-private', 'players' => '2 players',   'note' => 'You and a partner. Split the rate, keep the feedback specific.'],
                ['key' => 'group',        'name' => 'Group',        'players' => '3-4 players', 'note' => 'Drills and live-ball rotations. Best value per player.'],
                ['key' => 'clinic',       'name' => 'Clinic',       'players' => '6-8 players', 'note' => 'Themed two-hour block on a covered court. Runs Saturdays.'],
            ],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function roster(): array
    {
        return $this->db->table('coaches')
            ->select('coaches.*, users.first_name, users.last_name')
            ->join('users', 'users.id = coaches.user_id')
            ->where('coaches.deleted_at', null)
            ->orderBy('coaches.sort_order')
            ->get()->getResultArray();
    }
}
