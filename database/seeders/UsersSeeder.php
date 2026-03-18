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
    $userData = [
      'name' => 'Admin User',
      'email' => 'r.safarovitch@gmail.com',
      'password' => bcrypt('password'),
      'status' => \App\Enums\UserStatus::Active,
      'sip_extension' => '1001',
      'sip_password' => '08230a0d9912bbdb',
    ];

    $user = \App\Models\User::updateOrCreate(['email' => $userData['email']], $userData);

    $user->phones()->updateOrCreate(
        ['phone' => '+992884238383'],
        ['label' => 'Primary', 'is_default' => true]
    );

    if ($user->roles()->count() === 0) {
      $user->assignRole('Admin');
    }
  }
}
