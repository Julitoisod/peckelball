<?php

namespace App\Controllers;

class Rankings extends BaseController
{
    public function index(): string
    {
        // Season table: aggregate every completed tournament a team has played.
        $standings = $this->db->table('tournament_participants tp')
            ->select('tp.team_name, SUM(tp.wins) AS wins, SUM(tp.losses) AS losses, SUM(tp.points) AS points, COUNT(*) AS events, MIN(tp.final_rank) AS best')
            ->join('tournaments t', 't.id = tp.tournament_id')
            ->where('t.status', 'completed')
            ->groupBy('tp.team_name')
            ->orderBy('points', 'DESC')->orderBy('wins', 'DESC')
            ->get()->getResultArray();

        return $this->page('pages/rankings', [
            'title' => 'Rankings & results | Go Paddle',
            'meta'  => 'Season standings and completed tournament results from Go Paddle, Trento.',
            'standings' => $standings,
            'results'   => $this->db->table('tournament_matches tm')
                ->select('tm.*, a.team_name AS team_a, b.team_name AS team_b, t.name AS tournament, t.slug, courts.code AS court_code')
                ->join('tournaments t', 't.id = tm.tournament_id')
                ->join('tournament_participants a', 'a.id = tm.participant_a', 'left')
                ->join('tournament_participants b', 'b.id = tm.participant_b', 'left')
                ->join('courts', 'courts.id = tm.court_id', 'left')
                ->where('tm.status', 'completed')
                ->orderBy('tm.scheduled_at', 'DESC')
                ->limit(20)->get()->getResultArray(),
            'players' => $this->db->table('users')
                ->select('users.first_name, users.last_name, users.skill_level, users.dupr_rating')
                ->join('roles', 'roles.id = users.role_id')
                ->where('roles.slug', 'player')
                ->where('users.dupr_rating IS NOT NULL')
                ->where('users.deleted_at', null)
                ->orderBy('users.dupr_rating', 'DESC')
                ->limit(10)->get()->getResultArray(),
        ]);
    }
}
