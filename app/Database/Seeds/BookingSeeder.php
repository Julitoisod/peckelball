<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Three weeks of plausible traffic (-7 days to +14) so the availability board,
 * the calendar and every report open with real shape instead of an empty grid.
 *
 * Occupancy follows the pattern the facility actually sees: a busy 06:00-09:00
 * block before work, a dead middle of the day, and a hard 17:00-21:00 peak.
 * Weekends lift the daytime and flatten the evening spike.
 */
class BookingSeeder extends Seeder
{
    private const SLOT_MINUTES = 30;
    private const OPEN_HOUR    = 6;
    private const CLOSE_HOUR   = 22;

    /** Chance a free slot starts a booking, by hour of day. */
    private const DEMAND = [
        6 => 0.42, 7 => 0.46, 8 => 0.34, 9 => 0.18, 10 => 0.12, 11 => 0.10,
        12 => 0.08, 13 => 0.10, 14 => 0.14, 15 => 0.26, 16 => 0.38,
        17 => 0.72, 18 => 0.80, 19 => 0.78, 20 => 0.64, 21 => 0.30,
    ];

    private int $seq = 0;

    public function run(): void
    {
        // Deterministic: re-seeding produces the same board, which makes the
        // screenshots and the reports reproducible.
        mt_srand(20260804);

        $now      = date('Y-m-d H:i:s');
        $today    = date('Y-m-d');
        $courts   = $this->db->table('courts')->select('id, hourly_rate, peak_rate')->orderBy('sort_order')->get()->getResultArray();
        $players  = $this->db->table('users')->select('users.id')
            ->join('roles', 'roles.id = users.role_id')
            ->where('roles.slug', 'player')->get()->getResultArray();
        $playerIds = array_column($players, 'id');
        $coaches   = array_column($this->db->table('coaches')->select('id, hourly_rate')->get()->getResultArray(), 'hourly_rate', 'id');
        $coachIds  = array_keys($coaches);
        $equipment = $this->db->table('equipment')->select('id, rental_rate_hourly')->whereIn('category', ['paddle', 'ball'])->get()->getResultArray();
        $deskId    = (int) $this->db->table('users')->select('id')->where('email', 'frontdesk@berniepickleball.ph')->get()->getRow('id');

        $bookings = [];
        $meta     = []; // parallel array: extra rows keyed by booking index

        for ($dayOffset = -7; $dayOffset <= 14; $dayOffset++) {
            $date      = date('Y-m-d', strtotime("{$dayOffset} days"));
            $dow       = (int) date('w', strtotime($date));
            $isWeekend = $dow === 0 || $dow === 6;

            foreach ($courts as $court) {
                $cursor = self::OPEN_HOUR * 60;
                $endOfDay = self::CLOSE_HOUR * 60;

                while ($cursor < $endOfDay) {
                    $hour   = intdiv($cursor, 60);
                    $demand = self::DEMAND[$hour] ?? 0.1;
                    if ($isWeekend) {
                        $demand = $hour >= 17 ? $demand * 0.8 : min(0.85, $demand * 1.9);
                    }

                    if (mt_rand() / mt_getrandmax() > $demand) {
                        $cursor += self::SLOT_MINUTES;
                        continue;
                    }

                    $duration = [60, 60, 90, 120][mt_rand(0, 3)];
                    if ($cursor + $duration > $endOfDay) {
                        $duration = $endOfDay - $cursor;
                        if ($duration < 60) {
                            break;
                        }
                    }

                    $bookings[] = $this->buildBooking(
                        $court, $date, $cursor, $duration, $dayOffset, $today,
                        $playerIds, $coachIds, $coaches, $deskId, $now, $isWeekend, $equipment, $meta
                    );

                    $cursor += $duration;
                }
            }
        }

        $this->db->table('bookings')->insertBatch($bookings);

        // ------------------------------------------------------ dependent rows
        $stored = $this->db->table('bookings')->select('id, reference, court_id, booking_date, start_time, duration_minutes, coach_id, user_id, total_amount, status, payment_status, payment_method, created_at')->get()->getResultArray();
        $byRef  = array_column($stored, null, 'reference');

        $slots = [];
        $equipRows = [];
        $sessions = [];
        $payments = [];
        $payNo = 0;

        foreach ($stored as $b) {
            $steps = intdiv((int) $b['duration_minutes'], self::SLOT_MINUTES);
            $start = strtotime($b['booking_date'] . ' ' . $b['start_time']);
            for ($i = 0; $i < $steps; $i++) {
                $slots[] = [
                    'booking_id' => (int) $b['id'],
                    'court_id'   => (int) $b['court_id'],
                    'slot_start' => date('Y-m-d H:i:s', $start + $i * self::SLOT_MINUTES * 60),
                ];
            }

            $m = $meta[$b['reference']] ?? [];

            foreach ($m['equipment'] ?? [] as $e) {
                $equipRows[] = [
                    'booking_id'   => (int) $b['id'],
                    'equipment_id' => $e['id'],
                    'qty'          => $e['qty'],
                    'rate'         => $e['rate'],
                    'hours'        => $b['duration_minutes'] / 60,
                    'subtotal'     => $e['subtotal'],
                ];
            }

            if (! empty($b['coach_id'])) {
                $sessions[] = [
                    'booking_id'   => (int) $b['id'],
                    'coach_id'     => (int) $b['coach_id'],
                    'user_id'      => $b['user_id'] ? (int) $b['user_id'] : null,
                    'session_type' => $m['session_type'] ?? 'private',
                    'session_date' => $b['booking_date'],
                    'start_time'   => $b['start_time'],
                    'end_time'     => date('H:i:s', $start + (int) $b['duration_minutes'] * 60),
                    'participants' => $m['participants'] ?? 1,
                    'rate'         => $coaches[(int) $b['coach_id']] ?? 0,
                    'status'       => in_array($b['status'], ['completed', 'checked_in'], true) ? 'completed' : 'confirmed',
                    'goals'        => $m['goals'] ?? null,
                    'created_at'   => $b['created_at'],
                    'updated_at'   => $now,
                ];
            }

            if ($b['payment_status'] === 'paid') {
                $payments[] = [
                    'reference'   => 'PY-' . date('ymd', strtotime($b['booking_date'])) . '-' . str_pad((string) ++$payNo, 5, '0', STR_PAD_LEFT),
                    'booking_id'  => (int) $b['id'],
                    'user_id'     => $b['user_id'] ? (int) $b['user_id'] : null,
                    'purpose'     => 'booking',
                    'method'      => $b['payment_method'],
                    'amount'      => $b['total_amount'],
                    'status'      => 'paid',
                    'paid_at'     => $b['created_at'],
                    'received_by' => $b['payment_method'] === 'cash' ? $deskId : null,
                    'created_at'  => $b['created_at'],
                    'updated_at'  => $now,
                ];
            }
        }

        foreach (array_chunk($slots, 500) as $chunk) {
            $this->db->table('booking_slots')->insertBatch($chunk);
        }
        if ($equipRows !== []) {
            foreach (array_chunk($equipRows, 500) as $chunk) {
                $this->db->table('booking_equipment')->insertBatch($chunk);
            }
        }
        if ($sessions !== []) {
            $this->db->table('coaching_sessions')->insertBatch($sessions);
        }
        if ($payments !== []) {
            foreach (array_chunk($payments, 500) as $chunk) {
                $this->db->table('payments')->insertBatch($chunk);
            }
        }

        $this->seedMemberships($now);
        $this->seedNotifications($now, $byRef);
    }

