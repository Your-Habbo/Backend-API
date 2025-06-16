<?php
// database/migrations/xxxx_create_maintenance_settings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->string('title')->default('Site Under Maintenance');
            $table->text('message')->default('We are currently performing scheduled maintenance. Please check back soon.');
            $table->datetime('scheduled_start')->nullable();
            $table->datetime('scheduled_end')->nullable();
            $table->json('allowed_ips')->nullable(); // IPs that can bypass maintenance
            $table->json('allowed_roles')->nullable(); // Roles that can bypass maintenance
            $table->boolean('allow_admin_access')->default(true);
            $table->string('contact_email')->nullable();
            $table->json('social_links')->nullable(); // Social media links to show during maintenance
            $table->string('estimated_duration')->nullable(); // Human readable duration
            $table->text('internal_notes')->nullable(); // For admin reference
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_settings');
    }
};
