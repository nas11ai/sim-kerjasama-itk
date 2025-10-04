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
        Schema::create('review_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_summary_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('parent_comment_id')->nullable()
                ->constrained('review_comments')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('reviewer_id')->nullable()->constrained()->onUpdate('cascade')->onDelete('set null');
            $table->text('comment_text');
            $table->timestamps();

            $table->index(['review_summary_id', 'parent_comment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_comments');
    }
};
