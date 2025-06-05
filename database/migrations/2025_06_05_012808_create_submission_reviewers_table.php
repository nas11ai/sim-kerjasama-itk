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
        Schema::create('submission_reviewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('reviewer_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('submission_review_type_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission_reviewers');
    }
};
