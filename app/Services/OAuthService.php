<?php
// app/Services/OAuthService.php

namespace App\Services;

use App\Models\OAuthProvider;
use App\Models\User;
use App\Models\UserOAuthProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

class OAuthService
{
    public function getRedirectUrl(string $provider): string
    {
        $this->validateProvider($provider);
        
        return Socialite::driver($provider)->redirect()->getTargetUrl();
    }

    public function handleCallback(string $provider): array
    {
        $this->validateProvider($provider);
        
        $socialiteUser = Socialite::driver($provider)->user();
        $oauthProvider = OAuthProvider::where('name', $provider)->firstOrFail();
        
        // Check if this OAuth account is already linked
        $existingLink = UserOAuthProvider::where('oauth_provider_id', $oauthProvider->id)
            ->where('provider_user_id', $socialiteUser->getId())
            ->first();

        if ($existingLink) {
            // Update the existing link and return the user
            $user = $existingLink->user;
            $this->updateOAuthProviderData($existingLink, $socialiteUser);
            
            return [
                'user' => $user,
                'is_new_user' => false,
                'is_new_link' => false,
            ];
        }

        // Check if user exists by email
        $user = User::where('email', $socialiteUser->getEmail())->first();
        
        if ($user) {
            // Link existing user account
            $user->linkOAuthProvider($provider, $this->extractProviderData($socialiteUser));
            
            return [
                'user' => $user,
                'is_new_user' => false,
                'is_new_link' => true,
            ];
        }

        // Create new user account
        $user = $this->createUserFromSocialite($socialiteUser, $provider);
        
        return [
            'user' => $user,
            'is_new_user' => true,
            'is_new_link' => true,
        ];
    }

    public function linkAccount(User $user, string $provider): array
    {
        $this->validateProvider($provider);
        
        $socialiteUser = Socialite::driver($provider)->user();
        $oauthProvider = OAuthProvider::where('name', $provider)->firstOrFail();
        
        // Check if this OAuth account is already linked to another user
        $existingLink = UserOAuthProvider::where('oauth_provider_id', $oauthProvider->id)
            ->where('provider_user_id', $socialiteUser->getId())
            ->first();

        if ($existingLink && $existingLink->user_id !== $user->id) {
            throw new \Exception('This ' . $provider . ' account is already linked to another user.');
        }

        $userOAuthProvider = $user->linkOAuthProvider(
            $provider, 
            $this->extractProviderData($socialiteUser)
        );

        return [
            'provider' => $provider,
            'provider_username' => $userOAuthProvider->provider_username,
            'linked_at' => $userOAuthProvider->created_at,
        ];
    }

    public function unlinkAccount(User $user, string $provider): bool
    {
        $this->validateProvider($provider);
        
        // Ensure user has password or other OAuth providers
        if (!$user->password && $user->oauthProviders()->count() <= 1) {
            throw new \Exception('Cannot unlink the only authentication method. Please set a password first.');
        }

        return $user->unlinkOAuthProvider($provider);
    }

    protected function createUserFromSocialite(SocialiteUser $socialiteUser, string $provider): User
    {
        return DB::transaction(function () use ($socialiteUser, $provider) {
            $user = User::create([
                'name' => $socialiteUser->getName(),
                'email' => $socialiteUser->getEmail(),
                'username' => $this->generateUsername($socialiteUser),
                'email_verified_at' => now(),
                'password' => null, // OAuth-only account initially
            ]);

            // Assign default role
            $user->assignRole('user');

            // Link OAuth provider
            $user->linkOAuthProvider($provider, $this->extractProviderData($socialiteUser));

            return $user;
        });
    }

    protected function updateOAuthProviderData(UserOAuthProvider $link, SocialiteUser $socialiteUser): void
    {
        $link->update([
            'provider_username' => $socialiteUser->getNickname() ?? $socialiteUser->getName(),
            'provider_email' => $socialiteUser->getEmail(),
            'provider_avatar' => $socialiteUser->getAvatar(),
            'provider_data' => $this->extractProviderData($socialiteUser),
            'last_used_at' => now(),
        ]);
    }

    protected function extractProviderData(SocialiteUser $socialiteUser): array
    {
        return [
            'id' => $socialiteUser->getId(),
            'nickname' => $socialiteUser->getNickname(),
            'name' => $socialiteUser->getName(),
            'email' => $socialiteUser->getEmail(),
            'avatar' => $socialiteUser->getAvatar(),
            'username' => $socialiteUser->getNickname() ?? $socialiteUser->getName(),
            'raw' => $socialiteUser->getRaw(),
        ];
    }

    protected function generateUsername(SocialiteUser $socialiteUser): string
    {
        $username = $socialiteUser->getNickname() ?? 
                   strtolower(str_replace(' ', '.', $socialiteUser->getName()));
        
        // Ensure username is unique
        $originalUsername = $username;
        $counter = 1;
        
        while (User::where('username', $username)->exists()) {
            $username = $originalUsername . $counter;
            $counter++;
        }

        return $username;
    }

    protected function validateProvider(string $provider): void
    {
        $validProviders = ['google', 'discord'];
        
        if (!in_array($provider, $validProviders)) {
            throw new \InvalidArgumentException("Unsupported OAuth provider: {$provider}");
        }

        $oauthProvider = OAuthProvider::where('name', $provider)->active()->first();
        
        if (!$oauthProvider) {
            throw new \Exception("OAuth provider {$provider} is not configured or active.");
        }
    }
}