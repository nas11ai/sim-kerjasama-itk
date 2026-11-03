<?php

// Close Early (ubiquitous language "Period Lifecycle") — Operator menutup periode yang
// masih terbuka: BR-SCH-07 mengunci seluruh fasenya, BR-SM-10 memberi tahu pemilik draft.

use App\Actions\Period\CloseSubmissionPeriod;
use App\Enums\DeadlineType;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Notifications\SubmissionPeriodClosedNotification;
use App\Support\Audit\AuditTrail;
use App\Support\SubmissionPeriodGate;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

/** @return array{0: User, 1: SubmissionPeriod, 2: User} pemilik draft, periode berjalan, Operator */
function closingFixture(string $deadline = '+1 week'): array
{
    $owner = User::factory()->create();
    $period = SubmissionPeriod::factory()->create(['is_force_closed' => false]);
    $period->submissionDates()->create([
        'type' => DeadlineType::Submit,
        'datetime' => now()->modify($deadline),
    ]);
    FormSubmission::factory()->create(['submitted_by' => $owner->id, 'submission_period_id' => $period->id]);

    $operator = User::factory()->create();
    $operator->assignRole('operator');

    return [$owner, $period, $operator];
}

function closingAudits(SubmissionPeriod $period): int
{
    return Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $period->getMorphClass())
        ->where('subject_id', $period->id)
        ->where('description', 'Periode pengajuan ditutup')
        ->count();
}

it('menutup periode menyetel is_force_closed dan mengaudit', function () {
    Notification::fake();
    [, $period, $operator] = closingFixture();

    $notified = CloseSubmissionPeriod::run($period, $operator);

    expect($period->fresh()->is_force_closed)->toBeTrue()
        ->and($notified)->toBe(1)
        ->and(closingAudits($period))->toBe(1);
});

it('[SM-ARCHIVE-02] pemilik draft diberi tahu saat periode ditutup', function () {
    Notification::fake();
    [$owner, $period, $operator] = closingFixture();

    CloseSubmissionPeriod::run($period, $operator);

    Notification::assertSentTo($owner, SubmissionPeriodClosedNotification::class);
});

it('pemilik pengajuan yang sudah submit tidak diberi tahu', function () {
    Notification::fake();
    [, $period, $operator] = closingFixture();
    $submitter = User::factory()->create();
    FormSubmission::factory()->create([
        'submitted_by' => $submitter->id,
        'submission_period_id' => $period->id,
        'is_submitted' => true,
    ]);

    CloseSubmissionPeriod::run($period, $operator);

    Notification::assertNotSentTo($submitter, SubmissionPeriodClosedNotification::class);
});

it('menutup periode yang sudah tertutup ditolak', function (bool $forceClosed, string $deadline) {
    Notification::fake();
    [, $period, $operator] = closingFixture($deadline);
    $period->update(['is_force_closed' => $forceClosed]);

    expect(fn () => CloseSubmissionPeriod::run($period->fresh(), $operator))
        ->toThrow(ValidationException::class, 'Periode ini sudah tertutup.');

    Notification::assertNothingSent();
    expect(closingAudits($period))->toBe(0);
})->with([
    'ditutup Operator' => [true, '+1 week'],
    'seluruh tenggat lewat' => [false, '-1 day'],
]);

it('periode yang ditutup mengunci fase meski tenggat belum lewat — akibat BR-SCH-07', function () {
    Notification::fake();
    [, $period, $operator] = closingFixture('+1 month');

    CloseSubmissionPeriod::run($period, $operator);

    expect(SubmissionPeriodGate::isOpen($period->fresh()))->toBeFalse();
});

it('wewenang tutup: operator lewat HTTP, peneliti 403', function () {
    Notification::fake();
    [, $period, $operator] = closingFixture();
    $researcher = User::factory()->create();
    $researcher->assignRole('researcher');

    $this->actingAs($researcher)->post(route('admin.submission-periods.close', $period))->assertForbidden();
    expect($period->fresh()->is_force_closed)->toBeFalse();

    $this->actingAs($operator)
        ->post(route('admin.submission-periods.close', $period))
        ->assertRedirect()
        ->assertSessionHas('success', 'Periode ditutup. 1 peneliti diberi tahu.');
});

it('menutup lewat HTTP periode yang sudah tertutup memberi galat validasi', function () {
    [, $period, $operator] = closingFixture();
    $period->update(['is_force_closed' => true]);

    $this->actingAs($operator)
        ->post(route('admin.submission-periods.close', $period))
        ->assertSessionHasErrors('period');
});
