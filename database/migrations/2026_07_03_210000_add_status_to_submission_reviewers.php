<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_reviewers', function (Blueprint $table) {
            $table->string('status')->default('active')->after('evaluation_status'); // active | replaced
            $table->index(['form_submission_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('submission_reviewers', function (Blueprint $table) {
            $table->dropIndex(['form_submission_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
