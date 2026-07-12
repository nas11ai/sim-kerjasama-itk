<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        Organization::firstOrCreate(
            ['name' => 'Institut Teknologi Kalimantan', 'type' => 'institution', 'parent_id' => null],
            ['is_active' => true, 'metadata' => ['code' => 'ITK']],
        );

        $this->createFaculty('Fakultas Sains dan Teknologi Informasi', 'FSTI', [
            ['name' => 'Matematika', 'code' => 'MATH'],
            ['name' => 'Ilmu Aktuaria', 'code' => 'AKTR'],
            ['name' => 'Statistika', 'code' => 'STAT'],
            ['name' => 'Fisika', 'code' => 'FIS'],
            ['name' => 'Informatika', 'code' => 'IF'],
            ['name' => 'Sistem Informasi', 'code' => 'SI'],
            ['name' => 'Bisnis Digital', 'code' => 'BD'],
            ['name' => 'Teknik Elektro', 'code' => 'EL'],
            ['name' => 'Teknik Biomedis', 'code' => 'TBM'],
        ]);

        $this->createFaculty('Fakultas Pembangunan Berkelanjutan', 'FPB', [
            ['name' => 'Teknik Perkapalan', 'code' => 'TKP'],
            ['name' => 'Teknik Kelautan', 'code' => 'TKL'],
            ['name' => 'Teknik Sistem Perkapalan', 'code' => 'TSP'],
            ['name' => 'Teknik Transportasi Laut', 'code' => 'TTL'],
            ['name' => 'Teknik Lingkungan', 'code' => 'TL'],
            ['name' => 'Teknik Sipil', 'code' => 'TS'],
            ['name' => 'Perencanaan Wilayah dan Kota', 'code' => 'PWK'],
            ['name' => 'Arsitektur', 'code' => 'ARS'],
            ['name' => 'Desain Komunikasi Visual', 'code' => 'DKV'],
        ]);

        $this->createFaculty('Fakultas Rekayasa dan Teknologi Industri', 'FRTI', [
            ['name' => 'Teknik Mesin', 'code' => 'TM'],
            ['name' => 'Teknik Industri', 'code' => 'TI'],
            ['name' => 'Teknik Logistik', 'code' => 'TLG'],
            ['name' => 'Teknik Material dan Metalurgi', 'code' => 'TMM'],
            ['name' => 'Teknologi Pangan', 'code' => 'TP'],
            ['name' => 'Teknik Kimia', 'code' => 'TK'],
            ['name' => 'Rekayasa Keselamatan', 'code' => 'RK'],
        ]);
    }

    /** @param array<int, array{name: string, code: string}> $programs */
    private function createFaculty(string $name, string $code, array $programs): void
    {
        $faculty = Organization::firstOrCreate(
            ['name' => $name, 'type' => 'faculty', 'parent_id' => Organization::where('type', 'institution')->value('id')],
            ['is_active' => true, 'metadata' => ['code' => $code]],
        );

        foreach ($programs as $prodi) {
            Organization::firstOrCreate(
                ['name' => $prodi['name'], 'type' => 'study_program', 'parent_id' => $faculty->id],
                ['is_active' => true, 'metadata' => ['code' => $prodi['code']]],
            );
        }
    }
}
