<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Coaching roster and competitive programs.
 */
class CreateProgramTables extends Migration
{
    public function up(): void
    {
        // -------------------------------------------------------------- coaches
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'          => ['type' => 'INT', 'unsigned' => true],
            'headline'         => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'bio'              => ['type' => 'TEXT', 'null' => true],
            'specialties'      => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'certifications'   => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'years_experience' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'hourly_rate'      => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'group_rate'       => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'rating'           => ['type' => 'DECIMAL', 'constraint' => '2,1', 'default' => 0],
            'sessions_taught'  => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'photo_path'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_accepting'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order'       => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('coaches', true, ['ENGINE' => 'InnoDB']);

        // --------------------------------------------------- coach_availability
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'coach_id'    => ['type' => 'INT', 'unsigned' => true],
            'day_of_week' => ['type' => 'TINYINT', 'unsigned' => true],
            'starts_at'   => ['type' => 'TIME'],
            'ends_at'     => ['type' => 'TIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['coach_id', 'day_of_week']);
        $this->forge->addForeignKey('coach_id', 'coaches', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('coach_availability', true, ['ENGINE' => 'InnoDB']);

        // ---------------------------------------------------------- tournaments
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'slug'                => ['type' => 'VARCHAR', 'constraint' => 140],
            'name'                => ['type' => 'VARCHAR', 'constraint' => 160],
            'description'         => ['type' => 'TEXT', 'null' => true],
            'format'              => ['type' => 'ENUM', 'constraint' => ['single_elimination', 'double_elimination', 'round_robin', 'pool_play'], 'default' => 'single_elimination'],
            'division'            => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'Open Doubles'],
            'skill_level'         => ['type' => 'ENUM', 'constraint' => ['beginner', 'intermediate', 'advanced', 'pro', 'open'], 'default' => 'open'],
            'starts_on'           => ['type' => 'DATE'],
            'ends_on'             => ['type' => 'DATE'],
            'registration_opens'  => ['type' => 'DATE', 'null' => true],
            'registration_closes' => ['type' => 'DATE', 'null' => true],
            'entry_fee'           => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'prize_pool'          => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'max_teams'           => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 16],
            'status'              => ['type' => 'ENUM', 'constraint' => ['draft', 'open', 'closed', 'ongoing', 'completed', 'cancelled'], 'default' => 'draft'],
            'banner_path'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey(['status', 'starts_on']);
        $this->forge->createTable('tournaments', true, ['ENGINE' => 'InnoDB']);

        // ----------------------------------------------- tournament_participants
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tournament_id'  => ['type' => 'INT', 'unsigned' => true],
            'user_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'partner_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'team_name'      => ['type' => 'VARCHAR', 'constraint' => 120],
            'contact_phone'  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'seed'           => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'status'         => ['type' => 'ENUM', 'constraint' => ['registered', 'confirmed', 'withdrawn', 'disqualified'], 'default' => 'registered'],
            'payment_status' => ['type' => 'ENUM', 'constraint' => ['unpaid', 'paid', 'refunded'], 'default' => 'unpaid'],
            'wins'           => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'losses'         => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'points'         => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'final_rank'     => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tournament_id', 'status']);
        $this->forge->addForeignKey('tournament_id', 'tournaments', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('partner_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('tournament_participants', true, ['ENGINE' => 'InnoDB']);

        // ---------------------------------------------------- tournament_matches
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tournament_id'  => ['type' => 'INT', 'unsigned' => true],
            'round'          => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 1],
            'round_label'    => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'match_number'   => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 1],
            'participant_a'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'participant_b'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'court_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'scheduled_at'   => ['type' => 'DATETIME', 'null' => true],
            'score_a'        => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'score_b'        => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'winner_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'status'         => ['type' => 'ENUM', 'constraint' => ['scheduled', 'live', 'completed', 'walkover'], 'default' => 'scheduled'],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tournament_id', 'round']);
        $this->forge->addForeignKey('tournament_id', 'tournaments', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('participant_a', 'tournament_participants', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('participant_b', 'tournament_participants', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('winner_id', 'tournament_participants', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('court_id', 'courts', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('tournament_matches', true, ['ENGINE' => 'InnoDB']);

        // --------------------------------------------------------- testimonials
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'author_name'  => ['type' => 'VARCHAR', 'constraint' => 80],
            'author_role'  => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'quote'        => ['type' => 'VARCHAR', 'constraint' => 600],
            'rating'       => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 5],
            'photo_path'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_published' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order'   => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('testimonials', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        foreach (['testimonials', 'tournament_matches', 'tournament_participants', 'tournaments', 'coach_availability', 'coaches'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
