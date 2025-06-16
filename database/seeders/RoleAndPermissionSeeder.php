<?php
// database/seeders/RoleAndPermissionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User permissions
            'view users',
            'create users',
            'edit users',
            'delete users',
            'view own profile',
            'edit own profile',
            'delete own account',
            
            // Role and permission management
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            'assign roles',
            'view permissions',
            'create permissions',
            'edit permissions',
            'delete permissions',
            'assign permissions',
            
            // System administration
            'system administration',
            'view system logs',
            'clear cache',
            'manage oauth providers',
            'view security events',
            'resolve security events',
            
            // API key management
            'view api keys',
            'create api keys',
            'delete api keys',
            'manage user api keys',
            
            // Two-factor authentication
            'manage user two factor',
            'disable user two factor',
            
            // Content management
            'view content',
            'create content',
            'edit content',
            'delete content',
            'publish content',
            
            // Audit and reporting
            'view audit logs',
            'export data',
            'view reports',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        // Create roles and assign permissions
        $superAdminRole = Role::findOrCreate('super-admin');
        $superAdminRole->givePermissionTo(Permission::all());

        $adminRole = Role::findOrCreate('admin');
        $adminRole->givePermissionTo([
            'view users',
            'create users',
            'edit users',
            'view roles',
            'assign roles',
            'view permissions',
            'view api keys',
            'create api keys',
            'delete api keys',
            'manage user api keys',
            'manage user two factor',
            'disable user two factor',
            'view content',
            'create content',
            'edit content',
            'delete content',
            'publish content',
            'view audit logs',
            'view reports',
            'view security events',
            'resolve security events',
        ]);

        $moderatorRole = Role::findOrCreate('moderator');
        $moderatorRole->givePermissionTo([
            'view users',
            'edit users',
            'view content',
            'create content',
            'edit content',
            'delete content',
            'view audit logs',
        ]);

        $editorRole = Role::findOrCreate('editor');
        $editorRole->givePermissionTo([
            'view content',
            'create content',
            'edit content',
            'publish content',
        ]);

        $userRole = Role::findOrCreate('user');
        $userRole->givePermissionTo([
            'view own profile',
            'edit own profile',
            'delete own account',
            'view api keys',
            'create api keys',
            'delete api keys',
        ]);

        $guestRole = Role::findOrCreate('guest');
        $guestRole->givePermissionTo([
            'view content',
        ]);

        $this->command->info('Roles and permissions created successfully!');
    }
}