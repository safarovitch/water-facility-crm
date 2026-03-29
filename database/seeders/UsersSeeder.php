<?php

namespace Database\Seeders;

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

    if (app()->environment() !== 'production') {

      // Add John Courier
      $courier = \App\Models\User::updateOrCreate(
        ['email' => 'courier@example.com'],
        [
          'name' => 'John Courier',
          'password' => bcrypt('password'),
          'status' => \App\Enums\UserStatus::Active,
          'sip_extension' => '1002',
          'sip_password' => 'secret123',
        ]
      );
      $courier->phones()->updateOrCreate(
        ['phone' => '+992178605005'],
        ['label' => 'Mobile', 'is_default' => true]
      );
      if (!$courier->hasRole('Currier')) {
        $courier->assignRole('Currier');
      }

      // Add Test Client
      $client = \App\Models\User::updateOrCreate(
        ['email' => 'client@example.com'],
        [
          'name' => 'Test Client',
          'password' => bcrypt('password'),
          'status' => \App\Enums\UserStatus::Active,
        ]
      );
      $client->phones()->updateOrCreate(
        ['phone' => '+992178605005'],
        ['label' => 'Mobile', 'is_default' => true]
      );
      if (!$client->hasRole('Client')) {
        $client->assignRole('Client');
      }
    }
  }
}
