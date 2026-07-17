<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_access_controls', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('role_id')
                ->constrained('organizations')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
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

        DB::table('form_access_controls')
            ->orderBy('id')
            ->chunkById(1000, function ($controls) use ($orgMap) {
                foreach ($controls as $control) {
                    $orgId = $orgMap[(string) $control->study_program_id] ?? null;
                    if ($orgId === null) {
                        throw new RuntimeException(
                            "Cannot map form_access_controls.id={$control->id} study_program_id={$control->study_program_id} to organizations."
                        );
                    }

                    DB::table('form_access_controls')
                        ->where('id', $control->id)
                        ->update(['organization_id' => $orgId]);
                }
            });

        Schema::table('form_access_controls', function (Blueprint $table) {
            $table->dropForeign(['study_program_id']);
            $table->dropColumn('study_program_id');
        });

        DB::statement('ALTER TABLE form_access_controls ALTER COLUMN organization_id SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('form_access_controls', function (Blueprint $table) {
            $table->foreignId('study_program_id')
                ->nullable()
                ->after('role_id')
                ->constrained('study_programs')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
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
                        $orgToSpMap[$org->id] = (int) $legacySpId;
                    }
                }
            });

        DB::table('form_access_controls')
            ->orderBy('id')
            ->chunkById(1000, function ($controls) use ($orgToSpMap) {
                foreach ($controls as $control) {
                    $spId = $orgToSpMap[$control->organization_id] ?? null;
                    if ($spId === null) {
                        throw new RuntimeException(
                            "Cannot reverse-map form_access_controls.id={$control->id} organization_id={$control->organization_id}."
                        );
                    }

                    DB::table('form_access_controls')
                        ->where('id', $control->id)
                        ->update(['study_program_id' => $spId]);
                }
            });

        Schema::table('form_access_controls', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        DB::statement('ALTER TABLE form_access_controls ALTER COLUMN study_program_id SET NOT NULL');
    }
};
