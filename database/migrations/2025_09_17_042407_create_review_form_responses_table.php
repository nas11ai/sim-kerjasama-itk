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
        Schema::create('review_form_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reviewer_form_assignment_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['draft', 'submitted', 'locked'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->text('final_notes')->nullable(); // Overall notes/summary
            $table->timestamps();

            $table->index(['reviewer_form_assignment_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_form_responses');
    }
};
