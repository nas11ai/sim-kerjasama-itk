<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('review_form_field_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_form_response_id')->constrained()->onDelete('cascade');
            $table->foreignId('review_form_field_id')->constrained()->onDelete('cascade');
            $table->text('value')->nullable(); // Store all responses as text
            $table->timestamps();

            $table->index(['review_form_response_id', 'review_form_field_id'], 'idx_response_field');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_form_field_responses');
    }
};
