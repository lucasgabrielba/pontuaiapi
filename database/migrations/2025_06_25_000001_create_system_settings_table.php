<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->json('value');
            $table->string('type')->default('string');
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
