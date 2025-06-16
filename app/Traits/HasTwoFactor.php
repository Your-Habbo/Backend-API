<?php
// app/Traits/HasTwoFactor.php

namespace App\Traits;

use App\Services\TwoFactorService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

trait HasTwoFactor
{
    public function enableTwoFactor(): array
    {
        $twoFactorService = app(TwoFactorService::class);
        
        $secret = $twoFactorService->generateSecret();
        $qrCodeUrl = $twoFactorService->getQRCodeUrl($this->email, $secret);
        $recoveryCodes = $this->generateRecoveryCodes();
        
        $this->update([
            'two_factor_secret' => Crypt::encrypt($secret),
            'two_factor_recovery_codes' => Crypt::encrypt($recoveryCodes->toJson()),
        ]);

        $this->logSecurityEvent('two_factor_setup_started');

        return [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'recovery_codes' => $recoveryCodes,
        ];
    }

    public function confirmTwoFactor(string $code): bool
    {
        if (!$this->two_factor_secret) {
            return false;
        }

        $twoFactorService = app(TwoFactorService::class);
        $secret = Crypt::decrypt($this->two_factor_secret);
        
        if ($twoFactorService->verifyCode($secret, $code)) {
            $this->update([
                'two_factor_enabled' => true,
                'two_factor_confirmed_at' => now(),
            ]);

            $this->logSecurityEvent('two_factor_enabled');
            
            return true;
        }

        return false;
    }

    public function disableTwoFactor(string $password): bool
    {
        if (!password_verify($password, $this->password)) {
            return false;
        }

        $this->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $this->logSecurityEvent('two_factor_disabled');

        return true;
    }

    public function verifyTwoFactorCode(string $code): bool
    {
        if (!$this->two_factor_enabled || !$this->two_factor_secret) {
            return false;
        }

        $twoFactorService = app(TwoFactorService::class);
        $secret = Crypt::decrypt($this->two_factor_secret);
        
        return $twoFactorService->verifyCode($secret, $code);
    }

    public function verifyRecoveryCode(string $code): bool
    {
        if (!$this->two_factor_enabled || !$this->two_factor_recovery_codes) {
            return false;
        }

        $recoveryCodes = collect(json_decode(
            Crypt::decrypt($this->two_factor_recovery_codes), 
            true
        ));

        if (!$recoveryCodes->contains($code)) {
            return false;
        }

        // Remove the used recovery code
        $remainingCodes = $recoveryCodes->reject(fn($recoveryCode) => $recoveryCode === $code);
        
        $this->update([
            'two_factor_recovery_codes' => Crypt::encrypt($remainingCodes->toJson()),
        ]);

        $this->logSecurityEvent('recovery_code_used', [
            'remaining_codes_count' => $remainingCodes->count(),
        ]);

        return true;
    }

    public function generateNewRecoveryCodes(): Collection
    {
        $recoveryCodes = $this->generateRecoveryCodes();
        
        $this->update([
            'two_factor_recovery_codes' => Crypt::encrypt($recoveryCodes->toJson()),
        ]);

        $this->logSecurityEvent('recovery_codes_regenerated');

        return $recoveryCodes;
    }

    public function getRecoveryCodes(): Collection
    {
        if (!$this->two_factor_recovery_codes) {
            return collect();
        }

        return collect(json_decode(
            Crypt::decrypt($this->two_factor_recovery_codes), 
            true
        ));
    }

    protected function generateRecoveryCodes(): Collection
    {
        return collect(range(1, 8))->map(function () {
            return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 8);
        });
    }

    public function getTwoFactorQrCodeUrl(): ?string
    {
        if (!$this->two_factor_secret) {
            return null;
        }

        $twoFactorService = app(TwoFactorService::class);
        $secret = Crypt::decrypt($this->two_factor_secret);
        
        return $twoFactorService->getQRCodeUrl($this->email, $secret);
    }

    public function requiresTwoFactorChallenge(): bool
    {
        return $this->two_factor_enabled && !session('auth.two_factor_confirmed');
    }

    public function markTwoFactorChallengeComplete(): void
    {
        session(['auth.two_factor_confirmed' => true]);
        
        $this->logSecurityEvent('two_factor_challenge_completed');
    }
}