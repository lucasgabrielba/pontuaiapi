<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invoice_suggestions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invoice_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->string('type')->default('general'); // optimization, problem, enhancement, general
            $table->string('priority')->default('medium'); // low, medium, high, critical
            $table->string('status')->default('pending'); // pending, in_progress, completed, rejected
            $table->foreignUlid('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->json('additional_data')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->index(['created_by']);
            $table->index(['type', 'priority']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoice_suggestions');
    }
};