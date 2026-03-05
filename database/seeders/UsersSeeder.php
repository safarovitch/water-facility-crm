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
      'sip_extension' => '1001',
      'sip_password' => '08230a0d9912bbdb',
    ];

    $user = \App\Models\User::updateOrCreate(['email' => $user['email']], $user);

    if ($user->roles()->count() === 0) {
      $user->assignRole('Admin');
    }
  }
}
