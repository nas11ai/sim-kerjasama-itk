<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reviewer_form_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_reviewer_id')->constrained()->onDelete('cascade');
            $table->foreignId('review_evaluation_form_id')->constrained()->onDelete('cascade');
            $table->boolean('is_required')->default(true);
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('due_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Prevent duplicate assignments
            $table->unique(['submission_reviewer_id', 'review_evaluation_form_id'], 'unique_reviewer_form_assignment');

            $table->index(['submission_reviewer_id', 'is_active']);
            $table->index(['due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviewer_form_assignments');
    }
};
