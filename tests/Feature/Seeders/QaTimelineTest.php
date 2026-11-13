<?php

// Linimasa "tahun ini" tidak boleh tumpah ke tahun lalu saat seeder dijalankan di awal Januari:
// tahun statistik dibaca dari audit submit (SubmissionYear), jadi skenario tahun ini harus tetap
// tercatat di tahun berjalan berapa pun tanggal run-nya.

use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Submission\SubmissionYear;
use App\Support\PrivateUploadDisk;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Qa\QaAccounts;
use Database\Seeders\Qa\QaCatalog;
use Database\Seeders\Qa\QaClock;
use Database\Seeders\Qa\QaScenarios;
use Database\Seeders\Qa\QaSubmissionFlow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

it('menjaga skenario tahun ini di tahun berjalan walau dijalankan 3 Januari', function () {
    $year = now()->year;
    $this->travelTo(Carbon::create($year, 1, 3, 10));
    Storage::fake(PrivateUploadDisk::NAME);
    $this->seed(DatabaseSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $catalog = QaCatalog::build($admin);

    $s = QaScenarios::build(QaAccounts::build(1), new QaSubmissionFlow($catalog));

    $submitted = array_map(fn (FormSubmission $x): int => $x->id, array_slice($s, 2, null, true));
    expect($catalog->periods['current']->created_at->year)->toBe($year)
        ->and(SubmissionYear::apply(FormSubmission::query()->whereKey($submitted), $year)->count())->toBe(10);
});

it('memampatkan jarak hari ke dalam tahun berjalan', function () {
    $this->travelTo(Carbon::create(now()->year, 1, 3, 10));

    expect(QaClock::ago(42)->greaterThanOrEqualTo(now()->startOfYear()))->toBeTrue()
        ->and(QaClock::ago(35)->greaterThan(QaClock::ago(42)))->toBeTrue()
        ->and(QaClock::ago(2)->lessThan(now()))->toBeTrue();

    // Di luar Januari jaraknya hari sungguhan.
    $this->travelTo(Carbon::create(now()->year, 6, 15, 10));
    expect(QaClock::ago(35)->toDateString())->toBe(now()->subDays(35)->toDateString());
});
