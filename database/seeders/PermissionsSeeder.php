<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
  public function run(): void
  {
    $permissions = [
      // Client permissions
      'view clients',
      'create clients',
      'edit clients',
      'delete clients',

      // Order permissions
      'view orders',
      'create orders',
      'edit orders',
      'manage order status',
      'cancel orders',
    ];

    foreach ($permissions as $permission) {
      Permission::firstOrCreate([
        'name'       => $permission,
        'guard_name' => 'web',
      ]);
    }

    // Assign permissions to roles
    $map = [
      'Admin' => [
        'view clients',
        'create clients',
        'edit clients',
        'delete clients',
        'view orders',
        'create orders',
        'edit orders',
        'manage order status',
        'cancel orders',
      ],
      'Staff manager' => [
        'view clients',
        'create clients',
        'edit clients',
        'view orders',
        'create orders',
        'edit orders',
      ],
      'Currier manager' => [
        'view orders',
        'manage order status',
      ],
      'Product manager' => [
        'view orders',
      ],
      'Finance manager' => [
        'view clients',
        'view orders',
        'cancel orders',
      ],
    ];

    foreach ($map as $roleName => $rolePermissions) {
      $role = Role::where('name', $roleName)->first();
      if ($role) {
        $role->givePermissionTo($rolePermissions);
      }
    }
  }
}
