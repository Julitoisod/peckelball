<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Staff, coaches and a believable player base for Trento.
 *
 * Every demo account uses the same password so the facility can hand the
 * system to staff during onboarding: see DEMO_PASSWORD. Change it before
 * this ever leaves a local machine.
 */
class UserSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Bernie2026!';

    public function run(): void
    {
        $now   = date('Y-m-d H:i:s');
        $hash  = password_hash(self::DEMO_PASSWORD, PASSWORD_DEFAULT);
        $roles = array_column($this->db->table('roles')->get()->getResultArray(), 'id', 'slug');

        $staff = [
            ['super_admin',  'System',   'Administrator', 'admin@berniepickleball.ph',   '+63 917 000 0001'],
            ['owner',        'Bernardo', 'Alcantara',     'bernie@berniepickleball.ph',  '+63 917 555 0100'],
            ['manager',      'Maricel',  'Dagohoy',       'maricel@berniepickleball.ph', '+63 917 555 0101'],
            ['receptionist', 'Joy',      'Ampoloquio',    'frontdesk@berniepickleball.ph', '+63 917 555 0102'],
            ['maintenance',  'Rogelio',  'Bactol',        'facilities@berniepickleball.ph', '+63 917 555 0103'],
        ];

        $coaches = [
            [
                'first' => 'Jomar', 'last' => 'Villarin', 'email' => 'jomar@berniepickleball.ph',
                'phone' => '+63 917 555 0110', 'years' => 9, 'rate' => 900, 'group' => 500,
                'rating' => 4.9, 'taught' => 612, 'photo' => 'photo-1642060603505-e716140d45d2',
                'headline' => 'Third-shot drops and transition-zone control',
                'specialties' => 'Third shot drop|Transition zone|Doubles strategy',
                'certs' => 'IPTPA Level II|PPR Certified Coach',
            ],
            [
                'first' => 'Rhea', 'last' => 'Malaluan', 'email' => 'rhea@berniepickleball.ph',
                'phone' => '+63 917 555 0111', 'years' => 6, 'rate' => 750, 'group' => 420,
                'rating' => 4.8, 'taught' => 430, 'photo' => 'photo-1628515304743-dbfe2122d6db',
                'headline' => 'Beginner-to-3.5 pathway and clean fundamentals',
                'specialties' => "Fundamentals|Serve mechanics|Women's clinics",
                'certs' => 'PPR Certified Coach|Basic Life Support',
            ],
            [
                'first' => 'Dennis', 'last' => 'Sarmiento', 'email' => 'dennis@berniepickleball.ph',
                'phone' => '+63 917 555 0112', 'years' => 12, 'rate' => 1100, 'group' => 620,
                'rating' => 5.0, 'taught' => 880, 'photo' => 'photo-1650969684814-cf406f7d2020',
                'headline' => 'Tournament prep, singles speed and shot selection',
                'specialties' => 'Singles|Tournament prep|Speed-ups',
                'certs' => 'IPTPA Level III|National Open bronze, 2024',
            ],
            [
                'first' => 'Kaye', 'last' => 'Bautista', 'email' => 'kaye@berniepickleball.ph',
                'phone' => '+63 917 555 0113', 'years' => 4, 'rate' => 650, 'group' => 380,
                'rating' => 4.7, 'taught' => 264, 'photo' => 'photo-1718006915613-bcb972cabdb1',
                'headline' => 'Junior development and school programs',
                'specialties' => 'Juniors|Footwork|Group clinics',
                'certs' => 'PPR Certified Coach|Youth Sports Safety',
            ],
        ];

        $players = [
            ['Aljon',   'Pacheco',   'aljon.pacheco@example.ph',   '+63 918 220 4471', 'intermediate', 3.75],
            ['Christine', 'Larazo',  'christine.larazo@example.ph','+63 918 220 4472', 'advanced',     4.25],
            ['Mark Kevin', 'Obenza', 'markkevin.obenza@example.ph','+63 918 220 4473', 'beginner',     2.50],
            ['Divine',  'Ambulo',    'divine.ambulo@example.ph',   '+63 918 220 4474', 'intermediate', 3.50],
            ['Renz',    'Cabañero',  'renz.cabanero@example.ph',   '+63 918 220 4475', 'advanced',     4.50],
            ['Michelle','Tabuada',   'michelle.tabuada@example.ph','+63 918 220 4476', 'intermediate', 3.25],
            ['Jayson',  'Empeño',    'jayson.empeno@example.ph',   '+63 918 220 4477', 'beginner',     2.75],
            ['Aira',    'Gomez',     'aira.gomez@example.ph',      '+63 918 220 4478', 'intermediate', 3.75],
            ['Paolo',   'Tandayu',   'paolo.tandayu@example.ph',   '+63 918 220 4479', 'advanced',     4.00],
            ['Shane',   'Bagares',   'shane.bagares@example.ph',   '+63 918 220 4480', 'beginner',     2.25],
            ['Demo',    'Player',    'player@berniepickleball.ph', '+63 918 220 4481', 'intermediate', 3.50],
        ];

        $rows = [];

        foreach ($staff as [$role, $first, $last, $email, $phone]) {
            $rows[] = $this->user($roles[$role], $first, $last, $email, $phone, $hash, $now);
        }

        foreach ($coaches as $c) {
            $rows[] = $this->user($roles['coach'], $c['first'], $c['last'], $c['email'], $c['phone'], $hash, $now, 'pro', 4.75);
        }

        foreach ($players as [$first, $last, $email, $phone, $skill, $dupr]) {
            $rows[] = $this->user($roles['player'], $first, $last, $email, $phone, $hash, $now, $skill, $dupr);
        }

        $this->db->table('users')->insertBatch($rows);

        // ---------------------------------------------------------- coach rows
        $userIds  = array_column($this->db->table('users')->select('id, email')->get()->getResultArray(), 'id', 'email');
        $coachRows = [];
        foreach ($coaches as $i => $c) {
            $coachRows[] = [
                'user_id'          => $userIds[$c['email']],
                'headline'         => $c['headline'],
                'bio'              => $this->coachBio($c['first'], $c['headline'], $c['years']),
                'specialties'      => $c['specialties'],
                'certifications'   => $c['certs'],
                'years_experience' => $c['years'],
                'hourly_rate'      => $c['rate'],
                'group_rate'       => $c['group'],
                'rating'           => $c['rating'],
                'sessions_taught'  => $c['taught'],
                'photo_path'       => $c['photo'],
                'is_accepting'     => 1,
                'sort_order'       => $i,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }
        $this->db->table('coaches')->insertBatch($coachRows);

        // ------------------------------------------------- coach availability
        $coachIds = array_column($this->db->table('coaches')->select('id')->orderBy('sort_order')->get()->getResultArray(), 'id');
        $avail    = [];
        foreach ($coachIds as $idx => $coachId) {
            // Weekdays for everyone, weekends for the two senior coaches.
            $days = $idx < 2 ? [1, 2, 3, 4, 5, 6, 0] : [1, 2, 3, 4, 5];
            foreach ($days as $d) {
                $avail[] = [
                    'coach_id'    => $coachId,
                    'day_of_week' => $d,
                    'starts_at'   => in_array($d, [0, 6], true) ? '07:00:00' : '15:00:00',
                    'ends_at'     => in_array($d, [0, 6], true) ? '18:00:00' : '21:00:00',
                ];
            }
        }
        $this->db->table('coach_availability')->insertBatch($avail);
    }

    private function user(int $roleId, string $first, string $last, string $email, string $phone, string $hash, string $now, string $skill = 'intermediate', ?float $dupr = null): array
    {
        return [
            'role_id'           => $roleId,
            'first_name'        => $first,
            'last_name'         => $last,
            'email'             => $email,
            'phone'             => $phone,
            'password_hash'     => $hash,
            'skill_level'       => $skill,
            'dupr_rating'       => $dupr,
            'address'           => 'Trento, Agusan del Sur',
            'status'            => 'active',
            'email_verified_at' => $now,
            'created_at'        => $now,
            'updated_at'        => $now,
        ];
    }

    private function coachBio(string $first, string $headline, int $years): string
    {
        return sprintf(
            'Coach %s has spent %d years on court and teaches out of Bernie Pickleball in Trento. '
            . 'Sessions are built around %s — video review on request, and a written plan you keep after every block of four.',
            $first,
            $years,
            lcfirst($headline)
        );
    }
}
