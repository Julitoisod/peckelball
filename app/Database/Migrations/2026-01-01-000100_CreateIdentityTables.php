<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Identity, access control and the system-wide audit / messaging tables.
 */
class CreateIdentityTables extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------- roles
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'slug'        => ['type' => 'VARCHAR', 'constraint' => 40],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 60],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            // Higher level = more authority. Used for "can this user manage that user".
            'level'       => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 10],
            'is_staff'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('roles', true, ['ENGINE' => 'InnoDB']);

        // ---------------------------------------------------------- permissions
        $this->forge->addField([
            'id'     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'slug'   => ['type' => 'VARCHAR', 'constraint' => 80],
            'name'   => ['type' => 'VARCHAR', 'constraint' => 120],
            'module' => ['type' => 'VARCHAR', 'constraint' => 40],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey('module');
        $this->forge->createTable('permissions', true, ['ENGINE' => 'InnoDB']);

        // ----------------------------------------------------- role_permissions
        $this->forge->addField([
            'role_id'       => ['type' => 'INT', 'unsigned' => true],
            'permission_id' => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addKey(['role_id', 'permission_id'], true);
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('permission_id', 'permissions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('role_permissions', true, ['ENGINE' => 'InnoDB']);

        // ---------------------------------------------------------------- users
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'role_id'           => ['type' => 'INT', 'unsigned' => true],
            'first_name'        => ['type' => 'VARCHAR', 'constraint' => 60],
            'last_name'         => ['type' => 'VARCHAR', 'constraint' => 60],
            'email'             => ['type' => 'VARCHAR', 'constraint' => 160],
            'phone'             => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            // password_hash() output — never a raw or reversible value.
            'password_hash'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'avatar_path'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'skill_level'       => ['type' => 'ENUM', 'constraint' => ['beginner', 'intermediate', 'advanced', 'pro'], 'default' => 'beginner'],
            'dupr_rating'       => ['type' => 'DECIMAL', 'constraint' => '3,2', 'null' => true],
            'date_of_birth'     => ['type' => 'DATE', 'null' => true],
            'gender'            => ['type' => 'ENUM', 'constraint' => ['male', 'female', 'other', 'undisclosed'], 'default' => 'undisclosed'],
            'address'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status'            => ['type' => 'ENUM', 'constraint' => ['active', 'inactive', 'suspended'], 'default' => 'active'],
            'email_verified_at' => ['type' => 'DATETIME', 'null' => true],
            'last_login_at'     => ['type' => 'DATETIME', 'null' => true],
            'last_login_ip'     => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'failed_logins'     => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'locked_until'      => ['type' => 'DATETIME', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->addKey('role_id');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('users', true, ['ENGINE' => 'InnoDB']);

        // ----------------------------------------------------------- audit_logs
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'action'      => ['type' => 'VARCHAR', 'constraint' => 80],
            'entity_type' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'entity_id'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'summary'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'changes'     => ['type' => 'TEXT', 'null' => true],
            'ip_address'  => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['entity_type', 'entity_id']);
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('audit_logs', true, ['ENGINE' => 'InnoDB']);

        // -------------------------------------------------------- notifications
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            // NULL user_id = broadcast to every user.
            'user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'type'       => ['type' => 'ENUM', 'constraint' => ['booking', 'payment', 'membership', 'tournament', 'maintenance', 'system'], 'default' => 'system'],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 160],
            'body'       => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'link'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'read_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'read_at']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('notifications', true, ['ENGINE' => 'InnoDB']);

        // -------------------------------------------------------- announcements
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'author_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'title'        => ['type' => 'VARCHAR', 'constraint' => 160],
            'body'         => ['type' => 'TEXT'],
            'audience'     => ['type' => 'ENUM', 'constraint' => ['public', 'players', 'members', 'staff', 'coaches'], 'default' => 'public'],
            'is_pinned'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'published_at' => ['type' => 'DATETIME', 'null' => true],
            'expires_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['audience', 'published_at']);
        $this->forge->addForeignKey('author_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('announcements', true, ['ENGINE' => 'InnoDB']);

        // ------------------------------------------------------------- settings
        $this->forge->addField([
            'key'        => ['type' => 'VARCHAR', 'constraint' => 80],
            'value'      => ['type' => 'TEXT', 'null' => true],
            'type'       => ['type' => 'ENUM', 'constraint' => ['string', 'int', 'bool', 'json'], 'default' => 'string'],
            'group'      => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'general'],
            'label'      => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('key', true);
        $this->forge->addKey('group');
        $this->forge->createTable('settings', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        foreach (['settings', 'announcements', 'notifications', 'audit_logs', 'users', 'role_permissions', 'permissions', 'roles'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
