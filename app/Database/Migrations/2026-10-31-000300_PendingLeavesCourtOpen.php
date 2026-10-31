<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A booking waiting for approval no longer holds its court: the slots are
 * written when the desk approves it. This frees the ones written before that
 * change. Only bookings with a GCash reference: older pending rows are left be.
 */
class PendingLeavesCourtOpen extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "DELETE bs FROM booking_slots bs
             JOIN bookings b ON b.id = bs.booking_id
             WHERE b.status = 'pending' AND b.gcash_reference IS NOT NULL"
        );
    }

    public function down(): void
    {
        // Nothing to put back: approving a booking writes its slots.
    }
}
