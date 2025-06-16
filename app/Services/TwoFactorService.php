<?php
// app/Services/TwoFactorService.php

namespace App\Services;

use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    protected Google2FA $google2fa;
    protected string $issuer;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
        $this->issuer = config('app.name', 'Laravel App');
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getQRCodeUrl(string $email, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            $this->issuer,
            $email,
            $secret
        );
    }

    public function verifyCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code);
    }

    public function getCurrentTimeSlot(): int
    {
        return $this->google2fa->getCurrentOtp($secret);
    }
}