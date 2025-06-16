<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('event_type'); // login, logout, failed_login, password_change, etc.
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('event_data')->nullable(); // additional event information
            $table->string('risk_level')->default('low'); // low, medium, high
            $table->boolean('requires_action')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event_type', 'created_at']);
            $table->index(['risk_level', 'requires_action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};