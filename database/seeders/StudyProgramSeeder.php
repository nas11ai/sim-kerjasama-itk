<?php

namespace Database\Seeders;

use App\Models\Faculty;
use App\Models\StudyProgram;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudyProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            DB::transaction(function () {

                $fsti = Faculty::firstOrCreate(['name' => 'Fakultas Sains dan Teknologi Informasi']);
                $fpb = Faculty::firstOrCreate(['name' => 'Fakultas Pembangunan Berkelanjutan']);
                $frti = Faculty::firstOrCreate(['name' => 'Fakultas Rekayasa dan Teknologi Industri']);

                $fsti_study_programs = [
                    ['faculty_id' => $fsti->id, 'name' => 'Matematika'],
                    ['faculty_id' => $fsti->id, 'name' => 'Ilmu Aktuaria'],
                    ['faculty_id' => $fsti->id, 'name' => 'Statistika'],
                    ['faculty_id' => $fsti->id, 'name' => 'Fisika'],
                    ['faculty_id' => $fsti->id, 'name' => 'Informatika'],
                    ['faculty_id' => $fsti->id, 'name' => 'Sistem Informasi'],
                    ['faculty_id' => $fsti->id, 'name' => 'Bisnis Digital'],
                    ['faculty_id' => $fsti->id, 'name' => 'Teknik Elektro'],
                ];

                $fpb_study_programs = [
                    ['faculty_id' => $fpb->id, 'name' => 'Teknik Perkapalan'],
                    ['faculty_id' => $fpb->id, 'name' => 'Teknik Kelautan'],
                    ['faculty_id' => $fpb->id, 'name' => 'Teknik Lingkungan'],
                    ['faculty_id' => $fpb->id, 'name' => 'Teknik Sipil'],
                    ['faculty_id' => $fpb->id, 'name' => 'Perencanaan Wilayah dan Kota'],
                    ['faculty_id' => $fpb->id, 'name' => 'Arsitektur'],
                    ['faculty_id' => $fpb->id, 'name' => 'Desain Komunikasi Visual'],
                ];

                $frti_study_programs = [
                    ['faculty_id' => $frti->id, 'name' => 'Teknik Mesin'],
                    ['faculty_id' => $frti->id, 'name' => 'Teknik Industri'],
                    ['faculty_id' => $frti->id, 'name' => 'Teknik Logistik'],
                    ['faculty_id' => $frti->id, 'name' => 'Teknik Material dan Metalurgi'],
                    ['faculty_id' => $frti->id, 'name' => 'Teknologi Pangan'],
                    ['faculty_id' => $frti->id, 'name' => 'Teknik Kimia'],
                    ['faculty_id' => $frti->id, 'name' => 'Rekayasa Keselamatan'],
                ];

                foreach ($fsti_study_programs as $program) {
                    StudyProgram::create($program);
                }

                foreach ($fpb_study_programs as $program) {
                    StudyProgram::create($program);
                }

                foreach ($frti_study_programs as $program) {
                    StudyProgram::create($program);
                }
            });
        } catch (\Exception $e) {
            dd($e);
        }
    }
}
