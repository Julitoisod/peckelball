<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Operating model as of 2026-09-17: open 4:00 PM to 6:00 AM (the session runs
 * past midnight), booked by the hour, one flat ₱250/hr on every court.
 *
 * Data only — UPDATEs, no schema change — so it is safe to run on the live
 * database without touching bookings. Existing 30-minute booking_slots rows
 * still block the hour they fall inside (Availability::occupiedSlots snaps
 * them onto the grid).
 */
class OvernightHourlyPricing extends Migration
{
    private const RATE = 250;

    public function up(): void
    {
        $this->settings([
            'hours.open'           => '16:00',
            'hours.close'          => '06:00',
            'booking.slot_minutes' => '60',
            'booking.min_minutes'  => '60',
            // Start == end: no peak window, one rate all night.
            'pricing.peak_start'   => '16:00',
            'pricing.peak_end'     => '16:00',
        ]);

        $this->db->table('courts')->update(['hourly_rate' => self::RATE, 'peak_rate' => self::RATE]);
        $this->db->table('court_schedules')->update(['opens_at' => '16:00:00', 'closes_at' => '06:00:00']);
    }

    public function down(): void
    {
        $this->settings([
            'hours.open'           => '06:00',
            'hours.close'          => '22:00',
            'booking.slot_minutes' => '30',
            'booking.min_minutes'  => '60',
            'pricing.peak_start'   => '17:00',
            'pricing.peak_end'     => '21:00',
        ]);

        // ponytail: per-court rates before this migration are not restored;
        // re-enter them on Admin → Courts if rolling back.
        $this->db->table('court_schedules')->update(['opens_at' => '06:00:00', 'closes_at' => '22:00:00']);
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
