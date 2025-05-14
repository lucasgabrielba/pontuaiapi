<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('card_reward_program', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('card_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('reward_program_id')->constrained()->onDelete('cascade');
            $table->decimal('conversion_rate', 8, 2)->default(1.0);
            $table->boolean('is_primary')->default(false);
            $table->text('terms')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            $table->unique(['card_id', 'reward_program_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('card_reward_program');
    }
};