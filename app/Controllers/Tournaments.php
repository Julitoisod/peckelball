<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

class Tournaments extends BaseController
{
    public function index(): string
    {
        $all = $this->db->table('tournaments')
            ->whereIn('status', ['open', 'closed', 'ongoing', 'completed'])
            ->orderBy('starts_on', 'DESC')->get()->getResultArray();

        $counts = array_column(
            $this->db->table('tournament_participants')
                ->select('tournament_id, COUNT(*) AS n')
                ->whereIn('status', ['registered', 'confirmed'])
                ->groupBy('tournament_id')->get()->getResultArray(),
            'n',
            'tournament_id'
        );

        foreach ($all as &$t) {
            $t['entered'] = (int) ($counts[$t['id']] ?? 0);
        }
        unset($t);

        $today = date('Y-m-d');

        return $this->page('pages/tournaments', [
            'title'    => 'Tournaments | Bernie Pickleball',
            'meta'     => 'Open tournaments, leagues and junior cups at Bernie Pickleball in Trento, Agusan del Sur.',
            'upcoming' => array_values(array_filter($all, static fn ($t) => $t['ends_on'] >= $today && $t['status'] !== 'completed')),
            'past'     => array_values(array_filter($all, static fn ($t) => $t['ends_on'] < $today || $t['status'] === 'completed')),
        ]);
    }

    public function show(string $slug): string
    {
        $tournament = $this->db->table('tournaments')->where('slug', $slug)->get()->getRowArray();
        if ($tournament === null) {
            throw PageNotFoundException::forPageNotFound('No tournament by that name.');
        }

        $participants = $this->db->table('tournament_participants')
            ->where('tournament_id', $tournament['id'])
            ->whereIn('status', ['registered', 'confirmed'])
            ->orderBy('seed')->get()->getResultArray();

        $matches = $this->db->table('tournament_matches tm')
            ->select('tm.*, a.team_name AS team_a, b.team_name AS team_b, courts.code AS court_code')
            ->join('tournament_participants a', 'a.id = tm.participant_a', 'left')
            ->join('tournament_participants b', 'b.id = tm.participant_b', 'left')
            ->join('courts', 'courts.id = tm.court_id', 'left')
            ->where('tm.tournament_id', $tournament['id'])
            ->orderBy('tm.round')->orderBy('tm.match_number')
            ->get()->getResultArray();

        $rounds = [];
        foreach ($matches as $m) {
            $rounds[$m['round_label'] ?: 'Round ' . $m['round']][] = $m;
        }

        return $this->page('pages/tournament_detail', [
            'title'  => $tournament['name'] . ' | Bernie Pickleball',
            'meta'   => mb_strimwidth((string) $tournament['description'], 0, 155, '…'),
            'tournament'   => $tournament,
            'participants' => $participants,
            'rounds'       => $rounds,
            'spotsLeft'    => max(0, (int) $tournament['max_teams'] - count($participants)),
            'canRegister'  => $tournament['status'] === 'open'
                && ($tournament['registration_closes'] === null || $tournament['registration_closes'] >= date('Y-m-d'))
                && count($participants) < (int) $tournament['max_teams'],
        ]);
    }

    public function register(string $slug)
    {
        $tournament = $this->db->table('tournaments')->where('slug', $slug)->get()->getRowArray();
        if ($tournament === null) {
            throw PageNotFoundException::forPageNotFound('No tournament by that name.');
        }

        if ($tournament['status'] !== 'open') {
            return redirect()->back()->with('error', 'Registration for ' . $tournament['name'] . ' is not open.');
        }

        if (! $this->validate([
            'team_name'     => 'required|min_length[2]|max_length[120]',
            'contact_phone' => 'required|min_length[7]|max_length[30]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        // Nothing in the schema stops the same team entering twice, and a
        // double submit used to produce two rows sharing one seed number.
        $duplicate = $this->db->table('tournament_participants')
            ->where('tournament_id', $tournament['id'])
            ->whereIn('status', ['registered', 'confirmed']);

        $this->auth->check()
            ? $duplicate->where('user_id', $this->auth->id())
            : $duplicate->where('team_name', trim((string) $this->request->getPost('team_name')));

        if ($duplicate->countAllResults() > 0) {
            return redirect()->to('tournaments/' . $slug)->with('error', 'That entry is already in the draw.');
        }

        $entered = $this->db->table('tournament_participants')
            ->where('tournament_id', $tournament['id'])
            ->whereIn('status', ['registered', 'confirmed'])->countAllResults();

        if ($entered >= (int) $tournament['max_teams']) {
            return redirect()->back()->with('error', 'That draw is full. Ask the desk about the reserve list.');
        }

        $this->db->table('tournament_participants')->insert([
            'tournament_id' => (int) $tournament['id'],
            'user_id'       => $this->auth->id(),
            'team_name'     => trim((string) $this->request->getPost('team_name')),
            'contact_phone' => trim((string) $this->request->getPost('contact_phone')),
            'seed'          => $entered + 1,
            'status'        => 'registered',
            'payment_status' => 'unpaid',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        service('audit')->log('tournament.registered', 'tournament', (int) $tournament['id'], $this->request->getPost('team_name'));

        return redirect()->to('tournaments/' . $slug)->with(
            'success',
            'Entered as "' . $this->request->getPost('team_name') . '". Settle the ' . peso($tournament['entry_fee']) . ' entry at the desk to confirm your spot.'
        );
    }
}
