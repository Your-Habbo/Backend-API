<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->nullable()->after('email');
            $table->timestamp('email_verified_at')->nullable()->change();
            $table->boolean('two_factor_enabled')->default(false)->after('password');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->json('security_settings')->nullable()->after('two_factor_confirmed_at');
            $table->timestamp('last_login_at')->nullable()->after('security_settings');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
            $table->json('login_history')->nullable()->after('last_login_ip');
            $table->boolean('is_active')->default(true)->after('login_history');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'two_factor_enabled',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'security_settings',
                'last_login_at',
                'last_login_ip',
                'login_history',
                'is_active'
            ]);
        });
    }
};