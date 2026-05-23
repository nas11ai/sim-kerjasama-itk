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
                $fsti_study_programs = [
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Sains dan Teknologi Informasi')->first()->id,
                        'name' => 'Matematika',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Sains dan Teknologi Informasi')->first()->id,
                        'name' => 'Ilmu Aktuaria',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Sains dan Teknologi Informasi')->first()->id,
                        'name' => 'Statistika',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Sains dan Teknologi Informasi')->first()->id,
                        'name' => 'Fisika',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Sains dan Teknologi Informasi')->first()->id,
                        'name' => 'Informatika',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Sains dan Teknologi Informasi')->first()->id,
                        'name' => 'Sistem Informasi',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Sains dan Teknologi Informasi')->first()->id,
                        'name' => 'Bisnis Digital',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Sains dan Teknologi Informasi')->first()->id,
                        'name' => 'Teknik Elektro',
                    ],
                ];

                $fpb_study_programs = [
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Pembangunan Berkelanjutan')->first()->id,
                        'name' => 'Teknik Perkapalan',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Pembangunan Berkelanjutan')->first()->id,
                        'name' => 'Teknik Kelautan',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Pembangunan Berkelanjutan')->first()->id,
                        'name' => 'Teknik Lingkungan',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Pembangunan Berkelanjutan')->first()->id,
                        'name' => 'Teknik Sipil',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Pembangunan Berkelanjutan')->first()->id,
                        'name' => 'Perencanaan Wilayah dan Kota',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Pembangunan Berkelanjutan')->first()->id,
                        'name' => 'Arsitektur',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Pembangunan Berkelanjutan')->first()->id,
                        'name' => 'Desain Komunikasi Visual',
                    ],
                ];

                $frti_study_programs = [
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Rekayasa dan Teknologi Industri')->first()->id,
                        'name' => 'Teknik Mesin',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Rekayasa dan Teknologi Industri')->first()->id,
                        'name' => 'Teknik Industri',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Rekayasa dan Teknologi Industri')->first()->id,
                        'name' => 'Teknik Logistik',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Rekayasa dan Teknologi Industri')->first()->id,
                        'name' => 'Teknik Material dan Metalurgi',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Rekayasa dan Teknologi Industri')->first()->id,
                        'name' => 'Teknologi Pangan',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Rekayasa dan Teknologi Industri')->first()->id,
                        'name' => 'Teknik Kimia',
                    ],
                    [
                        'faculty_id' => Faculty::where('name', 'Fakultas Rekayasa dan Teknologi Industri')->first()->id,
                        'name' => 'Rekayasa Keselamatan',
                    ],
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
        }
    }
}
