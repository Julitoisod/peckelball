<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Let a player take as many hours as they want in one booking.
 *
 * The quick-book card now selects whole hours, so the three-hour ceiling was
 * the thing standing between "tap the hours you want" and the booking being
 * refused. Twelve hours is a guard against a mis-tap swallowing a whole day,
 * not a policy: change `booking.max_minutes` in Admin → Settings.
 */
class LongerSessions extends Migration
{
    public function up(): void
    {
        $this->setting('booking.max_minutes', '720');
    }

    public function down(): void
    {
        $this->setting('booking.max_minutes', '180');
    }

    private function setting(string $key, string $value): void
    {
        $this->db->table('settings')->where('key', $key)
            ->update(['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
    }
}