    /**
     * @param array<string, mixed> $court
     * @param list<int>            $playerIds
     * @param list<int>            $coachIds
     * @param array<int, float>    $coachRates
     * @param list<array>          $equipment
     * @param array                $meta       by-reference sidecar for child rows
     *
     * @return array<string, mixed>
     */
    private function buildBooking(array $court, string $date, int $startMinutes, int $duration, int $dayOffset, string $today, array $playerIds, array $coachIds, array $coachRates, int $deskId, string $now, bool $isWeekend, array $equipment, array &$meta): array
    {
        $hour      = intdiv($startMinutes, 60);
        $isPeak    = $hour >= 17 && $hour < 21;
        $rate      = (float) ($isPeak ? $court['peak_rate'] : $court['hourly_rate']);
        $hours     = $duration / 60;
        $courtCost = round($rate * $hours, 2);

        $roll      = mt_rand(1, 100);
        $isWalkIn  = $roll <= 22;
        $hasCoach  = ! $isWalkIn && $roll > 88 && $coachIds !== [];
        $userId    = $isWalkIn ? null : $playerIds[array_rand($playerIds)];

        $coachId   = $hasCoach ? $coachIds[array_rand($coachIds)] : null;
        $coachCost = $hasCoach ? round(($coachRates[$coachId] ?? 0) * $hours, 2) : 0.0;

        // Equipment on roughly a third of bookings.
        $equipCost = 0.0;
        $equipPicks = [];
        if (mt_rand(1, 100) <= 34 && $equipment !== []) {
            $pick = $equipment[array_rand($equipment)];
            $qty  = mt_rand(1, 4);
            $sub  = round((float) $pick['rental_rate_hourly'] * $qty * $hours, 2);
            $equipPicks[] = ['id' => (int) $pick['id'], 'qty' => $qty, 'rate' => (float) $pick['rental_rate_hourly'], 'subtotal' => $sub];
            $equipCost = $sub;
        }

        $subtotal = $courtCost + $coachCost + $equipCost;
        $discount = mt_rand(1, 100) <= 18 ? round($subtotal * 0.15, 2) : 0.0;
        $total    = round($subtotal - $discount, 2);

        $reference = 'BP-' . date('ymd', strtotime($date)) . '-' . str_pad((string) ++$this->seq, 4, '0', STR_PAD_LEFT);
        $startTime = sprintf('%02d:%02d:00', intdiv($startMinutes, 60), $startMinutes % 60);
        $endTime   = sprintf('%02d:%02d:00', intdiv($startMinutes + $duration, 60), ($startMinutes + $duration) % 60);

        [$status, $paymentStatus, $checkedInAt] = $this->lifecycle($date, $startTime, $dayOffset, $today);

        $methods = $isWalkIn ? ['cash', 'cash', 'gcash'] : ['gcash', 'gcash', 'maya', 'card', 'cash'];
        $method  = $methods[array_rand($methods)];

        $createdAt = date('Y-m-d H:i:s', strtotime($date . ' ' . $startTime) - mt_rand(3600, 6 * 86400));

        if ($hasCoach) {
            $meta[$reference] = [
                'session_type' => ['private', 'private', 'semi_private', 'group'][mt_rand(0, 3)],
                'participants' => mt_rand(1, 4),
                'goals'        => ['Third shot consistency', 'Serve depth and placement', 'Kitchen footwork', 'Match play under pressure'][mt_rand(0, 3)],
            ];
        }
        if ($equipPicks !== []) {
            $meta[$reference]['equipment'] = $equipPicks;
        }

        $guestNames = ['Walk-in — Nognog T.', 'Walk-in — Ate Belen', 'Walk-in — Sir Roldan', 'Walk-in — Kuya Jhun', 'Walk-in — Ma. Fe D.', 'Walk-in — Boyet A.'];

        return [
            'reference'          => $reference,
            'user_id'            => $userId,
            'court_id'           => (int) $court['id'],
            'coach_id'           => $coachId,
            'booking_date'       => $date,
            'start_time'         => $startTime,
            'end_time'           => $endTime,
            'duration_minutes'   => $duration,
            'player_count'       => [2, 2, 4, 4, 4, 3][mt_rand(0, 5)],
            'booking_type'       => $hasCoach ? 'coaching' : ($isWalkIn ? 'walk_in' : 'online'),
            'status'             => $status,
            'court_subtotal'     => $courtCost,
            'equipment_subtotal' => $equipCost,
            'coaching_subtotal'  => $coachCost,
            'discount_amount'    => $discount,
            'promo_code_id'      => null,
            'total_amount'       => $total,
            'payment_method'     => $method,
            'payment_status'     => $paymentStatus,
            'guest_name'         => $isWalkIn ? $guestNames[array_rand($guestNames)] : null,
            'guest_phone'        => $isWalkIn ? '+63 9' . mt_rand(10, 99) . ' ' . mt_rand(100, 999) . ' ' . mt_rand(1000, 9999) : null,
            'guest_email'        => null,
            'notes'              => null,
            'qr_token'           => bin2hex(random_bytes(16)),
            'checked_in_at'      => $checkedInAt,
            'cancelled_at'       => $status === 'cancelled' ? $createdAt : null,
            'cancellation_reason' => $status === 'cancelled' ? 'Player cancelled — weather' : null,
            'created_by'         => $isWalkIn ? $deskId : null,
            'created_at'         => $createdAt,
            'updated_at'         => $now,
        ];
    }

