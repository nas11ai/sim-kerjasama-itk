<?php

namespace Database\Seeders;

use App\Models\Faculty;
use DB;
use Illuminate\Database\Seeder;

class FacultySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            DB::transaction(function () {
                $faculties = [
                    'Fakultas Sains dan Teknologi Informasi',
                    'Fakultas Pembangunan Berkelanjutan',
                    'Fakultas Rekayasa dan Teknologi Industri',
                ];

                foreach ($faculties as $faculty) {
                    Faculty::create([
                        'name' => $faculty,
                    ]);
                }
            });
        } catch (\Exception $e) {
        }
    }
}
