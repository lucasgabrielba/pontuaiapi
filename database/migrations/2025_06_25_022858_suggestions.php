<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('suggestions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignUlid('category_id')->nullable()->after('invoice_id')->constrained()->onDelete('set null');
            
            $table->string('title', 80);
            $table->text('description');
            $table->string('type'); // card_recommendation, merchant_recommendation, category_optimization, points_strategy, general_tip
            $table->string('priority')->default('medium'); // low, medium, high
            $table->text('recommendation');
            $table->string('impact_description', 120)->nullable();
            $table->string('potential_points_increase', 32)->nullable();
            $table->boolean('is_personalized')->default(false);
            $table->boolean('applies_to_future')->default(false);
            $table->json('additional_data')->nullable();
            
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['invoice_id', 'type']);
            $table->index(['created_by']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('suggestions');
    }
};