<?php

// tests/Feature/Scheme/DeadlineExtensionTest.php
//
// BR-SCH-08 — perpanjangan batas waktu wajib diikuti pemberitahuan ke pemilik
// draft. Peneliti yang sudah menyerah karena batasnya lewat tidak punya alasan
// membuka aplikasi lagi; perpanjangan yang diam praktis sama dengan tidak ada.

use App\Actions\FormSubmission\StartSubmission;
use App\Actions\Period\ExtendSubmissionDates;
use App\Data\Request\PeriodDateInput;
use App\Enums\DeadlineType;
use App\Models\SubmissionDate;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\SubmissionDeadlineExtendedNotification;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

it('[SCH-RULE-11] perpanjangan memberi tahu pemilik draft', function () {
    Notification::fake();

    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $tenggat = $fx['period']->submissionDates->first();

    $diberitahu = ExtendSubmissionDates::run($fx['period'], [
        new PeriodDateInput($tenggat->type, $tenggat->cycle, now()->addMonth()->toDateTimeString()),
    ]);

    expect($diberitahu->notified)->toBe(1)
        ->and($diberitahu->extended)->toBeTrue();
    Notification::assertSentTo($fx['user'], SubmissionDeadlineExtendedNotification::class);
});

it('[SCH-RULE-12] deadline baru langsung berlaku sebagai gerbang akses', function () {
    $fx = wizardFixture();

    // Batasnya dimundurkan ke masa lalu dulu — fase tertutup.
    $fx['period']->submissionDates()->update(['datetime' => now()->subWeek()]);
    expect($fx['detail']->fresh()->isWithinDeadline())->toBeFalse();

    $tenggat = $fx['period']->submissionDates()->first();

    ExtendSubmissionDates::run($fx['period'], [
        new PeriodDateInput($tenggat->type, $tenggat->cycle, now()->addMonth()->toDateTimeString()),
    ]);

    // Tidak ada penyambungan ulang: tanggalnya diperbarui di tempat, jadi
    // FormPhaseDetail yang sama langsung menggerbangi dengan batas yang baru.
    expect($fx['detail']->fresh()->isWithinDeadline())->toBeTrue();
});

it('[SCH-RULE-13] tanpa draft berjalan tidak ada notifikasi terkirim', function () {
    Notification::fake();

    $fx = wizardFixture();
    $tenggat = $fx['period']->submissionDates->first();

    $diberitahu = ExtendSubmissionDates::run($fx['period'], [
        new PeriodDateInput($tenggat->type, $tenggat->cycle, now()->addMonth()->toDateTimeString()),
    ]);

    expect($diberitahu->notified)->toBe(0)
        ->and($diberitahu->extended)->toBeTrue();
    Notification::assertNothingSent();
});

it('[SCH-RULE-11] memajukan tanggal tidak memicu notifikasi perpanjangan', function () {
    Notification::fake();

    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $tenggat = $fx['period']->submissionDates->first();

    // Batas dimajukan, bukan dimundurkan — tidak ada kesempatan baru yang
    // diberikan, jadi tidak ada yang perlu diberitahukan.
    $diberitahu = ExtendSubmissionDates::run($fx['period'], [
        new PeriodDateInput($tenggat->type, $tenggat->cycle, now()->addDay()->toDateTimeString()),
    ]);

    expect($diberitahu->notified)->toBe(0)
        ->and($diberitahu->extended)->toBeFalse();
    Notification::assertNothingSent();
});

it('[SCH-RULE-11] hanya pemilik draft di periode itu yang diberi tahu', function () {
    Notification::fake();

    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $orangLain = User::factory()->create();

    $tenggat = $fx['period']->submissionDates->first();

    ExtendSubmissionDates::run($fx['period'], [
        new PeriodDateInput($tenggat->type, $tenggat->cycle, now()->addMonth()->toDateTimeString()),
    ]);

    Notification::assertNotSentTo($orangLain, SubmissionDeadlineExtendedNotification::class);
});

it('BR-RPT-09 entri audit perpanjangan mencatat Operator sebagai pelaku', function () {
    Notification::fake();

    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $operator = User::factory()->create();
    $tenggat = $fx['period']->submissionDates->first();

    ExtendSubmissionDates::run($fx['period'], [
        new PeriodDateInput($tenggat->type, $tenggat->cycle, now()->addMonth()->toDateTimeString()),
    ], $operator);

    $entry = Activity::query()->where('description', 'Tenggat periode diperpanjang')->firstOrFail();

    expect($entry->causer_id)->toBe($operator->id);
});

// BR-SCH-08 sampai ke layar. Nilai kembalian ExtendSubmissionDates sebelumnya
// dibuang controller, jadi Operator memperpanjang tenggat tanpa pernah tahu
// apakah ada yang dikabari — dan perpanjangan yang diam praktis sama dengan
// tidak diberikan.

it('[SCH-RULE-11] daftar periode membawa jumlah pemilik draft berjalan', function () {
    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    Permission::findOrCreate('periods.manage', 'web');
    $operator = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $operator->id]);
    $operator->givePermissionTo('periods.manage');

    $this->actingAs($operator->refresh())
        ->get(route('admin.submission-periods.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FormEngine/PeriodIndexPage')
            ->where('periods.data.0.id', $fx['period']->id)
            ->where('periods.data.0.draftOwnerCount', 1)
            ->etc());
});

