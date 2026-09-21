<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Operating model as of 2026-09-21: open around the clock, with two rates.
 *
 *   ₱150/hr  5:00 AM – 4:00 PM   (the base rate)
 *   ₱200/hr  4:00 PM – 5:00 AM   (the peak window, which crosses midnight)
 *
 * Open == close is how Availability expresses a 24-hour day, and 05:00 is the
 * cut: a booking at 2 AM belongs to the day that opened at 5 AM the morning
 * before, which is also where the rate changes back. Data only, safe on live.
 */
class DayNightPricing extends Migration
{
    private const DAY_RATE   = 150;
    private const NIGHT_RATE = 200;

    public function up(): void
    {
        $this->settings([
            'hours.open'         => '05:00',
            'hours.close'        => '05:00',
            'pricing.peak_start' => '16:00',
            'pricing.peak_end'   => '05:00',
        ]);

        $this->db->table('courts')->update(['hourly_rate' => self::DAY_RATE, 'peak_rate' => self::NIGHT_RATE]);
        $this->db->table('court_schedules')->update(['opens_at' => '05:00:00', 'closes_at' => '05:00:00']);
    }

    public function down(): void
    {
        // Back to 2026-09-17: 4 PM – 6 AM, flat ₱250, no peak window.
        $this->settings([
            'hours.open'         => '16:00',
            'hours.close'        => '06:00',
            'pricing.peak_start' => '16:00',
            'pricing.peak_end'   => '16:00',
        ]);

        $this->db->table('courts')->update(['hourly_rate' => 250, 'peak_rate' => 250]);
        $this->db->table('court_schedules')->update(['opens_at' => '16:00:00', 'closes_at' => '06:00:00']);
    }

    /** @param array<string, string> $values */
    private function settings(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->db->table('settings')->where('key', $key)
                ->update(['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }
}
