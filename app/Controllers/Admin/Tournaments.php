<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Tournaments, the teams in them, and the bracket.
 *
 * Bracket generation is deliberately limited to the two formats whose pairing
 * rules are unambiguous: single elimination (seeds paired 1 v N, byes given to
 * the top seeds) and round robin (every pair once). Double elimination and pool
 * play need a losers' tree and group tables, and a half-built one of those is
 * worse at a scorer's table than none, so those formats say so instead.
 */
class Tournaments extends BaseController
{
    private const FORMATS  = ['single_elimination', 'double_elimination', 'round_robin', 'pool_play'];
    private const LEVELS   = ['beginner', 'intermediate', 'advanced', 'pro', 'open'];
    private const STATUSES = ['draft', 'open', 'closed', 'ongoing', 'completed', 'cancelled'];

    public function index(): string
    {
        $openId = (int) ($this->request->getGet('id') ?? 0);

        $rows = $this->db->table('tournaments t')
            ->select('t.*, COUNT(DISTINCT tp.id) AS teams,
                      SUM(CASE WHEN tp.status = "confirmed" THEN 1 ELSE 0 END) AS confirmed,
                      COUNT(DISTINCT tm.id) AS matches')
            ->join('tournament_participants tp', 'tp.tournament_id = t.id', 'left')
            ->join('tournament_matches tm', 'tm.tournament_id = t.id', 'left')
            ->groupBy('t.id')
            ->orderBy('t.starts_on', 'DESC')->get()->getResultArray();

        return $this->page('admin/tournaments', [
            'title'    => 'Tournaments | Bernie Pickleball',
            'rows'     => $rows,
            'open'     => $openId > 0 ? $this->detail($openId) : null,
            'formats'  => self::FORMATS,
            'levels'   => self::LEVELS,
            'statuses' => self::STATUSES,
        ], 'layouts/admin');
    }

    public function save()
    {
        if (! $this->validate([
            'name'        => 'required|max_length[160]',
            'division'    => 'required|max_length[60]',
            'format'      => 'required|in_list[' . implode(',', self::FORMATS) . ']',
            'skill_level' => 'required|in_list[' . implode(',', self::LEVELS) . ']',
            'status'      => 'required|in_list[' . implode(',', self::STATUSES) . ']',
            'starts_on'   => 'required|valid_date[Y-m-d]',
            'ends_on'     => 'required|valid_date[Y-m-d]',
            'entry_fee'   => 'permit_empty|numeric|greater_than_equal_to[0]',
            'prize_pool'  => 'permit_empty|numeric|greater_than_equal_to[0]',
            'max_teams'   => 'required|is_natural_no_zero|less_than_equal_to[256]',
            'description' => 'permit_empty|max_length[5000]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $starts = (string) $this->request->getPost('starts_on');
        $ends   = (string) $this->request->getPost('ends_on');
        if ($ends < $starts) {
            return redirect()->back()->withInput()->with('error', 'The tournament cannot end before it starts.');
        }

        $name = trim((string) $this->request->getPost('name'));
        $id   = (int) ($this->request->getPost('id') ?? 0);

        $data = [
            'name'        => $name,
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'format'      => (string) $this->request->getPost('format'),
            'division'    => trim((string) $this->request->getPost('division')),
            'skill_level' => (string) $this->request->getPost('skill_level'),
            'starts_on'   => $starts,
            'ends_on'     => $ends,
            'registration_opens'  => $this->date((string) $this->request->getPost('registration_opens')),
            'registration_closes' => $this->date((string) $this->request->getPost('registration_closes')),
            'entry_fee'   => (float) ($this->request->getPost('entry_fee') ?: 0),
            'prize_pool'  => (float) ($this->request->getPost('prize_pool') ?: 0),
            'max_teams'   => (int) $this->request->getPost('max_teams'),
            'status'      => (string) $this->request->getPost('status'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            if ($this->db->table('tournaments')->where('id', $id)->countAllResults() === 0) {
                return redirect()->to('admin/tournaments')->with('error', 'No such tournament.');
            }
            $this->db->table('tournaments')->where('id', $id)->update($data);
            service('audit')->log('tournament.updated', 'tournament', $id, $name, $this->auth->id());

            return redirect()->to('admin/tournaments?id=' . $id)->with('success', $name . ' updated.');
        }

        // The slug is the public URL, so it has to be unique and stable.
        $data['slug']       = $this->uniqueSlug($name);
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->table('tournaments')->insert($data);
        $newId = (int) $this->db->insertID();
        service('audit')->log('tournament.created', 'tournament', $newId, $name, $this->auth->id());

        return redirect()->to('admin/tournaments?id=' . $newId)->with('success', $name . ' created. Add teams, then draw the bracket.');
    }

    public function team(int $id, int $participantId)
    {
        $to = (string) $this->request->getPost('status');
        if (! in_array($to, ['registered', 'confirmed', 'withdrawn', 'disqualified'], true)) {
            return redirect()->to('admin/tournaments?id=' . $id)->with('error', 'That is not a team state.');
        }

        $team = $this->db->table('tournament_participants')
            ->where('id', $participantId)->where('tournament_id', $id)->get()->getRowArray();

        if ($team === null) {
            return redirect()->to('admin/tournaments?id=' . $id)->with('error', 'No such team.');
        }

        $this->db->table('tournament_participants')->where('id', $participantId)->update([
            'status'     => $to,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        service('audit')->log('tournament.team', 'tournament', $id, $team['team_name'] . ' is ' . $to, $this->auth->id());

        return redirect()->to('admin/tournaments?id=' . $id)->with('success', $team['team_name'] . ' is now ' . $to . '.');
    }

    public function pay(int $id, int $participantId)
    {
        $team = $this->db->table('tournament_participants')
            ->where('id', $participantId)->where('tournament_id', $id)->get()->getRowArray();
        $tournament = $this->db->table('tournaments')->where('id', $id)->get()->getRowArray();

        if ($team === null || $tournament === null) {
            return redirect()->to('admin/tournaments?id=' . $id)->with('error', 'No such team.');
        }
        if ($team['payment_status'] === 'paid') {
            return redirect()->to('admin/tournaments?id=' . $id)->with('error', $team['team_name'] . ' has already paid.');
        }

        $method = (string) $this->request->getPost('method');
        if (! in_array($method, ['cash', 'gcash', 'maya', 'card', 'bank_transfer'], true)) {
            return redirect()->to('admin/tournaments?id=' . $id)->with('error', 'That is not a payment method we take.');
        }

        $this->db->table('payments')->insert([
            'reference'   => 'PY-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
            'user_id'     => $team['user_id'],
            'purpose'     => 'tournament',
            'method'      => $method,
            'amount'      => (float) $tournament['entry_fee'],
            'status'      => 'paid',
            'paid_at'     => date('Y-m-d H:i:s'),
            'received_by' => $this->auth->id(),
            'notes'       => $tournament['name'] . ' entry: ' . $team['team_name'],
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        // Paying is what confirms an entry, so the two move together.
        $this->db->table('tournament_participants')->where('id', $participantId)->update([
            'payment_status' => 'paid',
            'status'         => $team['status'] === 'registered' ? 'confirmed' : $team['status'],
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        service('audit')->log('payment.recorded', 'tournament', $id, $team['team_name'] . ' entry ' . peso((float) $tournament['entry_fee']), $this->auth->id());

        return redirect()->to('admin/tournaments?id=' . $id)->with('success', sprintf(
            '%s paid %s and is confirmed.',
            $team['team_name'],
            peso((float) $tournament['entry_fee'])
        ));
    }

    public function draw(int $id)
    {
        $tournament = $this->db->table('tournaments')->where('id', $id)->get()->getRowArray();
        if ($tournament === null) {
            return redirect()->to('admin/tournaments')->with('error', 'No such tournament.');
        }

        if ($this->db->table('tournament_matches')->where('tournament_id', $id)->countAllResults() > 0) {
            return redirect()->to('admin/tournaments?id=' . $id)
                ->with('error', 'This bracket already has matches. Clear them before drawing again.');
        }

        $teams = $this->db->table('tournament_participants')
            ->select('id, team_name, seed')
            ->where('tournament_id', $id)->where('status', 'confirmed')
            ->orderBy('COALESCE(seed, 9999)')->orderBy('id')
            ->get()->getResultArray();

        if (count($teams) < 2) {
            return redirect()->to('admin/tournaments?id=' . $id)
                ->with('error', 'Confirm at least two teams before drawing.');
        }

        $matches = match ($tournament['format']) {
            'single_elimination' => $this->singleElimination($teams),
            'round_robin'        => $this->roundRobin($teams),
            default              => null,
        };

        if ($matches === null) {
            return redirect()->to('admin/tournaments?id=' . $id)->with('error', sprintf(
                'Drawing is only wired for single elimination and round robin. %s brackets have to be run off the system for now.',
                ucwords(str_replace('_', ' ', $tournament['format']))
            ));
        }

        foreach ($matches as $m) {
            $this->db->table('tournament_matches')->insert($m + [
                'tournament_id' => $id,
                'status'        => 'scheduled',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        service('audit')->log('tournament.drawn', 'tournament', $id, count($matches) . ' matches, ' . count($teams) . ' teams', $this->auth->id());

        return redirect()->to('admin/tournaments?id=' . $id)->with('success', sprintf(
            'Bracket drawn: %d matches for %d teams. Scores go in under Live scoring.',
            count($matches),
            count($teams)
        ));
    }

    public function clearDraw(int $id)
    {
        // Only a real result blocks a redraw. The byes this draw creates are
        // walkovers with no score, so counting those would mean any bracket with
        // an odd number of teams could never be redrawn.
        $played = $this->db->table('tournament_matches')
            ->where('tournament_id', $id)
            ->groupStart()
                ->where('status', 'completed')
                ->orGroupStart()->where('status', 'walkover')->where('score_a IS NOT NULL')->groupEnd()
            ->groupEnd()
            ->countAllResults();

        if ($played > 0) {
            return redirect()->to('admin/tournaments?id=' . $id)->with(
                'error',
                $played . ' matches already have results. Clearing the draw would throw those away, so it is refused.'
            );
        }

        $this->db->table('tournament_matches')->where('tournament_id', $id)->delete();
        service('audit')->log('tournament.draw_cleared', 'tournament', $id, 'bracket cleared', $this->auth->id());

        return redirect()->to('admin/tournaments?id=' . $id)->with('success', 'Bracket cleared.');
    }

    /**
     * Seeds paired 1 v N, 2 v N-1 and so on, padded up to a power of two so the
     * top seeds get the byes rather than whoever happens to be last in the list.
     *
     * @param list<array<string, mixed>> $teams
     *
     * @return list<array<string, mixed>>
     */
    private function singleElimination(array $teams): array
    {
        $n    = count($teams);
        $size = 2 ** (int) ceil(log(max(2, $n), 2));
        $ids  = array_column($teams, 'id');
        $ids  = array_pad($ids, $size, null);   // nulls are byes

        $matches = [];
        $number  = 1;
        $pairs   = [];

        for ($i = 0; $i < $size / 2; $i++) {
            $pairs[] = [$ids[$i], $ids[$size - 1 - $i]];
        }

        $rounds = (int) log($size, 2);
        foreach ($pairs as [$a, $b]) {
            $matches[] = [
                'round'          => 1,
                'round_label'    => $this->roundLabel(1, $rounds),
                'match_number'   => $number++,
                'participant_a'  => $a,
                'participant_b'  => $b,
                // A bye is a walkover, not a match anybody turns up for.
                'winner_id'      => $b === null ? $a : null,
                'status'         => $b === null ? 'walkover' : 'scheduled',
            ];
        }

        // Empty shells for the later rounds so the winners have somewhere to go.
        $inRound = count($pairs);
        for ($round = 2; $round <= $rounds; $round++) {
            $inRound = (int) ($inRound / 2);
            $number  = 1;
            for ($i = 0; $i < $inRound; $i++) {
                $matches[] = [
                    'round'         => $round,
                    'round_label'   => $this->roundLabel($round, $rounds),
                    'match_number'  => $number++,
                    'participant_a' => null,
                    'participant_b' => null,
                    'status'        => 'scheduled',
                ];
            }
        }

        return $matches;
    }

    /**
     * @param list<array<string, mixed>> $teams
     *
     * @return list<array<string, mixed>>
     */
    private function roundRobin(array $teams): array
    {
        $ids     = array_column($teams, 'id');
        $matches = [];
        $number  = 1;

        for ($i = 0; $i < count($ids); $i++) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $matches[] = [
                    'round'         => 1,
                    'round_label'   => 'Round robin',
                    'match_number'  => $number++,
                    'participant_a' => $ids[$i],
                    'participant_b' => $ids[$j],
                    'status'        => 'scheduled',
                ];
            }
        }

        return $matches;
    }

    private function roundLabel(int $round, int $rounds): string
    {
        $fromEnd = $rounds - $round;

        return match ($fromEnd) {
            0       => 'Final',
            1       => 'Semi-final',
            2       => 'Quarter-final',
            default => 'Round ' . $round,
        };
    }

    /** @return array<string, mixed>|null */
    private function detail(int $id)
    {
        $tournament = $this->db->table('tournaments')->where('id', $id)->get()->getRowArray();
        if ($tournament === null) {
            return null;
        }

        $tournament['teams'] = $this->db->table('tournament_participants tp')
            ->select('tp.*, u.first_name, u.last_name, u.email, p.first_name AS partner_first, p.last_name AS partner_last')
            ->join('users u', 'u.id = tp.user_id', 'left')
            ->join('users p', 'p.id = tp.partner_id', 'left')
            ->where('tp.tournament_id', $id)
            ->orderBy('COALESCE(tp.seed, 9999)')->orderBy('tp.id')
            ->get()->getResultArray();

        $tournament['matches'] = $this->db->table('tournament_matches')
            ->where('tournament_id', $id)->orderBy('round')->orderBy('match_number')
            ->get()->getResultArray();

        return $tournament;
    }

    private function uniqueSlug(string $name): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? '', '-') ?: 'tournament';
        $slug = $base;
        $n    = 2;

        while ($this->db->table('tournaments')->where('slug', $slug)->countAllResults() > 0) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }

    private function date(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false ? $value : null;
    }
}
