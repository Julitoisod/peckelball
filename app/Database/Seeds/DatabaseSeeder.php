<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Order matters — each seeder reads IDs the previous one created.
 *
 *   php spark db:seed DatabaseSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(FacilitySeeder::class);
        $this->call(ProgramSeeder::class);
        $this->call(BookingSeeder::class);
    }
}
