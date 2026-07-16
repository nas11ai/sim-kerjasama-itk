<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $itk = Organization::firstOrCreate(
            ['name' => 'Institut Teknologi Kalimantan', 'type' => 'institution', 'parent_id' => null],
            ['is_active' => true, 'metadata' => ['code' => 'ITK']],
        );

        $this->createFaculty($itk->id, 'Fakultas Sains dan Teknologi Informasi', 'FSTI', [
            ['name' => 'Matematika', 'code' => 'MATH', 'legacy_study_program_id' => 1],
            ['name' => 'Ilmu Aktuaria', 'code' => 'AKTR', 'legacy_study_program_id' => 2],
            ['name' => 'Statistika', 'code' => 'STAT', 'legacy_study_program_id' => 3],
            ['name' => 'Fisika', 'code' => 'FIS', 'legacy_study_program_id' => 4],
            ['name' => 'Informatika', 'code' => 'IF', 'legacy_study_program_id' => 5],
            ['name' => 'Sistem Informasi', 'code' => 'SI', 'legacy_study_program_id' => 6],
            ['name' => 'Bisnis Digital', 'code' => 'BD', 'legacy_study_program_id' => 7],
            ['name' => 'Teknik Elektro', 'code' => 'EL', 'legacy_study_program_id' => 8],
            ['name' => 'Teknik Biomedis', 'code' => 'TBM'],
        ]);

        $this->createFaculty($itk->id, 'Fakultas Pembangunan Berkelanjutan', 'FPB', [
            ['name' => 'Teknik Perkapalan', 'code' => 'TKP', 'legacy_study_program_id' => 9],
            ['name' => 'Teknik Kelautan', 'code' => 'TKL', 'legacy_study_program_id' => 10],
            ['name' => 'Teknik Sistem Perkapalan', 'code' => 'TSP'],
            ['name' => 'Teknik Transportasi Laut', 'code' => 'TTL'],
            ['name' => 'Teknik Lingkungan', 'code' => 'TL', 'legacy_study_program_id' => 11],
            ['name' => 'Teknik Sipil', 'code' => 'TS', 'legacy_study_program_id' => 12],
            ['name' => 'Perencanaan Wilayah dan Kota', 'code' => 'PWK', 'legacy_study_program_id' => 13],
            ['name' => 'Arsitektur', 'code' => 'ARS', 'legacy_study_program_id' => 14],
            ['name' => 'Desain Komunikasi Visual', 'code' => 'DKV', 'legacy_study_program_id' => 15],
        ]);

        $this->createFaculty($itk->id, 'Fakultas Rekayasa dan Teknologi Industri', 'FRTI', [
            ['name' => 'Teknik Mesin', 'code' => 'TM', 'legacy_study_program_id' => 16],
            ['name' => 'Teknik Industri', 'code' => 'TI', 'legacy_study_program_id' => 17],
            ['name' => 'Teknik Logistik', 'code' => 'TLG', 'legacy_study_program_id' => 18],
            ['name' => 'Teknik Material dan Metalurgi', 'code' => 'TMM', 'legacy_study_program_id' => 19],
            ['name' => 'Teknologi Pangan', 'code' => 'TP', 'legacy_study_program_id' => 20],
            ['name' => 'Teknik Kimia', 'code' => 'TK', 'legacy_study_program_id' => 21],
            ['name' => 'Rekayasa Keselamatan', 'code' => 'RK', 'legacy_study_program_id' => 22],
        ]);
    }

    /** @param array<int, array{name: string, code: string, legacy_study_program_id?: int}> $programs */
    private function createFaculty(int $institutionId, string $name, string $code, array $programs): void
    {
        $faculty = Organization::firstOrCreate(
            ['name' => $name, 'type' => 'faculty', 'parent_id' => $institutionId],
            ['is_active' => true, 'metadata' => ['code' => $code]],
        );

        foreach ($programs as $prodi) {
            $metadata = ['code' => $prodi['code']];

            if (isset($prodi['legacy_study_program_id'])) {
                $metadata['legacy'] = ['study_program_id' => $prodi['legacy_study_program_id']];
            }

            Organization::firstOrCreate(
                ['name' => $prodi['name'], 'type' => 'study_program', 'parent_id' => $faculty->id],
                ['is_active' => true, 'metadata' => $metadata],
            );
        }
    }
}
