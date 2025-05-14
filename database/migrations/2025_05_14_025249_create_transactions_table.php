<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('category_id')->nullable()->constrained();
            $table->string('merchant_name');
            $table->date('transaction_date');
            $table->integer('amount');
            $table->integer('points_earned')->default(0);
            $table->boolean('is_recommended')->default(false);
            $table->text('description')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};