<?php

// Katalog bersama data uji QA (docs/design/2026-11-13-seeder-data-uji-qa-design.md, "Katalog bersama"):
// lahir di awal tahun lalu lewat jalur Form Builder, supaya pengajuan tahun lalu pun melihat isiannya.

use App\Models\Announcement;
use App\Models\User;
use App\Support\SubmissionPeriodGate;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Qa\QaCatalog;
use Database\Seeders\Qa\QaClock;
use Illuminate\Support\Carbon;

it('membangun katalog yang menawarkan dua form di periode tahun ini', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $catalog = QaCatalog::build($admin);
    $last = now()->year - 1;

    expect($catalog->schemes)->toHaveKeys(['dasar', 'terapan', 'pengabdian'])
        ->and(collect($catalog->schemes)->every(fn ($scheme): bool => $scheme->fresh()->is_active))->toBeTrue()
        ->and($catalog->forms['penelitian']->published_at)->not->toBeNull()
        ->and($catalog->forms['pengabdian']->published_at)->not->toBeNull()
        ->and($catalog->forms['penelitian']->formFields()->count())->toBe(8)
        ->and($catalog->forms['pengabdian']->formFields()->count())->toBe(7)
        // Review Focus 2 — isian lahir di tahun lalu, sebelum pengajuan mana pun.
        ->and($catalog->forms['penelitian']->formFields()->max('created_at'))->toStartWith((string) $last)
        ->and($catalog->periods['last']->name)->toBe("Periode {$last}")
        ->and(SubmissionPeriodGate::isFullyClosed($catalog->periods['last']))->toBeTrue()
        ->and($catalog->steps['penelitian']['submit']->needs_review)->toBeTrue()
        ->and($catalog->steps['pengabdian']['monev1']->deadline_cycle)->toBe(1)
        ->and(Announcement::count())->toBe(3);

    // Tidak throw = periode tahun ini menerima pengajuan baru (BR-FE-01).
    SubmissionPeriodGate::assertOpen($catalog->periods['current']);
});

it('membuat periode tahun lalu yang terbuka pada jamnya sendiri', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $catalog = QaCatalog::build($admin);

    QaClock::at(Carbon::create(now()->year - 1, 2, 10, 9), function () use ($catalog): void {
        SubmissionPeriodGate::assertOpen($catalog->periods['last']);
    });

    expect(true)->toBeTrue();
});
