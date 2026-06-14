<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schemes', function (Blueprint $table) {
            $table->jsonb('rules')->nullable()->after('duration_months');
        });

        DB::statement('CREATE INDEX IF NOT EXISTS idx_schemes_rules ON schemes USING GIN (rules)');
    }

    public function down(): void
    {
        Schema::table('schemes', function (Blueprint $table) {
            $table->dropIndex('idx_schemes_rules');
            $table->dropColumn('rules');
        });
    }
};
