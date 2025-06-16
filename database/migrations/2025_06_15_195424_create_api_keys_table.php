<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('key_hash'); // hashed API key
            $table->string('key_prefix', 10); // first 10 chars for identification
            $table->json('scopes')->nullable(); // permissions/scopes
            $table->json('allowed_ips')->nullable(); // IP restrictions
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('usage_count')->default(0);
            $table->integer('rate_limit')->nullable(); // requests per minute
            $table->timestamps();

            $table->index(['key_prefix', 'is_active']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};