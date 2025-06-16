<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity');
            $table->string('device_name')->nullable();
            $table->json('device_info')->nullable();
            $table->boolean('is_current')->default(false);

            $table->index(['user_id', 'last_activity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};