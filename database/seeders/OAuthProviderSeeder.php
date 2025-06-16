<?php

// database/seeders/OAuthProviderSeeder.php

namespace Database\Seeders;

use App\Models\OAuthProvider;
use Illuminate\Database\Seeder;

class OAuthProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name' => 'google',
                'display_name' => 'Google',
                'is_active' => true,
                'config' => [
                    'scopes' => ['openid', 'profile', 'email'],
                    'redirect_uri' => config('services.google.redirect'),
                ],
            ],
            [
                'name' => 'discord',
                'display_name' => 'Discord',
                'is_active' => true,
                'config' => [
                    'scopes' => ['identify', 'email'],
                    'redirect_uri' => config('services.discord.redirect'),
                ],
            ],
            [
                'name' => 'github',
                'display_name' => 'GitHub',
                'is_active' => false,
                'config' => [
                    'scopes' => ['user:email'],
                    'redirect_uri' => null,
                ],
            ],
            [
                'name' => 'facebook',
                'display_name' => 'Facebook',
                'is_active' => false,
                'config' => [
                    'scopes' => ['email'],
                    'redirect_uri' => null,
                ],
            ],
        ];

        foreach ($providers as $provider) {
            OAuthProvider::updateOrCreate(
                ['name' => $provider['name']],
                $provider
            );
        }

        $this->command->info('OAuth providers seeded successfully!');
    }
}
