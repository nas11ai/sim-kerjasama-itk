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
            $table->enum('evaluation_status', ['pending', 'completed', 'not_required'])
                ->default('not_required')
                ->after('reviewer_id');

            $table->index(['form_submission_id', 'evaluation_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submission_reviewers', function (Blueprint $table) {
            $table->dropIndex(['form_submission_id', 'evaluation_status']);
            $table->dropColumn('evaluation_status');
        });
    }
};
