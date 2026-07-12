<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->foreignId('parent_submission_id')
                ->nullable()
                ->after('submitted_by')
                ->constrained('form_submissions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropForeign(['parent_submission_id']);
            $table->dropColumn('parent_submission_id');
        });
    }
};
