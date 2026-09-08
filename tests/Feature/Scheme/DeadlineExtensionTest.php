<?php

// tests/Feature/Scheme/DeadlineExtensionTest.php
//
// BR-SCH-08 — perpanjangan batas waktu wajib diikuti pemberitahuan ke pemilik
// draft. Peneliti yang sudah menyerah karena batasnya lewat tidak punya alasan
// membuka aplikasi lagi; perpanjangan yang diam praktis sama dengan tidak ada.

use App\Actions\FormSubmission\StartSubmission;
use App\Actions\Period\ExtendSubmissionDates;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\SubmissionDeadlineExtendedNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

it('[SCH-RULE-11] perpanjangan memberi tahu pemilik draft', function () {
    Notification::fake();

    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $label = $fx['period']->submissionDates->first()->submissionDateLabel->name;

    $diberitahu = ExtendSubmissionDates::run($fx['period'], [
        ['label' => $label, 'date' => now()->addMonth()->toDateTimeString()],
    ]);

    expect($diberitahu)->toBe(1);
    Notification::assertSentTo($fx['user'], SubmissionDeadlineExtendedNotification::class);
});

it('[SCH-RULE-12] deadline baru langsung berlaku sebagai gerbang akses', function () {
    $fx = wizardFixture();

    // Batasnya dimundurkan ke masa lalu dulu — fase tertutup.
    $fx['period']->submissionDates()->update(['datetime' => now()->subWeek()]);
    expect($fx['detail']->fresh()->isWithinDeadline())->toBeFalse();

    $label = $fx['period']->submissionDates()->first()->submissionDateLabel->name;

    ExtendSubmissionDates::run($fx['period'], [
        ['label' => $label, 'date' => now()->addMonth()->toDateTimeString()],
    ]);

    // Tidak ada penyambungan ulang: tanggalnya diperbarui di tempat, jadi
    // FormPhaseDetail yang sama langsung menggerbangi dengan batas yang baru.
    expect($fx['detail']->fresh()->isWithinDeadline())->toBeTrue();
});

it('[SCH-RULE-13] tanpa draft berjalan tidak ada notifikasi terkirim', function () {
    Notification::fake();

    $fx = wizardFixture();
    $label = $fx['period']->submissionDates->first()->submissionDateLabel->name;

    $diberitahu = ExtendSubmissionDates::run($fx['period'], [
        ['label' => $label, 'date' => now()->addMonth()->toDateTimeString()],
    ]);

    expect($diberitahu)->toBe(0);
    Notification::assertNothingSent();
});

it('[SCH-RULE-11] memajukan tanggal tidak memicu notifikasi perpanjangan', function () {
    Notification::fake();

    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $label = $fx['period']->submissionDates->first()->submissionDateLabel->name;

    // Batas dimajukan, bukan dimundurkan — tidak ada kesempatan baru yang
    // diberikan, jadi tidak ada yang perlu diberitahukan.
    $diberitahu = ExtendSubmissionDates::run($fx['period'], [
        ['label' => $label, 'date' => now()->addDay()->toDateTimeString()],
    ]);

    expect($diberitahu)->toBe(0);
    Notification::assertNothingSent();
});

it('[SCH-RULE-11] hanya pemilik draft di periode itu yang diberi tahu', function () {
    Notification::fake();

    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $orangLain = User::factory()->create();

    $label = $fx['period']->submissionDates->first()->submissionDateLabel->name;

    ExtendSubmissionDates::run($fx['period'], [
        ['label' => $label, 'date' => now()->addMonth()->toDateTimeString()],
    ]);

    Notification::assertNotSentTo($orangLain, SubmissionDeadlineExtendedNotification::class);
});

// BR-SCH-08 sampai ke layar. Nilai kembalian ExtendSubmissionDates sebelumnya
// dibuang controller, jadi Operator memperpanjang tenggat tanpa pernah tahu
// apakah ada yang dikabari — dan perpanjangan yang diam praktis sama dengan
// tidak diberikan.

it('[SCH-RULE-11] halaman edit periode membawa jumlah draft berjalan', function () {
    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    Permission::findOrCreate('users.manage', 'web');
    $operator = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $operator->id]);
    $operator->givePermissionTo('users.manage');

    $this->actingAs($operator->refresh())
        ->get(route('admin.submission-periods.edit', $fx['period']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SubmissionPeriods/EditPage', false)
            ->where('runningDrafts', 1)
            ->etc());
});
