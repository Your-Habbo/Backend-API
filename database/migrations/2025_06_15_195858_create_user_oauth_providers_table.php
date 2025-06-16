<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_oauth_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('oauth_provider_id')->constrained()->onDelete('cascade');
            $table->string('provider_user_id');
            $table->string('provider_username')->nullable();
            $table->string('provider_email')->nullable();
            $table->string('provider_avatar')->nullable();
            $table->json('provider_data')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['oauth_provider_id', 'provider_user_id']);
            $table->index(['user_id', 'oauth_provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_oauth_providers');
    }
};