<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * The scorer's table. One tournament at a time, matches in round order, a score
 * box on every live match.
 *
 * Completing a match does three things in one transaction: records the score,
 * moves the win and loss onto both teams' records, and drops the winner into
 * their next slot in the bracket. Doing that by hand across three tables is how
 * a bracket ends up disagreeing with itself halfway through a Saturday.
 *
 * The advance mapping matches how `Tournaments::singleElimination()` numbers
 * matches: the winner of match `m` in round `r` goes to round `r + 1`, match
 * `ceil(m / 2)`, into slot A when `m` is odd and slot B when it is even.
 */
class Scoring extends BaseController
{
    public function index(): string
    {
        $tournaments = $this->db->table('tournaments t')
            ->select('t.id, t.name, t.format, t.status, t.starts_on, COUNT(tm.id) AS matches,
                      SUM(CASE WHEN tm.status IN ("completed","walkover") THEN 1 ELSE 0 END) AS done')
            ->join('tournament_matches tm', 'tm.tournament_id = t.id', 'left')
            ->whereIn('t.status', ['open', 'closed', 'ongoing', 'completed'])
            ->groupBy('t.id')
            ->orderBy('t.starts_on', 'DESC')->get()->getResultArray();

        $pick = (int) ($this->request->getGet('t') ?? 0);
        if ($pick === 0) {
            // Default to whatever is actually being played.
            foreach ($tournaments as $t) {
                if ($t['status'] === 'ongoing' && (int) $t['matches'] > 0) {
                    $pick = (int) $t['id'];
                    break;
                }
            }
        }

        return $this->page('admin/scoring', [
            'title'       => 'Live scoring | Bernie Pickleball',
            'tournaments' => $tournaments,
            'pick'        => $pick,
            'board'       => $pick > 0 ? $this->board($pick) : null,
            'courts'      => $this->db->table('courts')->select('id, code, name')
                ->where('deleted_at', null)->where('status', 'active')->orderBy('sort_order')->get()->getResultArray(),
        ], 'layouts/admin');
    }

    public function score(int $id)
    {
        if (! $this->validate([
            'score_a' => 'required|max_length[40]',
            'score_b' => 'required|max_length[40]',
            'winner'  => 'required|in_list[a,b]',
        ])) {
            return redirect()->back()->with('error', 'Both scores and a winner are needed.');
        }

        $match = $this->db->table('tournament_matches')->where('id', $id)->get()->getRowArray();
        if ($match === null) {
            return redirect()->to('admin/scoring')->with('error', 'No such match.');
        }
        if ($match['participant_a'] === null || $match['participant_b'] === null) {
            return redirect()->to('admin/scoring?t=' . (int) $match['tournament_id'])
                ->with('error', 'That match is still waiting for both teams.');
        }
        if (in_array($match['status'], ['completed', 'walkover'], true)) {
            return redirect()->to('admin/scoring?t=' . (int) $match['tournament_id'])
                ->with('error', 'That match already has a result. Reopen it first if it was wrong.');
        }

        $winnerSide = (string) $this->request->getPost('winner');
        $winnerId   = (int) ($winnerSide === 'a' ? $match['participant_a'] : $match['participant_b']);
        $loserId    = (int) ($winnerSide === 'a' ? $match['participant_b'] : $match['participant_a']);

        $this->db->transBegin();

        try {
            $this->db->table('tournament_matches')->where('id', $id)->update([
                'score_a'    => trim((string) $this->request->getPost('score_a')),
                'score_b'    => trim((string) $this->request->getPost('score_b')),
                'winner_id'  => $winnerId,
                'status'     => 'completed',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->table('tournament_participants')->where('id', $winnerId)
                ->set('wins', 'wins + 1', false)->set('points', 'points + 2', false)
                ->set('updated_at', date('Y-m-d H:i:s'))->update();

            $this->db->table('tournament_participants')->where('id', $loserId)
                ->set('losses', 'losses + 1', false)
                ->set('updated_at', date('Y-m-d H:i:s'))->update();

            $this->advance($match, $winnerId);

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('score_write_failed');
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Score write failed for match {id}: {msg}', ['id' => $id, 'msg' => $e->getMessage()]);

            return redirect()->back()->with('error', 'That score did not save. Nothing was recorded.');
        }

        service('audit')->log('tournament.scored', 'tournament', (int) $match['tournament_id'], sprintf(
            'Match %d round %d: %s to %s',
            (int) $match['match_number'],
            (int) $match['round'],
            $this->request->getPost('score_a') . ' - ' . $this->request->getPost('score_b'),
            $this->teamName($winnerId)
        ), $this->auth->id());

        return redirect()->to('admin/scoring?t=' . (int) $match['tournament_id'])
            ->with('success', $this->teamName($winnerId) . ' takes it.');
    }

    public function live(int $id)
    {
        $match = $this->db->table('tournament_matches')->where('id', $id)->get()->getRowArray();
        if ($match === null) {
            return redirect()->to('admin/scoring')->with('error', 'No such match.');
        }

        $to = $match['status'] === 'live' ? 'scheduled' : 'live';
        $this->db->table('tournament_matches')->where('id', $id)->update([
            'status'     => $to,
            'court_id'   => (int) ($this->request->getPost('court_id') ?: 0) ?: $match['court_id'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('admin/scoring?t=' . (int) $match['tournament_id'])
            ->with('success', $to === 'live' ? 'Match is on court.' : 'Match is back on the schedule.');
    }

    public function reopen(int $id)
    {
        $match = $this->db->table('tournament_matches')->where('id', $id)->get()->getRowArray();
        if ($match === null || $match['status'] !== 'completed') {
            return redirect()->to('admin/scoring')->with('error', 'Only a completed match can be reopened.');
        }

        // A result that has already sent someone into the next round cannot be
        // undone here: the tidy-up would have to walk the whole tree.
        $next = $this->nextSlot($match);
        if ($next !== null) {
            $downstream = $this->db->table('tournament_matches')
                ->where('tournament_id', $match['tournament_id'])
                ->where('round', $next['round'])
                ->where('match_number', $next['match_number'])
                ->whereIn('status', ['live', 'completed', 'walkover'])
                ->countAllResults();

            if ($downstream > 0) {
                return redirect()->to('admin/scoring?t=' . (int) $match['tournament_id'])->with(
                    'error',
                    'The next round has already started with this winner in it. Fix that match first.'
                );
            }
        }

        $winnerId = (int) $match['winner_id'];
        $loserId  = (int) ($winnerId === (int) $match['participant_a'] ? $match['participant_b'] : $match['participant_a']);

        $this->db->transBegin();
        $this->db->table('tournament_matches')->where('id', $id)->update([
            'score_a' => null, 'score_b' => null, 'winner_id' => null,
            'status'  => 'scheduled', 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('tournament_participants')->where('id', $winnerId)
            ->set('wins', 'GREATEST(wins - 1, 0)', false)->set('points', 'GREATEST(points - 2, 0)', false)->update();
        $this->db->table('tournament_participants')->where('id', $loserId)
            ->set('losses', 'GREATEST(losses - 1, 0)', false)->update();

        if ($next !== null) {
            $this->db->table('tournament_matches')
                ->where('tournament_id', $match['tournament_id'])
                ->where('round', $next['round'])
                ->where('match_number', $next['match_number'])
                ->update([$next['slot'] => null, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $this->db->transCommit();

        service('audit')->log('tournament.reopened', 'tournament', (int) $match['tournament_id'], 'match ' . $id, $this->auth->id());

        return redirect()->to('admin/scoring?t=' . (int) $match['tournament_id'])->with('success', 'Match reopened.');
    }

    /** @param array<string, mixed> $match */
    private function advance(array $match, int $winnerId): void
    {
        $next = $this->nextSlot($match);
        if ($next === null) {
            return;
        }

        $this->db->table('tournament_matches')
            ->where('tournament_id', $match['tournament_id'])
            ->where('round', $next['round'])
            ->where('match_number', $next['match_number'])
            ->update([$next['slot'] => $winnerId, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * @param array<string, mixed> $match
     *
     * @return array{round:int, match_number:int, slot:string}|null
     */
    private function nextSlot(array $match)
    {
        // Round robin has no next round to advance into.
        if ($match['round_label'] === 'Round robin') {
            return null;
        }

        $round  = (int) $match['round'];
        $number = (int) $match['match_number'];

        $exists = $this->db->table('tournament_matches')
            ->where('tournament_id', $match['tournament_id'])
            ->where('round', $round + 1)
            ->where('match_number', (int) ceil($number / 2))
            ->countAllResults();

        if ($exists === 0) {
            return null;
        }

        return [
            'round'        => $round + 1,
            'match_number' => (int) ceil($number / 2),
            'slot'         => $number % 2 === 1 ? 'participant_a' : 'participant_b',
        ];
    }

    private function teamName(int $participantId): string
    {
        return (string) ($this->db->table('tournament_participants')->select('team_name')
            ->where('id', $participantId)->get()->getRowArray()['team_name'] ?? 'the winner');
    }

    /** @return array<string, mixed> */
    private function board(int $tournamentId): array
    {
        $tournament = $this->db->table('tournaments')->where('id', $tournamentId)->get()->getRowArray() ?? [];

        $matches = $this->db->table('tournament_matches tm')
            ->select('tm.*, a.team_name AS team_a, b.team_name AS team_b, courts.code AS court_code')
            ->join('tournament_participants a', 'a.id = tm.participant_a', 'left')
            ->join('tournament_participants b', 'b.id = tm.participant_b', 'left')
            ->join('courts', 'courts.id = tm.court_id', 'left')
            ->where('tm.tournament_id', $tournamentId)
            ->orderBy('tm.round')->orderBy('tm.match_number')
            ->get()->getResultArray();

        $rounds = [];
        foreach ($matches as $m) {
            $rounds[$m['round_label'] ?: 'Round ' . $m['round']][] = $m;
        }

        return [
            'tournament' => $tournament,
            'rounds'     => $rounds,
            'standings'  => $this->db->table('tournament_participants')
                ->select('team_name, wins, losses, points, seed, status')
                ->where('tournament_id', $tournamentId)
                ->whereNotIn('status', ['withdrawn'])
                ->orderBy('points', 'DESC')->orderBy('wins', 'DESC')->orderBy('losses')
                ->get()->getResultArray(),
        ];
    }
}
