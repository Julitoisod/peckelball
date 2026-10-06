<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The facility is called **Go Paddle**, not Bernie Pickleball.
 *
 * The name lives in `settings.facility.name` and inside free text the client
 * can edit (court blurbs, coach bios, product and tournament names), so this
 * rewrites both. Deliberately untouched:
 *
 *   - account emails and `facility.email` (@berniepickleball.ph) — changing a
 *     user's email changes their login; the mailbox and domain are the
 *     client's to move
 *   - "Bernardo “Bernie” Alcantara", the founder, who keeps his name
 *   - booking references already issued as BP-…
 *
 * Data only, safe on live.
 */
class RenameToGoPaddle extends Migration
{
    private const OLD_FULL  = 'Bernie Pickleball';
    private const OLD_SHORT = 'Bernie';
    private const NEW       = 'Go Paddle';

    /** @var list<array{0:string, 1:string}> table, column */
    private const TEXT_COLUMNS = [
        ['courts', 'name'],
        ['courts', 'description'],
        ['coaches', 'headline'],
        ['coaches', 'bio'],
        ['announcements', 'title'],
        ['announcements', 'body'],
        ['tournaments', 'name'],
        ['tournaments', 'slug'],
        ['tournaments', 'description'],
        ['products', 'name'],
        ['products', 'slug'],
        ['products', 'brand'],
        ['products', 'description'],
        ['equipment', 'name'],
        ['equipment', 'description'],
        ['membership_plans', 'name'],
        ['membership_plans', 'tagline'],
        ['membership_plans', 'perks'],
        ['testimonials', 'body'],
    ];

    public function up(): void
    {
        $this->rename(self::OLD_FULL, self::OLD_SHORT, self::NEW);
    }

    public function down(): void
    {
        // Only the full name comes back: "Bernie" alone cannot be recovered
        // from "Go Paddle" without guessing which mentions were short.
        $this->db->table('settings')->where('key', 'facility.name')
            ->update(['value' => self::OLD_FULL, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->replaceEverywhere(self::NEW, self::OLD_FULL);
    }

    private function rename(string $full, string $short, string $new): void
    {
        $this->db->table('settings')->where('key', 'facility.name')
            ->update(['value' => $new, 'updated_at' => date('Y-m-d H:i:s')]);

        // Longest first, so "Bernie Pickleball" never becomes "Go Paddle Pickleball".
        $this->replaceEverywhere($full, $new);
        $this->replaceEverywhere($short, $new);
        $this->replaceEverywhere(strtolower($short) . '-', 'go-paddle-');
    }

    private function replaceEverywhere(string $from, string $to): void
    {
        foreach (self::TEXT_COLUMNS as [$table, $column]) {
            if (! $this->db->fieldExists($column, $table)) {
                continue;
            }

            $this->db->query(
                "UPDATE `{$table}` SET `{$column}` = REPLACE(`{$column}`, ?, ?) WHERE `{$column}` LIKE ?",
                [$from, $to, "%{$from}%"]
            );
        }
    }
}
