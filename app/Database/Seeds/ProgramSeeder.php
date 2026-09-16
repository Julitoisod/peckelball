<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Membership plans, promos, tournaments (with a real bracket), testimonials,
 * announcements and the settings the dashboard reads.
 */
class ProgramSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // ------------------------------------------------------ membership plans
        $plans = [
            ['RALLY', 'Rally', 'For the once-a-week player.', 1200, 'monthly', 4, 10, 1, 7, 0,
                "4 court-hours a month|10% off every extra booking|1 guest pass|Book 7 days ahead|Member-only Tuesday round robin"],
            ['BASELINE', 'Baseline', 'The plan most of our regulars are on.', 2400, 'monthly', 10, 15, 2, 14, 1,
                "10 court-hours a month|15% off every extra booking|2 guest passes|Book 14 days ahead|Free paddle rental|Priority tournament entry"],
            ['ALLCOURT', 'All-Court', 'Three months, one payment, more court time.', 6500, 'quarterly', 32, 20, 6, 21, 0,
                "32 court-hours a quarter|20% off every extra booking|6 guest passes|Book 21 days ahead|Free paddle and shoe rental|1 free coaching hour per month|10% off the pro shop"],
            ['FOUNDER', 'Founder', 'The first fifty members. Locked rate, for good.', 22000, 'annual', 150, 25, 24, 30, 0,
                "150 court-hours a year|25% off every extra booking|24 guest passes|Book 30 days ahead|All rentals included|4 free coaching hours per quarter|15% off the pro shop|Name on the Center Court wall"],
        ];

        $planRows = [];
        foreach ($plans as $i => [$code, $name, $tagline, $price, $period, $credits, $discount, $guests, $advance, $featured, $perks]) {
            $planRows[] = [
                'code' => $code, 'name' => $name, 'tagline' => $tagline, 'price' => $price,
                'billing_period' => $period, 'court_credit_hours' => $credits,
                'discount_percent' => $discount, 'guest_passes' => $guests,
                'advance_booking_days' => $advance, 'perks' => $perks,
                'is_featured' => $featured, 'is_active' => 1, 'sort_order' => $i,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        $this->db->table('membership_plans')->insertBatch($planRows);

        // ------------------------------------------------------------ promo codes
        $this->db->table('promo_codes')->insertBatch([
            [
                'code' => 'FIRSTDINK', 'description' => 'First booking for new players — 25% off one court hour.',
                'discount_type' => 'percent', 'discount_value' => 25, 'min_amount' => 0,
                'max_uses' => 500, 'used_count' => 143,
                'starts_on' => date('Y-m-d', strtotime('-60 days')), 'ends_on' => date('Y-m-d', strtotime('+120 days')),
                'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'MORNING100', 'description' => '₱100 off any booking that starts before 09:00.',
                'discount_type' => 'fixed', 'discount_value' => 100, 'min_amount' => 300,
                'max_uses' => 0, 'used_count' => 88,
                'starts_on' => date('Y-m-d', strtotime('-30 days')), 'ends_on' => date('Y-m-d', strtotime('+60 days')),
                'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'TRENTO15', 'description' => 'Trento residents — 15% off, ID at the desk.',
                'discount_type' => 'percent', 'discount_value' => 15, 'min_amount' => 0,
                'max_uses' => 0, 'used_count' => 302,
                'starts_on' => date('Y-m-d', strtotime('-180 days')), 'ends_on' => null,
                'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        // ------------------------------------------------------------ tournaments
        $tournaments = [
            [
                'slug' => 'agusan-open-2026', 'name' => 'Agusan Open 2026',
                'format' => 'double_elimination', 'division' => 'Mixed Doubles', 'skill' => 'open',
                'start' => '+24 days', 'end' => '+26 days', 'regOpen' => '-10 days', 'regClose' => '+18 days',
                'fee' => 1500, 'prize' => 60000, 'max' => 32, 'status' => 'open',
                'banner' => 'photo-1693142518820-78d7a05f1546',
                'desc' => 'Three days, thirty-two teams, one trophy that stays in Trento. Double elimination across all five courts with finals under lights on Center Court. Entry covers both partners, a shirt, and lunch on finals day.',
            ],
            [
                'slug' => 'kitchen-league-night-4', 'name' => 'Kitchen League — Night 4',
                'format' => 'round_robin', 'division' => "Men's & Women's Doubles", 'skill' => 'intermediate',
                'start' => '+6 days', 'end' => '+6 days', 'regOpen' => '-20 days', 'regClose' => '+4 days',
                'fee' => 400, 'prize' => 8000, 'max' => 16, 'status' => 'open',
                'banner' => 'photo-1723004714201-cf224222b897',
                'desc' => 'Our Thursday night league. Eight rounds of eleven-point games, everybody plays everybody, results feed the season table. 3.0-4.0 only — we check.',
            ],
            [
                'slug' => 'trento-juniors-cup', 'name' => 'Trento Juniors Cup',
                'format' => 'pool_play', 'division' => 'U-16 Doubles', 'skill' => 'beginner',
                'start' => '+45 days', 'end' => '+45 days', 'regOpen' => '+10 days', 'regClose' => '+40 days',
                'fee' => 250, 'prize' => 12000, 'max' => 24, 'status' => 'draft',
                'banner' => 'photo-1737231802748-5850838f35e7',
                'desc' => 'Pool play into a knockout for players under sixteen. Coach Kaye runs it, paddles are provided, and every entrant goes home with something.',
            ],
            [
                'slug' => 'bernie-invitational-2025', 'name' => 'Bernie Invitational 2025',
                'format' => 'single_elimination', 'division' => 'Open Doubles', 'skill' => 'advanced',
                'start' => '-70 days', 'end' => '-69 days', 'regOpen' => '-120 days', 'regClose' => '-75 days',
                'fee' => 1200, 'prize' => 45000, 'max' => 16, 'status' => 'completed',
                'banner' => 'photo-1686721135030-e2ab79e27b16',
                'desc' => 'The tournament that opened the covered courts. Sixteen teams, one afternoon of rain that pushed everything under the canopy, and a final that went to 15-13.',
            ],
        ];

        $tourRows = [];
        foreach ($tournaments as $t) {
            $tourRows[] = [
                'slug' => $t['slug'], 'name' => $t['name'], 'description' => $t['desc'],
                'format' => $t['format'], 'division' => $t['division'], 'skill_level' => $t['skill'],
                'starts_on' => date('Y-m-d', strtotime($t['start'])),
                'ends_on'   => date('Y-m-d', strtotime($t['end'])),
                'registration_opens'  => date('Y-m-d', strtotime($t['regOpen'])),
                'registration_closes' => date('Y-m-d', strtotime($t['regClose'])),
                'entry_fee' => $t['fee'], 'prize_pool' => $t['prize'], 'max_teams' => $t['max'],
                'status' => $t['status'], 'banner_path' => $t['banner'],
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        $this->db->table('tournaments')->insertBatch($tourRows);

        $this->seedCompletedBracket($now);
        $this->seedOpenRegistrations($now);

        // ----------------------------------------------------------- testimonials
        $this->db->table('testimonials')->insertBatch([
            [
                'author_name' => 'Aljon Pacheco', 'author_role' => 'Baseline member since 2024',
                'quote' => 'I used to text the desk and wait. Now I can see Court 3 is free at 18:30 and I am booked before I have finished parking. That is the whole difference.',
                'rating' => 5, 'photo_path' => 'photo-1609126396762-542d99fc7a07', 'is_published' => 1, 'sort_order' => 0, 'created_at' => $now,
            ],
            [
                'author_name' => 'Christine Larazo', 'author_role' => '4.25 DUPR · Agusan Open finalist',
                'quote' => 'Coach Dennis rebuilt my third shot in six sessions. The covered courts mean we never lose a block to rain, which in this province matters more than anything else.',
                'rating' => 5, 'photo_path' => 'photo-1600817629838-cc0f3f55e690', 'is_published' => 1, 'sort_order' => 1, 'created_at' => $now,
            ],
            [
                'author_name' => 'Renz Cabañero', 'author_role' => 'Founder member',
                'quote' => 'Five courts is enough that you never wait and small enough that everyone knows your name by the second week. The Thursday league is the best night in Trento.',
                'rating' => 5, 'photo_path' => 'photo-1683049621511-5eb772204bc4', 'is_published' => 1, 'sort_order' => 2, 'created_at' => $now,
            ],
            [
                'author_name' => 'Michelle Tabuada', 'author_role' => 'Started playing here in January',
                'quote' => 'I turned up with no paddle and no idea. They rented me one for eighty pesos and Coach Rhea put me in a beginner clinic that same week. Six months later I am 3.25.',
                'rating' => 5, 'photo_path' => 'photo-1616305798947-6cb245a84e18', 'is_published' => 1, 'sort_order' => 3, 'created_at' => $now,
            ],
        ]);

        // ---------------------------------------------------------- announcements
        $ownerId = $this->db->table('users')->select('id')->where('email', 'bernie@berniepickleball.ph')->get()->getRow('id');

        $this->db->table('announcements')->insertBatch([
            [
                'author_id' => $ownerId, 'title' => 'Agusan Open 2026 registration is open',
                'body' => "Thirty-two teams, ₱60,000 prize pool, three days across all five courts. Entry closes when we hit thirty-two or eighteen days from now — whichever comes first. Both partners are covered by one entry.",
                'audience' => 'public', 'is_pinned' => 1,
                'published_at' => date('Y-m-d H:i:s', strtotime('-6 days')),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+18 days')),
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'author_id' => $ownerId, 'title' => 'Riverside Court closed for resurfacing on the 9th',
                'body' => "Court 4 is out from 07:00 to 12:00 while we grind and patch the east baseline. Bookings already on it have been moved to Court 5 at the same time, no charge difference.",
                'audience' => 'public', 'is_pinned' => 0,
                'published_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+10 days')),
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'author_id' => $ownerId, 'title' => 'Morning rate now runs until 09:00',
                'body' => "MORNING100 takes ₱100 off anything starting before nine. We extended it by an hour because the 08:00 slot was going empty.",
                'audience' => 'players', 'is_pinned' => 0,
                'published_at' => date('Y-m-d H:i:s', strtotime('-14 days')),
                'expires_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        // --------------------------------------------------------------- settings
        $settings = [
            ['facility.name', 'Bernie Pickleball', 'string', 'general', 'Facility name'],
            ['facility.tagline', 'Five courts. Trento, Agusan del Sur.', 'string', 'general', 'Tagline'],
            ['facility.address', 'Purok 3, National Highway, Trento, Agusan del Sur 8505', 'string', 'general', 'Street address'],
            ['facility.phone', '+63 917 555 0100', 'string', 'general', 'Contact number'],
            ['facility.email', 'hello@berniepickleball.ph', 'string', 'general', 'Contact email'],
            ['facility.maps_url', 'https://www.google.com/maps/search/?api=1&query=Trento+Agusan+del+Sur', 'string', 'general', 'Map link'],
            ['hours.open', '16:00', 'string', 'operations', 'Opening time'],
            ['hours.close', '06:00', 'string', 'operations', 'Closing time'],
            ['booking.slot_minutes', '60', 'int', 'operations', 'Booking slot length (minutes)'],
            ['booking.min_minutes', '60', 'int', 'operations', 'Minimum booking length (minutes)'],
            ['booking.max_minutes', '180', 'int', 'operations', 'Maximum booking length (minutes)'],
            ['booking.advance_days', '14', 'int', 'operations', 'How far ahead the public may book (days)'],
            ['booking.cutoff_minutes', '30', 'int', 'operations', 'Cutoff before start time (minutes)'],
            ['pricing.peak_start', '16:00', 'string', 'pricing', 'Peak window starts'],
            ['pricing.peak_end', '16:00', 'string', 'pricing', 'Peak window ends'],
            ['pricing.currency', 'PHP', 'string', 'pricing', 'Currency'],
            ['pricing.currency_symbol', '₱', 'string', 'pricing', 'Currency symbol'],
        ];

        $settingRows = [];
        foreach ($settings as [$key, $value, $type, $group, $label]) {
            $settingRows[] = ['key' => $key, 'value' => $value, 'type' => $type, 'group' => $group, 'label' => $label, 'updated_at' => $now];
        }
        $this->db->table('settings')->insertBatch($settingRows);
    }

    /**
     * A finished 8-team bracket for the 2025 Invitational, so /rankings and the
     * bracket view have real results rather than an empty state.
     */
    private function seedCompletedBracket(string $now): void
    {
        $tId = (int) $this->db->table('tournaments')->select('id')->where('slug', 'bernie-invitational-2025')->get()->getRow('id');
        $players = array_column(
            $this->db->table('users')->select('id')->where('role_id', $this->playerRoleId())->orderBy('id')->get()->getResultArray(),
            'id'
        );

        $teams = [
            'Kitchen Cartel', 'Dink Responsibly', 'Trento Heat', 'Agusan Riptide',
            'Third Shot Co.', 'Erne Squad', 'Paddle Bous', 'Net Positive',
        ];

        $rows = [];
        foreach ($teams as $i => $name) {
            $rows[] = [
                'tournament_id' => $tId,
                'user_id'    => $players[$i * 2 % count($players)] ?? null,
                'partner_id' => $players[($i * 2 + 1) % count($players)] ?? null,
                'team_name'  => $name, 'seed' => $i + 1, 'status' => 'confirmed',
                'payment_status' => 'paid',
                'wins' => 0, 'losses' => 0, 'points' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        $this->db->table('tournament_participants')->insertBatch($rows);

        $ids = array_column(
            $this->db->table('tournament_participants')->select('id')->where('tournament_id', $tId)->orderBy('seed')->get()->getResultArray(),
            'id'
        );
        $courtIds = array_column($this->db->table('courts')->select('id')->orderBy('sort_order')->get()->getResultArray(), 'id');

        // Quarters (seeds 1v8, 2v7, 3v6, 4v5) → semis → final. Higher seed wins
        // except one upset, so the table is not a straight line.
        $qf = [[0, 7, 0], [1, 6, 1], [2, 5, 0], [3, 4, 0]]; // [a, b, winnerIndexWithinPair]
        $matches = [];
        $qfWinners = [];
        foreach ($qf as $m => [$a, $b, $w]) {
            $winner = $w === 0 ? $ids[$a] : $ids[$b];
            $qfWinners[] = $winner;
            $matches[] = [
                'tournament_id' => $tId, 'round' => 1, 'round_label' => 'Quarter-final', 'match_number' => $m + 1,
                'participant_a' => $ids[$a], 'participant_b' => $ids[$b],
                'court_id' => $courtIds[$m % count($courtIds)],
                'scheduled_at' => date('Y-m-d H:i:s', strtotime('-70 days 09:00 +' . ($m * 45) . ' minutes')),
                'score_a' => $w === 0 ? '11, 11' : '9, 8', 'score_b' => $w === 0 ? '7, 9' : '11, 11',
                'winner_id' => $winner, 'status' => 'completed',
                'created_at' => $now, 'updated_at' => $now,
            ];
        }

        $sfWinners = [$qfWinners[0], $qfWinners[2]];
        $matches[] = $this->match($tId, 2, 'Semi-final', 1, $qfWinners[0], $qfWinners[1], $qfWinners[0], '11, 11', '6, 9', $courtIds[0], '-70 days 13:00', $now);
        $matches[] = $this->match($tId, 2, 'Semi-final', 2, $qfWinners[2], $qfWinners[3], $qfWinners[2], '11, 9, 11', '8, 11, 7', $courtIds[1], '-70 days 14:00', $now);
        $matches[] = $this->match($tId, 3, 'Final', 1, $sfWinners[0], $sfWinners[1], $sfWinners[0], '11, 15', '8, 13', $courtIds[0], '-69 days 16:00', $now);

        $this->db->table('tournament_matches')->insertBatch($matches);

        // Roll the results back onto the participants so /rankings can order them.
        $standings = [
            $sfWinners[0]  => ['w' => 3, 'l' => 0, 'p' => 9,  'rank' => 1],
            $sfWinners[1]  => ['w' => 2, 'l' => 1, 'p' => 6,  'rank' => 2],
            $qfWinners[1]  => ['w' => 1, 'l' => 1, 'p' => 3,  'rank' => 3],
            $qfWinners[3]  => ['w' => 1, 'l' => 1, 'p' => 3,  'rank' => 4],
        ];
        foreach ($ids as $pid) {
            $s = $standings[$pid] ?? ['w' => 0, 'l' => 1, 'p' => 0, 'rank' => 5];
            $this->db->table('tournament_participants')->where('id', $pid)->update([
                'wins' => $s['w'], 'losses' => $s['l'], 'points' => $s['p'], 'final_rank' => $s['rank'],
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function match(int $tId, int $round, string $label, int $no, ?int $a, ?int $b, ?int $winner, string $sa, string $sb, int $court, string $when, string $now): array
    {
        return [
            'tournament_id' => $tId, 'round' => $round, 'round_label' => $label, 'match_number' => $no,
            'participant_a' => $a, 'participant_b' => $b, 'court_id' => $court,
            'scheduled_at' => date('Y-m-d H:i:s', strtotime($when)),
            'score_a' => $sa, 'score_b' => $sb, 'winner_id' => $winner, 'status' => 'completed',
            'created_at' => $now, 'updated_at' => $now,
        ];
    }

    /** Registrations already in for the two open tournaments. */
    private function seedOpenRegistrations(string $now): void
    {
        $open = $this->db->table('tournaments')->select('id, slug')->whereIn('slug', ['agusan-open-2026', 'kitchen-league-night-4'])->get()->getResultArray();
        $players = array_column(
            $this->db->table('users')->select('id')->where('role_id', $this->playerRoleId())->orderBy('id')->get()->getResultArray(),
            'id'
        );

        $names = [
            'agusan-open-2026' => ['Dink Responsibly', 'Trento Heat', 'Kitchen Cartel', 'Bantay Paddles', 'Riptide Two', 'Sunset Smash', 'The Ernes', 'Banana Cue Ballers', 'Third Shot Co.', 'Highway 3 Crew', 'Poblacion Pop', 'Net Positive'],
            'kitchen-league-night-4' => ['Soft Hands', 'Reset Kings', 'Dinkosaurus', 'Lob City Trento', 'Kitchen Sink', 'Baseline Bandits', 'Paddle Bous', 'Two Bounce Rule'],
        ];

        $rows = [];
        foreach ($open as $t) {
            foreach ($names[$t['slug']] as $i => $team) {
                $rows[] = [
                    'tournament_id' => (int) $t['id'],
                    'user_id'    => $players[($i * 2) % count($players)] ?? null,
                    'partner_id' => $players[($i * 2 + 1) % count($players)] ?? null,
                    'team_name'  => $team,
                    'contact_phone' => '+63 918 220 44' . str_pad((string) (70 + $i), 2, '0', STR_PAD_LEFT),
                    'seed' => $i + 1,
                    'status' => $i < 2 ? 'registered' : 'confirmed',
                    'payment_status' => $i < 2 ? 'unpaid' : 'paid',
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        $this->db->table('tournament_participants')->insertBatch($rows);
    }

    private function playerRoleId(): int
    {
        return (int) $this->db->table('roles')->select('id')->where('slug', 'player')->get()->getRow('id');
    }
}
