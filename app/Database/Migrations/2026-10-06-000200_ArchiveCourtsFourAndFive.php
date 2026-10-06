<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The facility runs three courts: Court 4 (Riverside) and Court 5 (Sunset) go.
 *
 * Archived, not deleted: `deleted_at` is set, which is what every query in the
 * app already filters on, so they vanish from the board, the booking form, the
 * facility map and the 3D hero — while the bookings, payments and reports that
 * reference them stay readable. A hard DELETE would orphan hundreds of rows.
 *
 * To bring one back: `UPDATE courts SET deleted_at = NULL WHERE code = 'Court 4'`.
 */
class ArchiveCourtsFourAndFive extends Migration
{
    private const CODES = ['Court 4', 'Court 5'];

    public function up(): void
    {
        $this->db->table('courts')
            ->whereIn('code', self::CODES)
            ->where('deleted_at', null)
            ->update(['deleted_at' => date('Y-m-d H:i:s'), 'status' => 'closed']);

        // Equipment copy that pointed at them by name would now name nothing.
        $this->db->query(
            "UPDATE `equipment` SET `description` = REPLACE(`description`, ?, ?) WHERE `description` LIKE ?",
            ['used on Court 4 and Court 5', 'used outdoors', '%used on Court 4 and Court 5%']
        );

        // Stored copy that counts the courts out loud. The views derive their
        // own count (see court_mix()), but these two are rows, not templates.
        $word = $this->countWord();
        foreach ([['settings', 'value'], ['testimonials', 'quote']] as [$table, $column]) {
            $this->db->query(
                "UPDATE `{$table}` SET `{$column}` = REPLACE(`{$column}`, ?, ?) WHERE `{$column}` LIKE ?",
                ['Five courts', ucfirst($word) . ' courts', '%Five courts%']
            );
        }
    }

    private function countWord(): string
    {
        $n = $this->db->table('courts')->where('deleted_at', null)->countAllResults();

        return ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'][$n] ?? (string) $n;
    }

    public function down(): void
    {
        $this->db->table('courts')
            ->whereIn('code', self::CODES)
            ->update(['deleted_at' => null, 'status' => 'active']);
    }
}
