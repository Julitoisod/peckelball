<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Money and the booking ledger.
 *
 * Note `booking_slots`: bookings themselves store a start/end range, but the
 * authoritative occupancy record is one row per 30-minute slot with a UNIQUE
 * key on (court_id, slot_start). That makes overlapping reservations a
 * database-level impossibility rather than an application-level promise —
 * a UNIQUE on (court_id, date, start_time) would only catch identical starts
 * and would happily let a 60-minute 18:00 booking sit on top of a 30-minute
 * 18:30 one.
 */
class CreateCommerceTables extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------ membership_plans
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'code'                => ['type' => 'VARCHAR', 'constraint' => 24],
            'name'                => ['type' => 'VARCHAR', 'constraint' => 80],
            'tagline'             => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'price'               => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'billing_period'      => ['type' => 'ENUM', 'constraint' => ['monthly', 'quarterly', 'annual'], 'default' => 'monthly'],
            'court_credit_hours'  => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'discount_percent'    => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'guest_passes'        => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'advance_booking_days'=> ['type' => 'TINYINT', 'unsigned' => true, 'default' => 7],
            'perks'               => ['type' => 'TEXT', 'null' => true],
            'is_featured'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order'          => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('membership_plans', true, ['ENGINE' => 'InnoDB']);

        // ----------------------------------------------------------- memberships
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'           => ['type' => 'INT', 'unsigned' => true],
            'plan_id'           => ['type' => 'INT', 'unsigned' => true],
            'reference'         => ['type' => 'VARCHAR', 'constraint' => 24],
            'starts_on'         => ['type' => 'DATE'],
            'ends_on'           => ['type' => 'DATE'],
            'status'            => ['type' => 'ENUM', 'constraint' => ['pending', 'active', 'expired', 'cancelled'], 'default' => 'pending'],
            'credits_remaining' => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'auto_renew'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('reference');
        $this->forge->addKey(['user_id', 'status']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('plan_id', 'membership_plans', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('memberships', true, ['ENGINE' => 'InnoDB']);

        // ----------------------------------------------------------- promo_codes
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'code'           => ['type' => 'VARCHAR', 'constraint' => 32],
            'description'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'discount_type'  => ['type' => 'ENUM', 'constraint' => ['percent', 'fixed'], 'default' => 'percent'],
            'discount_value' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'min_amount'     => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'max_uses'       => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'used_count'     => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'starts_on'      => ['type' => 'DATE', 'null' => true],
            'ends_on'        => ['type' => 'DATE', 'null' => true],
            'is_active'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('promo_codes', true, ['ENGINE' => 'InnoDB']);

        // -------------------------------------------------------------- bookings
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'reference'           => ['type' => 'VARCHAR', 'constraint' => 20],
            // NULL user_id = front-desk walk-in captured under guest_* fields.
            'user_id'             => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'court_id'            => ['type' => 'INT', 'unsigned' => true],
            'coach_id'            => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'booking_date'        => ['type' => 'DATE'],
            'start_time'          => ['type' => 'TIME'],
            'end_time'            => ['type' => 'TIME'],
            'duration_minutes'    => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 60],
            'player_count'        => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 2],
            'booking_type'        => ['type' => 'ENUM', 'constraint' => ['online', 'walk_in', 'coaching', 'tournament', 'block'], 'default' => 'online'],
            'status'              => ['type' => 'ENUM', 'constraint' => ['pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'no_show'], 'default' => 'pending'],
            'court_subtotal'      => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'equipment_subtotal'  => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'coaching_subtotal'   => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'discount_amount'     => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'promo_code_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'total_amount'        => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'payment_method'      => ['type' => 'ENUM', 'constraint' => ['cash', 'gcash', 'maya', 'card', 'bank_transfer', 'credits'], 'default' => 'cash'],
            'payment_status'      => ['type' => 'ENUM', 'constraint' => ['unpaid', 'partial', 'paid', 'refunded'], 'default' => 'unpaid'],
            'guest_name'          => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'guest_phone'         => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'guest_email'         => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'notes'               => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'qr_token'            => ['type' => 'VARCHAR', 'constraint' => 40],
            'checked_in_at'       => ['type' => 'DATETIME', 'null' => true],
            'cancelled_at'        => ['type' => 'DATETIME', 'null' => true],
            'cancellation_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by'          => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('reference');
        $this->forge->addUniqueKey('qr_token');
        $this->forge->addKey(['court_id', 'booking_date']);
        $this->forge->addKey(['booking_date', 'status']);
        $this->forge->addKey(['user_id', 'booking_date']);
        $this->forge->addForeignKey('court_id', 'courts', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('coach_id', 'coaches', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('promo_code_id', 'promo_codes', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('bookings', true, ['ENGINE' => 'InnoDB']);

        // --------------------------------------------------------- booking_slots
        // The double-booking guard. One row per occupied 30-minute slot.
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'booking_id' => ['type' => 'INT', 'unsigned' => true],
            'court_id'   => ['type' => 'INT', 'unsigned' => true],
            'slot_start' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['court_id', 'slot_start']);
        $this->forge->addKey('booking_id');
        $this->forge->addForeignKey('booking_id', 'bookings', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('court_id', 'courts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('booking_slots', true, ['ENGINE' => 'InnoDB']);

        // ----------------------------------------------------- booking_equipment
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'booking_id'   => ['type' => 'INT', 'unsigned' => true],
            'equipment_id' => ['type' => 'INT', 'unsigned' => true],
            'qty'          => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 1],
            'rate'         => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'hours'        => ['type' => 'DECIMAL', 'constraint' => '4,2', 'default' => 1],
            'subtotal'     => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'returned_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('booking_id');
        $this->forge->addForeignKey('booking_id', 'bookings', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('equipment_id', 'equipment', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('booking_equipment', true, ['ENGINE' => 'InnoDB']);

        // ---------------------------------------------------- coaching_sessions
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'booking_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'coach_id'     => ['type' => 'INT', 'unsigned' => true],
            'user_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'session_type' => ['type' => 'ENUM', 'constraint' => ['private', 'semi_private', 'group', 'clinic'], 'default' => 'private'],
            'session_date' => ['type' => 'DATE'],
            'start_time'   => ['type' => 'TIME'],
            'end_time'     => ['type' => 'TIME'],
            'participants' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 1],
            'rate'         => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'status'       => ['type' => 'ENUM', 'constraint' => ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'], 'default' => 'pending'],
            'goals'        => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'coach_notes'  => ['type' => 'TEXT', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['coach_id', 'session_date']);
        $this->forge->addForeignKey('booking_id', 'bookings', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('coach_id', 'coaches', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('coaching_sessions', true, ['ENGINE' => 'InnoDB']);

        // -------------------------------------------------------------- payments
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'reference'     => ['type' => 'VARCHAR', 'constraint' => 24],
            'booking_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'membership_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'user_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'purpose'       => ['type' => 'ENUM', 'constraint' => ['booking', 'membership', 'tournament', 'retail', 'rental', 'other'], 'default' => 'booking'],
            'method'        => ['type' => 'ENUM', 'constraint' => ['cash', 'gcash', 'maya', 'card', 'bank_transfer', 'credits'], 'default' => 'cash'],
            'amount'        => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'status'        => ['type' => 'ENUM', 'constraint' => ['pending', 'paid', 'failed', 'refunded'], 'default' => 'pending'],
            'paid_at'       => ['type' => 'DATETIME', 'null' => true],
            'received_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'notes'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('reference');
        $this->forge->addKey(['status', 'paid_at']);
        $this->forge->addForeignKey('booking_id', 'bookings', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('membership_id', 'memberships', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('received_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('payments', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        foreach ([
            'payments', 'coaching_sessions', 'booking_equipment', 'booking_slots',
            'bookings', 'promo_codes', 'memberships', 'membership_plans',
        ] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
