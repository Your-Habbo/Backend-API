<?php

// database/seeders/MaintenanceSettingSeeder.php

namespace Database\Seeders;

use App\Models\MaintenanceSetting;
use Illuminate\Database\Seeder;

class MaintenanceSettingSeeder extends Seeder
{
    public function run(): void
    {
        MaintenanceSetting::create([
            'is_enabled' => false,
            'title' => 'YourHabbo Under Maintenance',
            'message' => 'We are currently performing scheduled maintenance to improve your experience. Please check back soon!',
            'allow_admin_access' => true,
            'contact_email' => 'support@yourhabbo.codeneko.co',
            'social_links' => [
                'discord' => 'https://discord.gg/yourhabbo',
                'twitter' => 'https://twitter.com/yourhabbo',
            ],
        ]);

        $this->command->info('Maintenance settings created successfully!');
    }
}