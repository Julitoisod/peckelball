<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Physical assets: courts, their operating hours, maintenance, rental
 * equipment and retail stock.
 */
class CreateFacilityTables extends Migration
{
    public function up(): void
    {
        // --------------------------------------------------------------- courts
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'code'         => ['type' => 'VARCHAR', 'constraint' => 12],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 80],
            'slug'         => ['type' => 'VARCHAR', 'constraint' => 90],
            'surface_type' => ['type' => 'ENUM', 'constraint' => ['indoor', 'covered', 'outdoor'], 'default' => 'covered'],
            'surface'      => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'status'       => ['type' => 'ENUM', 'constraint' => ['active', 'maintenance', 'closed'], 'default' => 'active'],
            'hourly_rate'  => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'peak_rate'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'capacity'     => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 4],
            'has_lights'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'description'  => ['type' => 'TEXT', 'null' => true],
            'image_path'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            // Facility-map / 3D-scene placement, in metres from the site origin.
            'map_x'        => ['type' => 'DECIMAL', 'constraint' => '6,2', 'default' => 0],
            'map_y'        => ['type' => 'DECIMAL', 'constraint' => '6,2', 'default' => 0],
            'sort_order'   => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey('status');
        $this->forge->createTable('courts', true, ['ENGINE' => 'InnoDB']);

        // ------------------------------------------------------ court_schedules
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'court_id'     => ['type' => 'INT', 'unsigned' => true],
            // 0 = Sunday … 6 = Saturday (matches PHP `w`).
            'day_of_week'  => ['type' => 'TINYINT', 'unsigned' => true],
            'opens_at'     => ['type' => 'TIME'],
            'closes_at'    => ['type' => 'TIME'],
            'is_closed'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['court_id', 'day_of_week']);
        $this->forge->addForeignKey('court_id', 'courts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('court_schedules', true, ['ENGINE' => 'InnoDB']);

        // ---------------------------------------------------- court_maintenance
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'court_id'        => ['type' => 'INT', 'unsigned' => true],
            'title'           => ['type' => 'VARCHAR', 'constraint' => 160],
            'description'     => ['type' => 'TEXT', 'null' => true],
            'type'            => ['type' => 'ENUM', 'constraint' => ['routine', 'repair', 'resurfacing', 'inspection', 'cleaning'], 'default' => 'routine'],
            'scheduled_start' => ['type' => 'DATETIME'],
            'scheduled_end'   => ['type' => 'DATETIME'],
            'status'          => ['type' => 'ENUM', 'constraint' => ['scheduled', 'in_progress', 'completed', 'cancelled'], 'default' => 'scheduled'],
            'assigned_to'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'cost'            => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'created_by'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['court_id', 'scheduled_start']);
        $this->forge->addForeignKey('court_id', 'courts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('assigned_to', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('court_maintenance', true, ['ENGINE' => 'InnoDB']);

        // ------------------------------------------------------------ equipment
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'code'               => ['type' => 'VARCHAR', 'constraint' => 20],
            'name'               => ['type' => 'VARCHAR', 'constraint' => 120],
            'category'           => ['type' => 'ENUM', 'constraint' => ['paddle', 'ball', 'net', 'shoes', 'apparel', 'other'], 'default' => 'paddle'],
            'description'        => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'rental_rate_hourly' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'deposit'            => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'total_qty'          => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'available_qty'      => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'condition_grade'    => ['type' => 'ENUM', 'constraint' => ['new', 'good', 'fair', 'retired'], 'default' => 'good'],
            'image_path'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('equipment', true, ['ENGINE' => 'InnoDB']);

        // ------------------------------------------------------------- products
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'sku'           => ['type' => 'VARCHAR', 'constraint' => 32],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 160],
            'slug'          => ['type' => 'VARCHAR', 'constraint' => 180],
            'category'      => ['type' => 'ENUM', 'constraint' => ['paddles', 'balls', 'apparel', 'footwear', 'bags', 'accessories', 'drinks'], 'default' => 'accessories'],
            'brand'         => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'description'   => ['type' => 'TEXT', 'null' => true],
            'price'         => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'cost_price'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'stock_qty'     => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'reorder_level' => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 5],
            'image_path'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_featured'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('sku');
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey('category');
        $this->forge->createTable('products', true, ['ENGINE' => 'InnoDB']);

        // -------------------------------------------------- inventory_movements
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'product_id' => ['type' => 'INT', 'unsigned' => true],
            'type'       => ['type' => 'ENUM', 'constraint' => ['in', 'out', 'adjustment']],
            'qty'        => ['type' => 'SMALLINT'],
            'balance'    => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'reason'     => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['product_id', 'created_at']);
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('inventory_movements', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        foreach (['inventory_movements', 'products', 'equipment', 'court_maintenance', 'court_schedules', 'courts'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
