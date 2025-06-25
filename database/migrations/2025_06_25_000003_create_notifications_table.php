<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('type'); // system_alert, performance, user_activity, etc.
            $table->string('title');
            $table->text('message');
            $table->enum('severity', ['info', 'warning', 'error', 'success'])->default('info');
            $table->boolean('read')->default(false);
            $table->string('action_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read']);
            $table->index(['type', 'created_at']);
            $table->index(['severity', 'read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};