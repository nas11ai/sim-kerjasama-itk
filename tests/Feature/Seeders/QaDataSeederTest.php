<?php

// Satu run penuh QaDataSeeder di database segar (docs/design/2026-11-13-seeder-data-uji-qa-design.md):
// empat set penguji plus volume institusi yang mengisi paginasi dan sebaran per fakultas.

use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\ResearchOutput;
use App\Models\User;
use App\Queries\Submission\SubmissionYear;
use App\Support\PrivateUploadDisk;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\QaDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Fakultas leluhur sebuah unit (prodi di bawah jurusan dihitung di fakultasnya). */
function facultyOf(?Organization $unit): ?int
{
    while ($unit !== null && $unit->type !== 'faculty') {
        $unit = $unit->parent;
    }

    return $unit?->id;
}

it('mengisi database segar untuk empat penguji', function () {
    Storage::fake(PrivateUploadDisk::NAME);
    $this->seed(DatabaseSeeder::class);

    $this->seed(QaDataSeeder::class);

    foreach (range(1, QaDataSeeder::SETS) as $k) {
        expect(FormSubmission::whereNull('parent_submission_id')->where('title', 'like', "[QA{$k}] %")->count())
            ->toBe(16, "set qa{$k}");
    }

    $main = FormSubmission::whereNull('parent_submission_id');
    expect((clone $main)->count())->toBeGreaterThanOrEqual(120)
        ->and((clone $main)->where('title', 'like', '[Latar] %')->count())->toBe(60)
        ->and(User::count())->toBeGreaterThanOrEqual(75)
        // Akun bawaan DatabaseSeeder (`@example.com`) bukan buatan seeder ini.
        ->and(User::where('email', 'not like', '%@simpas.test')->where('email', 'not like', '%@example.com')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0);

    // Judul luaran tanpa sisa awalan set (`[QA1]`, `[Latar]`): yang terlihat rusak akan dilaporkan sebagai bug.
    $titles = ResearchOutput::all()->map(fn (ResearchOutput $o): string => (string) ($o->metadata['title'] ?? ''));
    expect($titles)->not->toBeEmpty()
        ->and($titles->filter(fn (string $t): bool => str_contains($t, '[') || str_contains($t, ']'))->all())->toBe([]);

    // Setiap fakultas punya pengajuan tahun ini dan tahun lalu (perbandingan statistik).
    $faculties = Organization::where('type', 'faculty')->pluck('id');
    foreach ([now()->year, now()->year - 1] as $year) {
        $covered = SubmissionYear::apply(FormSubmission::query()->whereNull('parent_submission_id'), $year)
            ->with('submittedBy.userProfile.organization.parent.parent')
            ->get()
            ->map(fn (FormSubmission $s): ?int => facultyOf($s->submittedBy?->userProfile?->organization))
            ->filter()
            ->unique();

        expect($faculties->diff($covered)->all())->toBe([], "fakultas tanpa pengajuan {$year}");
    }
});
