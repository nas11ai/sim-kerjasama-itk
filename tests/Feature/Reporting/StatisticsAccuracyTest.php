<?php

// BR-RPT-06 — aturan akurasi statistik legacy yang dipertahankan v2 (W149): draft tidak
// dihitung, unit = unit pengaju menurut posisinya di pohon, dan setiap pengajuan dihitung sekali.

use App\Data\Reporting\StatisticsFilters;
use App\Data\View\Reporting\RankedCountData;
use App\Models\FormAccessControl;
use App\Models\Organization;
use App\Queries\Reporting\StatisticsOverview;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Submitted;

/** @return array<string, mixed> */
function accuracyOverview(): array
{
    return StatisticsOverview::run(new StatisticsFilters(null, null, null), true);
}

/**
 * @param  list<RankedCountData>  $rows
 * @return array<string, int>
 */
function countsByLabel(array $rows): array
{
    return collect($rows)->mapWithKeys(fn ($row) => [$row->label => $row->count])->all();
}

/** @return array{0: Organization, 1: Organization} fakultas dan prodi di bawah jurusannya */
function facultyWithDeepProgram(string $name, bool $programActive = true): array
{
    $faculty = Organization::create(['name' => "Fakultas {$name}", 'type' => 'faculty']);
    $department = Organization::create(['name' => "Jurusan {$name}", 'type' => 'department', 'parent_id' => $faculty->id]);
    $prodi = Organization::create([
        'name' => "Prodi {$name}", 'type' => 'study_program', 'parent_id' => $department->id, 'is_active' => $programActive,
    ]);

    return [$faculty, $prodi];
}

test('BR-RPT-06 draft tidak dihitung di total mana pun', function () {
    [, $prodi] = facultyWithDeepProgram('A');
    reportedSubmission(['status' => Submitted::class], unit: $prodi);
    reportedSubmission(['status' => NeedsRevision::class], unit: $prodi);
    reportedSubmission(['status' => Draft::class, 'is_submitted' => false], unit: $prodi);

    $result = accuracyOverview();

    expect(collect($result['kpis'])->firstWhere('key', 'submissions')->value)->toBe('2')
        ->and(collect($result['byStatus'])->sum('count'))->toBe(2)
        ->and(countsByLabel($result['byFaculty']))->toBe(['Fakultas A' => 2])
        ->and(countsByLabel($result['byStudyProgram']))->toBe(['Prodi A' => 2]);
});

test('BR-RPT-06 fakultas dan prodi menurut unit pengaju dan posisinya di pohon, aktif atau tidak', function () {
    [$faculty, $prodi] = facultyWithDeepProgram('A', programActive: false);
    reportedSubmission(unit: $prodi);
    reportedSubmission(unit: $faculty);

    $result = accuracyOverview();

    expect(countsByLabel($result['byFaculty']))->toBe(['Fakultas A' => 2])
        ->and(countsByLabel($result['byStudyProgram']))->toBe(['Prodi A' => 1]);
});

test('BR-RPT-06 pengajuan pada form dengan dua kontrol akses dihitung sekali', function () {
    [, $prodiA] = facultyWithDeepProgram('A');
    [, $prodiB] = facultyWithDeepProgram('B');
    $submission = reportedSubmission(unit: $prodiA);
    foreach ([$prodiA, $prodiB] as $unit) {
        FormAccessControl::create(['form_id' => $submission->form_id, 'permission' => 'submissions.create', 'organization_id' => $unit->id]);
    }

    $result = accuracyOverview();

    expect(countsByLabel($result['byFaculty']))->toBe(['Fakultas A' => 1])
        ->and(countsByLabel($result['byStudyProgram']))->toBe(['Prodi A' => 1]);
});
