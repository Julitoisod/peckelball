<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Operating model as of 2026-10-31: open 7:00 AM to 1:00 AM, closed in between.
 * The rates stay as they were, and the last bookable hour is 12:00 AM.
 *
 *   ₱150/hr  7:00 AM – 4:00 PM
 *   ₱200/hr  4:00 PM – 1:00 AM
 *
 * Data only, safe on live. A booking already taken for the closed hours stays
 * on the books: nothing here cancels or moves it.
 */
class ClosedOneToSevenAm extends Migration
{
    public function up(): void
    {
        $this->settings(['hours.open' => '07:00', 'hours.close' => '01:00', 'pricing.peak_end' => '01:00']);
        $this->db->table('court_schedules')->update(['opens_at' => '07:00:00', 'closes_at' => '01:00:00']);
    }

    public function down(): void
    {
        $this->settings(['hours.open' => '05:00', 'hours.close' => '05:00', 'pricing.peak_end' => '05:00']);
        $this->db->table('court_schedules')->update(['opens_at' => '05:00:00', 'closes_at' => '05:00:00']);
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
