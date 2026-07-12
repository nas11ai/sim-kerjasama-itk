<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete()->after('user_id');
        });

        DB::statement(<<<'SQL'
            UPDATE user_profiles
            SET organization_id = (
                SELECT o.id FROM organizations o
                WHERE o.metadata->'legacy'->>'study_program_id' = user_profiles.study_program_id::text
            )
            WHERE study_program_id IS NOT NULL
        SQL);

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropForeign(['study_program_id']);
            $table->dropColumn('study_program_id');
        });

        DB::statement('ALTER TABLE user_profiles ALTER COLUMN organization_id SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->cascadeOnDelete();
        });

        DB::statement(<<<'SQL'
            UPDATE user_profiles
            SET study_program_id = (
                SELECT (o.metadata->'legacy'->>'study_program_id')::bigint
                FROM organizations o
                WHERE o.id = user_profiles.organization_id
                AND o.metadata->'legacy' ? 'study_program_id'
            )
            WHERE organization_id IS NOT NULL
        SQL);

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
