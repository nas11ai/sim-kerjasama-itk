<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $studyPrograms = DB::table('study_programs')->get();

            foreach ($studyPrograms as $studyProgram) {
                $parentOrgId = DB::table('organizations')
                    ->where('type', 'faculty')
                    ->where('metadata->legacy->faculty_id', (string) $studyProgram->faculty_id)
                    ->value('id');

                if ($parentOrgId === null) {
                    throw new RuntimeException("Faculty organization not found for study program: {$studyProgram->name}");
                }

                DB::table('organizations')->insert([
                    'name' => $studyProgram->name,
                    'type' => 'study_program',
                    'parent_id' => $parentOrgId,
                    'is_active' => true,
                    'metadata' => json_encode([
                        'legacy' => [
                            'study_program_id' => $studyProgram->id,
                        ],
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            DB::table('organizations')
                ->where('type', 'study_program')
                ->whereNotNull('metadata->legacy->study_program_id')
                ->delete();
        });
    }
};
