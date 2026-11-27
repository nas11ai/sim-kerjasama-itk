<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('output_type_definitions')
            ->where('code', 'pks')
            ->where('submission_type', 'CommunityService')
            ->update(['submission_type' => 'Pengabdian kepada Masyarakat']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('output_type_definitions')
            ->where('code', 'pks')
            ->where('submission_type', 'Pengabdian kepada Masyarakat')
            ->update(['submission_type' => 'CommunityService']);
    }
};
