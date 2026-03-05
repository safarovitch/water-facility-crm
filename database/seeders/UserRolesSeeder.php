<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin', 'guard_name' => 'web'],
            ['name' => 'Staff manager', 'guard_name' => 'web'],
            ['name' => 'Currier manager', 'guard_name' => 'web'],
            ['name' => 'Product manager', 'guard_name' => 'web'],
            ['name' => 'Finance manager', 'guard_name' => 'web'],
            ['name' => 'Content manager', 'guard_name' => 'web'],
            ['name' => 'Client', 'guard_name' => 'web'],
        ];

        foreach ($roles as $role) {
            \Spatie\Permission\Models\Role::firstOrCreate($role);
        }
    }
}
