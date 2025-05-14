<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('points', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('reward_program_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('transaction_id')->nullable()->constrained();
            $table->integer('amount');
            $table->date('expiration_date')->nullable();
            $table->string('status');
            $table->text('description')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('points');
    }
};