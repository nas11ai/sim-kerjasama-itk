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
        Schema::table('submission_reviewers', function (Blueprint $table) {
            $table->dropForeign(['submission_review_type_id']);
            $table->dropColumn('submission_review_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submission_reviewers', function (Blueprint $table) {
            $table->foreignId('submission_review_type_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
        });
    }
};