    /** @return array{0:string, 1:string, 2:?string} status, payment status, checked-in timestamp */
    private function lifecycle(string $date, string $startTime, int $dayOffset, string $today): array
    {
        $startTs = strtotime($date . ' ' . $startTime);

        if ($dayOffset < 0 || ($date === $today && $startTs < time())) {
            $roll = mt_rand(1, 100);
            if ($roll <= 6) {
                return ['no_show', 'unpaid', null];
            }
            if ($roll <= 10) {
                return ['cancelled', 'refunded', null];
            }
            if ($date === $today && $startTs + 3600 > time()) {
                return ['checked_in', 'paid', date('Y-m-d H:i:s', $startTs)];
            }

            return ['completed', 'paid', date('Y-m-d H:i:s', $startTs)];
        }

        $roll = mt_rand(1, 100);
        if ($roll <= 24) {
            return ['pending', 'unpaid', null];
        }

        return ['confirmed', $roll <= 70 ? 'paid' : 'unpaid', null];
    }

    private function seedMemberships(string $now): void
    {
        $plans = array_column($this->db->table('membership_plans')->select('id, code, court_credit_hours, billing_period')->get()->getResultArray(), null, 'code');
        $players = array_column(
            $this->db->table('users')->select('users.id')->join('roles', 'roles.id = users.role_id')->where('roles.slug', 'player')->get()->getResultArray(),
            'id'
        );

        $assign = ['BASELINE', 'RALLY', 'BASELINE', 'ALLCOURT', 'FOUNDER', 'RALLY', 'BASELINE'];
        $rows   = [];

        foreach ($assign as $i => $code) {
            if (! isset($players[$i])) {
                break;
            }
            $plan   = $plans[$code];
            $months = ['monthly' => 1, 'quarterly' => 3, 'annual' => 12][$plan['billing_period']];
            $start  = date('Y-m-d', strtotime('-' . mt_rand(5, 80) . ' days'));

            $rows[] = [
                'user_id'   => (int) $players[$i],
                'plan_id'   => (int) $plan['id'],
                'reference' => 'MB-' . str_pad((string) (1000 + $i), 5, '0', STR_PAD_LEFT),
                'starts_on' => $start,
                'ends_on'   => date('Y-m-d', strtotime($start . " +{$months} months")),
                'status'    => 'active',
                'credits_remaining' => (int) round($plan['court_credit_hours'] * (mt_rand(20, 90) / 100)),
                'auto_renew' => mt_rand(0, 1),
                'created_at' => $now, 'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            $this->db->table('memberships')->insertBatch($rows);
        }
    }

    /** @param array<string, array> $byRef */
    private function seedNotifications(string $now, array $byRef): void
    {
        $demo = $this->db->table('users')->select('id')->where('email', 'player@berniepickleball.ph')->get()->getRow('id');
        if (! $demo) {
            return;
        }

        $this->db->table('notifications')->insertBatch([
            ['user_id' => (int) $demo, 'type' => 'booking', 'title' => 'Booking confirmed — Center Court', 'body' => 'Tomorrow, 18:30-20:00. Show the QR at the desk.', 'link' => '/account/bookings', 'read_at' => null, 'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))],
            ['user_id' => (int) $demo, 'type' => 'tournament', 'title' => 'Agusan Open registration closes in 18 days', 'body' => 'Twelve of thirty-two slots are gone.', 'link' => '/tournaments/agusan-open-2026', 'read_at' => null, 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))],
            ['user_id' => (int) $demo, 'type' => 'maintenance', 'title' => 'South Court closed on the 9th', 'body' => 'Resurfacing 07:00-12:00. Your booking was moved to Sunset Court.', 'link' => '/courts/riverside-court', 'read_at' => date('Y-m-d H:i:s', strtotime('-20 hours')), 'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))],
        ]);
    }
}