// W73 — BR-SCH-08 menyasar pemilik DRAFT aktif. Draft di periode yang ditutup Operator
// terarsip (BR-SM-13) dan tetap terkunci walau tanggalnya mundur (BR-SCH-07).
it('perpanjangan di periode yang ditutup Operator tidak memberi tahu siapa pun', function () {
    Notification::fake();

    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $fx['period']->update(['is_force_closed' => true]);
    $tenggat = $fx['period']->submissionDates->first();

    $hasil = ExtendSubmissionDates::run($fx['period']->fresh(), [
        new PeriodDateInput($tenggat->type, $tenggat->cycle, now()->addMonths(2)->toDateTimeString()),
    ]);

    expect($hasil->extended)->toBeTrue()
        ->and($hasil->notified)->toBe(0)
        ->and(Activity::query()->where('description', 'Tenggat periode diperpanjang')->exists())->toBeFalse();
    Notification::assertNothingSent();
});

it('flash deadlineExtended hanya saat tenggat mundur', function () {
    Notification::fake();
    $this->seed(PermissionSeeder::class);
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $fx = wizardFixture();
    $date = $fx['period']->submissionDates()->firstOrFail();
    $payload = fn (string $at) => [
        'name' => $fx['period']->name,
        'submission_dates' => [['type' => $date->type->value, 'cycle' => $date->cycle, 'date' => $at]],
        'form_phase_ids' => [$fx['phase']->id],
    ];

    $this->actingAs($operator)
        ->put(route('admin.submission-periods.update', $fx['period']), $payload($date->datetime->copy()->subDay()->toDateTimeString()))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlashMissing('deadlineExtended');

    // SCH-RULE-13 — mundur tanpa draft berjalan: layar tetap tahu ada perpanjangan.
    $this->actingAs($operator)
        ->put(route('admin.submission-periods.update', $fx['period']), $payload(now()->addMonths(3)->toDateTimeString()))
        ->assertInertiaFlash('deadlineExtended', 0);
});

it('[SCH-RULE-16] menggeser Batas Evaluasi tidak memberi tahu pemilik draft', function () {
    Notification::fake();

    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $submit = $fx['period']->submissionDates()->firstOrFail();
    SubmissionDate::create([
        'type' => DeadlineType::Evaluation,
        'datetime' => now()->addWeek(),
        'submission_period_id' => $fx['period']->id,
    ]);

    // BR-SCH-08 (K8e) — Batas Evaluasi tidak dikerjakan peneliti: memundurkannya bukan kesempatan baru baginya.
    $hasil = ExtendSubmissionDates::run($fx['period']->fresh(), [
        new PeriodDateInput(DeadlineType::Submit, null, $submit->datetime->toDateTimeString()),
        new PeriodDateInput(DeadlineType::Evaluation, null, now()->addMonth()->toDateTimeString()),
    ]);

    expect($hasil->notified)->toBe(0)
        ->and($hasil->extended)->toBeFalse();
    Notification::assertNothingSent();
});

it('[SCH-RULE-11] memundurkan Batas Revisi memberi tahu pemilik draft', function () {
    Notification::fake();

    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $submit = $fx['period']->submissionDates()->firstOrFail();
    SubmissionDate::create([
        'type' => DeadlineType::Revision,
        'datetime' => now()->addWeek(),
        'submission_period_id' => $fx['period']->id,
    ]);

    $hasil = ExtendSubmissionDates::run($fx['period']->fresh(), [
        new PeriodDateInput(DeadlineType::Submit, null, $submit->datetime->toDateTimeString()),
        new PeriodDateInput(DeadlineType::Revision, null, now()->addMonth()->toDateTimeString()),
    ]);

    expect($hasil->notified)->toBe(1);
    Notification::assertSentTo($fx['user'], SubmissionDeadlineExtendedNotification::class);
});

it('[SCH-RULE-12] Batas Evaluasi yang dimundurkan langsung berlaku bagi reviewer yang sudah ditugaskan', function () {
    $fx = reviewedDetailFixture(now()->subWeeks(2)->toDateTimeString(), now()->subDay()->startOfSecond()->toDateTimeString());
    expect($fx['reviewer']->can('evaluate', $fx['assignment']))->toBeFalse();

    $baru = now()->addMonth()->startOfSecond();
    ExtendSubmissionDates::run($fx['period']->fresh(), [
        new PeriodDateInput(DeadlineType::Submit, null, $fx['detail']->submissionDate->datetime->toDateTimeString()),
        new PeriodDateInput(DeadlineType::Evaluation, null, $baru->toDateTimeString()),
    ]);

    // K8e — pemegang periods.manage menggeser tenggat untuk semua; penugasan menyimpan salinan
    // tenggatnya (due_date), jadi salinan yang diturunkan dari tanggal itu ikut bergeser.
    $assignment = $fx['assignment']->fresh();
    expect($assignment->due_date->equalTo($baru))->toBeTrue()
        ->and($fx['reviewer']->can('evaluate', $assignment))->toBeTrue();
});

it('[SCH-RULE-12] tenggat penugasan yang disetel tangan tidak ditimpa pergeseran Batas Evaluasi', function () {
    $fx = reviewedDetailFixture(now()->subWeeks(2)->toDateTimeString(), now()->addDay()->startOfSecond()->toDateTimeString());
    $tangan = now()->addDays(3)->startOfSecond();
    $fx['assignment']->update(['due_date' => $tangan]);

    ExtendSubmissionDates::run($fx['period']->fresh(), [
        new PeriodDateInput(DeadlineType::Submit, null, $fx['detail']->submissionDate->datetime->toDateTimeString()),
        new PeriodDateInput(DeadlineType::Evaluation, null, now()->addMonth()->toDateTimeString()),
    ]);

    expect($fx['assignment']->fresh()->due_date->equalTo($tangan))->toBeTrue();
});
