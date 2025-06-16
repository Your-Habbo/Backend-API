<?php

// database/seeders/AdminUserSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Create super admin user
        $superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $superAdmin->assignRole('super-admin');

        // Create regular admin user
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin2@example.com',
            'username' => 'admin2',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $admin->assignRole('admin');

        // Create test user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'username' => 'testuser',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->assignRole('user');

        $this->command->info('Admin users created successfully!');
        $this->command->info('Super Admin: admin@example.com / password');
        $this->command->info('Admin: admin2@example.com / password');
        $this->command->info('User: user@example.com / password');
    }
}
