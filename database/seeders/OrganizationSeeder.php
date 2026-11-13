<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\StudyProgram;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $itk = $this->upsertOrganization(
            ['name' => 'Institut Teknologi Kalimantan', 'type' => 'institution'],
            [
                'parent_id' => null,
                'is_active' => true,
                'metadata' => ['code' => 'ITK'],
            ],
        );

        // Struktur dari pemilik (2026-11-13): Fakultas → Jurusan → Program Studi.
        $this->createFaculty($itk->id, 'Fakultas Sains dan Teknologi Informasi', 'FSTI', [
            ['name' => 'Jurusan Sains dan Analitika Data', 'code' => 'JSAD', 'programs' => [
                ['name' => 'Matematika', 'code' => 'MATH'],
                ['name' => 'Ilmu Aktuaria', 'code' => 'AKTR'],
                ['name' => 'Statistika', 'code' => 'STAT'],
                ['name' => 'Fisika', 'code' => 'FIS'],
            ]],
            ['name' => 'Jurusan Teknik Elektro, Informatika, dan Bisnis', 'code' => 'JTEIB', 'programs' => [
                ['name' => 'Informatika', 'code' => 'IF'],
                ['name' => 'Sistem Informasi', 'code' => 'SI'],
                ['name' => 'Bisnis Digital', 'code' => 'BD'],
                ['name' => 'Teknik Elektro', 'code' => 'EL'],
                ['name' => 'Teknik Biomedis', 'code' => 'TBM'],
            ]],
        ]);

        $this->createFaculty($itk->id, 'Fakultas Pembangunan Berkelanjutan', 'FPB', [
            ['name' => 'Jurusan Teknologi Kemaritiman', 'code' => 'JTK', 'programs' => [
                ['name' => 'Teknik Perkapalan', 'code' => 'TKP'],
                ['name' => 'Teknik Kelautan', 'code' => 'TKL'],
                ['name' => 'Teknik Lingkungan', 'code' => 'TL'],
                ['name' => 'Teknik Sistem Perkapalan', 'code' => 'TSP'],
                ['name' => 'Teknik Transportasi Laut', 'code' => 'TTL'],
            ]],
            ['name' => 'Jurusan Teknik Sipil dan Perencanaan', 'code' => 'JTSP', 'programs' => [
                ['name' => 'Teknik Sipil', 'code' => 'TS'],
                ['name' => 'Perencanaan Wilayah dan Kota', 'code' => 'PWK'],
                ['name' => 'Arsitektur', 'code' => 'ARS'],
                ['name' => 'Desain Komunikasi Visual', 'code' => 'DKV'],
                ['name' => 'Teknik Geomatika', 'code' => 'GEO'],
            ]],
        ]);

        $this->createFaculty($itk->id, 'Fakultas Rekayasa dan Teknologi Industri', 'FRTI', [
            ['name' => 'Jurusan Teknologi Industri', 'code' => 'JTI', 'programs' => [
                ['name' => 'Teknik Mesin', 'code' => 'TM'],
                ['name' => 'Teknik Industri', 'code' => 'TI'],
                ['name' => 'Teknik Logistik', 'code' => 'TLG'],
                ['name' => 'Teknik Material dan Metalurgi', 'code' => 'TMM'],
            ]],
            ['name' => 'Jurusan Rekayasa Industri', 'code' => 'JRI', 'programs' => [
                ['name' => 'Teknologi Pangan', 'code' => 'TP'],
                ['name' => 'Teknik Kimia', 'code' => 'TK'],
                ['name' => 'Rekayasa Keselamatan', 'code' => 'RK'],
            ]],
        ]);
    }

    /** @param  list<array{name: string, code: string, programs: list<array{name: string, code: string}>}>  $departments */
    private function createFaculty(int $institutionId, string $name, string $code, array $departments): void
    {
        $faculty = $this->upsertOrganization(
            ['name' => $name, 'type' => 'faculty'],
            [
                'parent_id' => $institutionId,
                'is_active' => true,
                'metadata' => ['code' => $code],
            ],
        );

        foreach ($departments as $department) {
            $jurusan = $this->upsertOrganization(
                ['name' => $department['name'], 'type' => 'department'],
                [
                    'parent_id' => $faculty->id,
                    'is_active' => true,
                    'metadata' => ['code' => $department['code']],
                ],
            );

            $this->createStudyPrograms($jurusan->id, $department['programs']);
        }
    }

    /** @param  list<array{name: string, code: string}>  $programs */
    private function createStudyPrograms(int $departmentId, array $programs): void
    {
        foreach ($programs as $prodi) {
            $metadata = ['code' => $prodi['code']];

            $legacyStudyProgram = StudyProgram::where('name', $prodi['name'])->first();
            if ($legacyStudyProgram !== null) {
                $metadata['legacy'] = ['study_program_id' => $legacyStudyProgram->id];
            }

            // Prodi yang sudah ada dipindah ke jurusannya (upsert parent_id), bukan diduplikasi.
            $this->upsertOrganization(
                ['name' => $prodi['name'], 'type' => 'study_program'],
                [
                    'parent_id' => $departmentId,
                    'is_active' => true,
                    'metadata' => $metadata,
                ],
            );
        }
    }

    /**
     * @param  array{name: string, type: string}  $identity
     * @param  array{parent_id?: int|null, is_active: bool, metadata: array<string, mixed>}  $attributes
     */
    private function upsertOrganization(array $identity, array $attributes): Organization
    {
        $organization = Organization::firstOrCreate(
            $identity,
            [
                'parent_id' => $attributes['parent_id'] ?? null,
                'is_active' => $attributes['is_active'],
                'metadata' => $attributes['metadata'],
            ],
        );

        $updates = [];

        if (array_key_exists('parent_id', $attributes) && $organization->parent_id !== $attributes['parent_id']) {
            $updates['parent_id'] = $attributes['parent_id'];
        }

        if ($organization->is_active !== $attributes['is_active']) {
            $updates['is_active'] = $attributes['is_active'];
        }

        $mergedMetadata = $this->mergeMetadata(
            is_array($organization->metadata) ? $organization->metadata : [],
            $attributes['metadata'],
        );

        if ($mergedMetadata !== $organization->metadata) {
            $updates['metadata'] = $mergedMetadata;
        }

        if ($updates !== []) {
            $organization->fill($updates);
            $organization->save();
        }

        return $organization->refresh();
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeMetadata(array $existing, array $incoming): array
    {
        $merged = $existing;

        foreach ($incoming as $key => $value) {
            if ($key === 'legacy' && isset($merged['legacy']) && is_array($merged['legacy']) && is_array($value)) {
                $merged['legacy'] = array_merge($value, $merged['legacy']);

                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }
}
