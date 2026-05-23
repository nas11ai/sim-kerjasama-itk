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
        Schema::create('review_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_evaluation_form_id')->constrained()->onDelete('cascade');
            $table->foreignId('field_type_id')->constrained();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('order')->default(0);
            $table->json('validation_rules')->nullable(); // For storing custom validation
            $table->timestamps();

            $table->index(['review_evaluation_form_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_form_fields');
    }
};
