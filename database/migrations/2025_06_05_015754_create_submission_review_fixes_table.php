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
        Schema::create('submission_review_fixes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_review_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->text('revision_note');
            $table->text('file_path')->nullable();
            $table->unsignedBigInteger('submitted_by');
            $table->timestamps();

            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission_review_fixes');
    }
};
