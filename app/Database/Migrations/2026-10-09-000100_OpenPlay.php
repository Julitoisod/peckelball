<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Open play: the facility opens a court for a few hours and anyone who signs
 * up pays a per-head fee.
 *
 * Two tables. A session is the window on one court, and it holds the court by
 * owning an ordinary `block` booking — that is what puts rows in
 * `booking_slots`, where the UNIQUE (court_id, slot_start) key already stops
 * a normal booking landing on the same hour, and the other way round. Closing
 * a session deletes that booking, and the slots cascade away with it, so the
 * court frees up.
 *
 * Participants are their own rows rather than bookings: the court is already
 * accounted for by the block, and what the desk needs per player is a name,
 * a number and whether they have paid.
 */
class OpenPlay extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'court_id'         => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            // Null while closed: the block is what reserves the hours.
            'booking_id'       => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'session_date'     => ['type' => 'DATE'],
            'start_time'       => ['type' => 'TIME'],
            'end_time'         => ['type' => 'TIME'],
            'duration_minutes' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'fee'              => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'capacity'         => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 12],
            'status'           => ['type' => 'ENUM', 'constraint' => ['open', 'closed'], 'default' => 'open'],
            'title'            => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'notes'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by'       => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['session_date', 'status']);
        $this->forge->addForeignKey('court_id', 'courts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('booking_id', 'bookings', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('open_play_sessions');

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'session_id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'user_id'        => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 120],
            'phone'          => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'amount'         => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'payment_method' => ['type' => 'ENUM', 'constraint' => ['cash', 'gcash'], 'default' => 'cash'],
            'payment_status' => ['type' => 'ENUM', 'constraint' => ['unpaid', 'paid'], 'default' => 'unpaid'],
            'created_by'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('session_id');
        $this->forge->addForeignKey('session_id', 'open_play_sessions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('open_play_participants');

        $this->permissions();
    }

    public function down(): void
    {
        $this->forge->dropTable('open_play_participants', true);
        $this->forge->dropTable('open_play_sessions', true);

        $ids = array_column(
            $this->db->table('permissions')->select('id')->whereIn('slug', ['openplay.view', 'openplay.manage'])->get()->getResultArray(),
            'id'
        );

        if ($ids !== []) {
            $this->db->table('role_permissions')->whereIn('permission_id', $ids)->delete();
            $this->db->table('permissions')->whereIn('id', $ids)->delete();
        }
    }

    /**
     * Two permissions, granted to the roles that already run the front desk.
     * Without this the screen exists and nobody can open it — see the role
     * access audit: a permission that gates nothing is worse than none.
     */
    private function permissions(): void
    {
        $rows = [
            ['openplay.view', 'View open play sessions and sign-ups', 'bookings'],
            ['openplay.manage', 'Schedule open play and register participants', 'bookings'],
        ];

        foreach ($rows as [$slug, $name, $module]) {
            if ($this->db->table('permissions')->where('slug', $slug)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('permissions')->insert([
                'slug' => $slug, 'name' => $name, 'module' => $module,
            ]);
            $permissionId = (int) $this->db->insertID();

            $roleIds = array_column(
                $this->db->table('roles')->select('id')
                    ->whereIn('slug', ['super_admin', 'owner', 'manager', 'receptionist'])
                    ->get()->getResultArray(),
                'id'
            );

            foreach ($roleIds as $roleId) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => $roleId, 'permission_id' => $permissionId,
                ]);
            }
        }
    }
}
