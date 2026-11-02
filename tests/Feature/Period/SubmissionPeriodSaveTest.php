<?php

// Membuat dan merevisi periode lewat Action beraudit. Setiap SubmissionDate adalah
// batas akhir (SubmissionPeriodGate), bukan jendela mulai–selesai.

use App\Actions\FormSubmission\StartSubmission;
use App\Models\FormPhase;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Models\User;
use App\Notifications\SubmissionDeadlineExtendedNotification;
use App\Support\Audit\AuditTrail;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    // OPERATOR-09-01 — pemegang periods.manage (§4.4 K1), bukan users.manage.
    $this->admin = User::factory()->create();
    $this->admin->assignRole('operator');
    $this->phase = FormPhase::create(['title' => 'Fase Satu', 'is_active' => true]);
    $this->labelA = SubmissionDateLabel::create(['name' => 'Batas Awal']);
    $this->labelB = SubmissionDateLabel::create(['name' => 'Batas Akhir']);
});

function periodPayload(array $dates, array $overrides = []): array
{
    return array_merge([
        'name' => 'Periode Uji',
        'submission_dates' => $dates,
        'form_phase_ids' => [test()->phase->id],
    ], $overrides);
}

it('rejects two rows with the same label in one request', function () {
    $payload = periodPayload([
        ['label' => 'Batas Awal', 'date' => '2030-01-01'],
        ['label' => 'Batas Awal', 'date' => '2030-02-01'],
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.submission-periods.store'), $payload)
        ->assertSessionHasErrors('submission_dates');

    expect(SubmissionPeriod::count())->toBe(0);
});

it('accepts two deadlines with different labels on the same date', function () {
    $payload = periodPayload([
        ['label' => 'Batas Awal', 'date' => '2030-01-01'],
        ['label' => 'Batas Akhir', 'date' => '2030-01-01'],
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.submission-periods.store'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.submission-periods.index'));

    expect(SubmissionPeriod::firstOrFail()->submissionDates()->count())->toBe(2);
});

it('audits the creation of a period (BR-RPT-09)', function () {
    $payload = periodPayload([['label' => 'Batas Awal', 'date' => '2030-01-01']]);

    $this->actingAs($this->admin)->post(route('admin.submission-periods.store'), $payload);

    $period = SubmissionPeriod::firstOrFail();
    $entry = Activity::query()
        ->where('log_name', AuditTrail::LOG_NAME)
        ->where('subject_id', $period->id)
        ->where('subject_type', $period->getMorphClass())
        ->firstOrFail();

    expect($entry->causer_id)->toBe($this->admin->id);
    expect($period->submissionPeriodPhases()->count())->toBe(1);
});

it('audits the revision of a period (BR-RPT-09)', function () {
    $period = SubmissionPeriod::factory()->create(['name' => 'Lama']);
    $payload = periodPayload([['label' => 'Batas Awal', 'date' => '2030-01-01']], ['name' => 'Baru']);

    $this->actingAs($this->admin)
        ->put(route('admin.submission-periods.update', $period), $payload)
        ->assertSessionHasNoErrors();

    $entry = Activity::query()
        ->where('log_name', AuditTrail::LOG_NAME)
        ->where('subject_id', $period->id)
        ->where('description', 'Periode pengajuan direvisi')
        ->firstOrFail();

    expect($entry->properties['old']['name'])->toBe('Lama');
    expect($entry->properties['new']['name'])->toBe('Baru');
    expect($period->fresh()->name)->toBe('Baru');
});

it('sends no extension notice when the revision fails after the dates were extended (BR-SCH-08)', function () {
    Notification::fake();

    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $date = $fx['period']->submissionDates()->with('submissionDateLabel')->firstOrFail();
    $before = $date->datetime->copy();

    // Galat sesudah tanggal disinkronkan: tahap ditulis ulang paling akhir.
    SubmissionPeriodPhase::creating(static function (): void {
        throw new RuntimeException('galat tak terduga');
    });

    $payload = periodPayload(
        [['label' => $date->submissionDateLabel->name, 'date' => $before->copy()->addMonth()->toDateTimeString()]],
        ['form_phase_ids' => [$fx['phase']->id]],
    );

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($this->admin)
        ->put(route('admin.submission-periods.update', $fx['period']), $payload))
        ->toThrow(RuntimeException::class, 'galat tak terduga');

    Notification::assertNothingSent();
    expect($date->fresh()->datetime->equalTo($before))->toBeTrue();
});

it('sends the extension notice after a successful revision (BR-SCH-08)', function () {
    Notification::fake();

    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $date = $fx['period']->submissionDates()->with('submissionDateLabel')->firstOrFail();

    $payload = periodPayload(
        [['label' => $date->submissionDateLabel->name, 'date' => $date->datetime->copy()->addMonth()->toDateTimeString()]],
        ['form_phase_ids' => [$fx['phase']->id]],
    );

    $this->actingAs($this->admin)
        ->put(route('admin.submission-periods.update', $fx['period']), $payload)
        ->assertInertiaFlash('deadlineExtended', 1);

    Notification::assertSentTo($fx['user'], SubmissionDeadlineExtendedNotification::class);
});

it('does not paste the exception message onto the screen', function () {
    SubmissionPeriodPhase::creating(static function (): void {
        throw new RuntimeException('rahasia internal');
    });

    $payload = periodPayload([['label' => 'Batas Awal', 'date' => '2030-01-01']]);

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($this->admin)->post(route('admin.submission-periods.store'), $payload))
        ->toThrow(RuntimeException::class);
    expect(SubmissionPeriod::count())->toBe(0);
});

it('label di-trim dan dipakai ulang', function () {
    $payload = periodPayload([['label' => '  Batas Awal ', 'date' => '2030-01-01']]);

    $this->actingAs($this->admin)
        ->post(route('admin.submission-periods.store'), $payload)
        ->assertSessionHasNoErrors();

    expect(SubmissionDateLabel::count())->toBe(2)
        ->and(SubmissionPeriod::firstOrFail()->submissionDates()->sole()->submission_date_label_id)->toBe($this->labelA->id);
});

it('label baru dibuat saat periode disimpan — BR-FE-17 butuh tenggat per tahap', function () {
    $payload = periodPayload([
        ['label' => 'Batas Awal', 'date' => '2030-01-01'],
        ['label' => 'Batas Evaluasi', 'date' => '2030-02-01'],
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.submission-periods.store'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.submission-periods.index'));

    expect(SubmissionDateLabel::where('name', 'Batas Evaluasi')->exists())->toBeTrue();
});

it('dua label yang sama setelah di-trim ditolak', function () {
    $payload = periodPayload([
        ['label' => 'Batas Awal', 'date' => '2030-01-01'],
        ['label' => ' Batas Awal', 'date' => '2030-02-01'],
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.submission-periods.store'), $payload)
        ->assertSessionHasErrors('submission_dates');
});

it('peneliti tidak bisa menyimpan atau merevisi periode', function () {
    $researcher = User::factory()->create();
    $researcher->assignRole('researcher');
    $period = SubmissionPeriod::factory()->create();
    $payload = periodPayload([['label' => 'Batas Awal', 'date' => '2030-01-01']]);

    $this->actingAs($researcher)->post(route('admin.submission-periods.store'), $payload)->assertForbidden();
    $this->actingAs($researcher)->put(route('admin.submission-periods.update', $period), $payload)->assertForbidden();
});
