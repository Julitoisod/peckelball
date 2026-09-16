<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Court codes read as words everywhere: "CT-01" becomes "Court 1".
 *
 * The code is display data only (URLs use the slug, nothing parses the
 * "CT-" shape), so renaming the rows changes every screen at once. Free text
 * that mentions a court by code is rewritten too. Data only, safe on live.
 */
class SpellOutCourtCodes extends Migration
{
    private const TEXT_COLUMNS = [
        ['courts', 'description'],
        ['equipment', 'description'],
        ['announcements', 'title'],
        ['announcements', 'body'],
        ['court_maintenance', 'title'],
        ['court_maintenance', 'description'],
    ];

    public function up(): void
    {
        $this->rename(static fn (int $n) => ['CT-0' . $n, 'Court ' . $n]);
    }

    public function down(): void
    {
        $this->rename(static fn (int $n) => ['Court ' . $n, 'CT-0' . $n]);
    }

    private function rename(callable $pair): void
    {
        for ($n = 1; $n <= 9; $n++) {
            [$from, $to] = $pair($n);

            $this->db->table('courts')->where('code', $from)->update(['code' => $to]);

            // down() only reverses codes: "Court 1" also appears in ordinary prose.
            if (str_starts_with($from, 'CT-')) {
                foreach (self::TEXT_COLUMNS as [$table, $column]) {
                    $this->db->query("UPDATE `{$table}` SET `{$column}` = REPLACE(`{$column}`, ?, ?) WHERE `{$column}` LIKE ?", [$from, $to, "%{$from}%"]);
                }
            }
        }
    }
}
