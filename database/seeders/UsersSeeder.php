<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = [
            'name' => 'Admin User',
            'email' => 'r.safarovitch@gmail.com',
            'phone' => '+992884238383',
            'password' => bcrypt('password'),
            'status' => \App\Enums\UserStatus::Active,
        ];

        $user = \App\Models\User::firstOrCreate(['email' => $user['email']], $user);

        if ($user->roles()->count() === 0) {
            $user->assignRole('Admin');
        }
    }
}
