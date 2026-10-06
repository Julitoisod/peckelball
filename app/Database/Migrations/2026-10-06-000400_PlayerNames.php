<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Who is actually playing, not just who booked.
 *
 * Pipe-delimited in one column, the way the rest of this schema carries
 * multi-values (see the `pipes()` helper): a booking's players are read and
 * written together, never queried one by one, so a second table would buy
 * nothing. The first name is the contact — it is also kept in `guest_name`
 * for a guest booking, which is what every existing screen reads.
 */
class PlayerNames extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('bookings', [
            'player_names' => [
                'type'    => 'TEXT',
                'null'    => true,
                'after'   => 'player_count',
                'comment' => 'Pipe-delimited player names; the first is the contact.',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('bookings', 'player_names');
    }
}
