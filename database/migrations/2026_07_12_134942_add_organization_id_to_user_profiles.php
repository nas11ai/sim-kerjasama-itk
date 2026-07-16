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

        $orgMap = [];
        DB::table('organizations')
            ->where('type', 'study_program')
            ->whereNotNull('metadata')
            ->select('id', 'metadata')
            ->orderBy('id')
            ->chunk(1000, function ($organizations) use (&$orgMap) {
                foreach ($organizations as $org) {
                    $meta = json_decode($org->metadata, true);
                    $legacySpId = $meta['legacy']['study_program_id'] ?? null;
                    if ($legacySpId !== null) {
                        $orgMap[(string) $legacySpId] = $org->id;
                    }
                }
            });

        DB::table('user_profiles')
            ->whereNotNull('study_program_id')
            ->orderBy('id')
            ->chunkById(1000, function ($profiles) use ($orgMap) {
                foreach ($profiles as $profile) {
                    $orgId = $orgMap[(string) $profile->study_program_id] ?? null;
                    if ($orgId !== null) {
                        DB::table('user_profiles')
                            ->where('id', $profile->id)
                            ->update(['organization_id' => $orgId]);
                    }
                }
            });

        $missing = DB::table('user_profiles')->whereNull('organization_id')->count();
        if ($missing > 0) {
            throw new RuntimeException("Migration aborted: {$missing} user_profiles rows have NULL organization_id after backfill.");
        }

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropForeign(['study_program_id']);
            $table->dropColumn('study_program_id');
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE user_profiles MODIFY COLUMN organization_id BIGINT UNSIGNED NOT NULL');
        } else {
            DB::statement('ALTER TABLE user_profiles ALTER COLUMN organization_id SET NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->cascadeOnDelete();
        });

        $orgToSpMap = [];
        DB::table('organizations')
            ->where('type', 'study_program')
            ->whereNotNull('metadata')
            ->select('id', 'metadata')
            ->orderBy('id')
            ->chunk(1000, function ($organizations) use (&$orgToSpMap) {
                foreach ($organizations as $org) {
                    $meta = json_decode($org->metadata, true);
                    $legacySpId = $meta['legacy']['study_program_id'] ?? null;
                    if ($legacySpId !== null) {
                        $orgToSpMap[$org->id] = (string) $legacySpId;
                    }
                }
            });

        DB::table('user_profiles')
            ->whereNotNull('organization_id')
            ->orderBy('id')
            ->chunkById(1000, function ($profiles) use ($orgToSpMap) {
                foreach ($profiles as $profile) {
                    $spId = $orgToSpMap[$profile->organization_id] ?? null;
                    if ($spId !== null) {
                        DB::table('user_profiles')
                            ->where('id', $profile->id)
                            ->update(['study_program_id' => $spId]);
                    }
                }
            });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
