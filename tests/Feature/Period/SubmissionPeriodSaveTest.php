<?php

// Membuat dan merevisi periode lewat Action beraudit. Setiap SubmissionDate adalah
// batas akhir (SubmissionPeriodGate), bukan jendela mulai–selesai.

use App\Actions\FormSubmission\StartSubmission;
use App\Enums\DeadlineType;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Models\User;
use App\Notifications\SubmissionDeadlineExtendedNotification;
use App\Support\Audit\AuditTrail;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    // OPERATOR-09-01 — pemegang periods.manage (§4.4 K1), bukan users.manage.
    $this->admin = User::factory()->create();
    $this->admin->assignRole('operator');
    $this->phase = publishedPhase(['title' => 'Fase Satu', 'is_active' => true]);
});

function periodPayload(array $dates, array $overrides = []): array
{
    return array_merge([
        'name' => 'Periode Uji',
        'submission_dates' => $dates,
        'form_phase_ids' => [test()->phase->id],
    ], $overrides);
}

it('[FE-WINDOW-12] rejects two rows with the same type and cycle in one request', function () {
    $payload = periodPayload([
        ['type' => 'submit', 'date' => '2030-01-01'],
        ['type' => 'submit', 'date' => '2030-02-01'],
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.submission-periods.store'), $payload)
        ->assertSessionHasErrors('submission_dates');

    expect(SubmissionPeriod::count())->toBe(0);
});

it('accepts two deadlines of different types on the same date', function () {
    $payload = periodPayload([
        ['type' => 'submit', 'date' => '2030-01-01'],
        ['type' => 'evaluation', 'date' => '2030-01-01'],
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.submission-periods.store'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.submission-periods.index'));

    expect(SubmissionPeriod::firstOrFail()->submissionDates()->count())->toBe(2);
});

it('audits the creation of a period (BR-RPT-09)', function () {
    $payload = periodPayload([['type' => 'submit', 'date' => '2030-01-01']]);

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

it('menautkan tahap yang terkirim dua kali hanya sekali', function () {
    $payload = periodPayload([['type' => 'submit', 'date' => '2030-01-01']], [
        'form_phase_ids' => [$this->phase->id, $this->phase->id],
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.submission-periods.store'), $payload)
        ->assertSessionHasNoErrors();

    expect(SubmissionPeriodPhase::query()->where('form_phase_id', $this->phase->id)->count())->toBe(1);
});

// Tautan ganda membuat celah jadwal fase itu terhitung dua kali di kartu fase.
it('menolak tautan fase yang sama dua kali di basis data', function () {
    $period = SubmissionPeriod::factory()->create();
    SubmissionPeriodPhase::create(['submission_period_id' => $period->id, 'form_phase_id' => $this->phase->id]);
    SubmissionPeriodPhase::create(['submission_period_id' => $period->id, 'form_phase_id' => $this->phase->id]);
})->throws(UniqueConstraintViolationException::class);

it('audits the revision of a period (BR-RPT-09)', function () {
    $period = SubmissionPeriod::factory()->create(['name' => 'Lama']);
    $payload = periodPayload([['type' => 'submit', 'date' => '2030-01-01']], ['name' => 'Baru']);

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
    $date = $fx['period']->submissionDates()->firstOrFail();
    $before = $date->datetime->copy();

    // Galat sesudah tanggal disinkronkan: tahap ditulis ulang paling akhir.
    SubmissionPeriodPhase::creating(static function (): void {
        throw new RuntimeException('galat tak terduga');
    });

    $payload = periodPayload(
        [['type' => $date->type->value, 'cycle' => $date->cycle, 'date' => $before->copy()->addMonth()->toDateTimeString()]],
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
    $date = $fx['period']->submissionDates()->firstOrFail();

    $payload = periodPayload(
        [['type' => $date->type->value, 'cycle' => $date->cycle, 'date' => $date->datetime->copy()->addMonth()->toDateTimeString()]],
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

    $payload = periodPayload([['type' => 'submit', 'date' => '2030-01-01']]);

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($this->admin)->post(route('admin.submission-periods.store'), $payload))
        ->toThrow(RuntimeException::class);
    expect(SubmissionPeriod::count())->toBe(0);
});

it('stores the deadline type and cycle — BR-FE-25', function () {
    $payload = periodPayload([
        ['type' => 'submit', 'cycle' => null, 'date' => '2030-01-01'],
        ['type' => 'evaluation', 'cycle' => null, 'date' => '2030-02-01'],
        ['type' => 'submit', 'cycle' => 1, 'date' => '2030-06-01'],
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.submission-periods.store'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.submission-periods.index'));

    $labels = SubmissionPeriod::firstOrFail()->submissionDates()->orderBy('datetime')->get()
        ->map(fn ($date) => [$date->type, $date->cycle, $date->label()])->all();

    expect($labels)->toBe([
        [DeadlineType::Submit, null, 'Batas Submit'],
        [DeadlineType::Evaluation, null, 'Batas Evaluasi'],
        [DeadlineType::Submit, 1, 'Batas Laporan Monev I'],
    ]);
});

it('rejects an unknown deadline type — the labels are fixed, not typed (W72)', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.submission-periods.store'), periodPayload([['type' => 'batas awal', 'date' => '2030-01-01']]))
        ->assertSessionHasErrors('submission_dates.0.type');

    expect(SubmissionPeriod::count())->toBe(0);
});

it('rejects cycle zero', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.submission-periods.store'), periodPayload([['type' => 'submit', 'cycle' => 0, 'date' => '2030-01-01']]))
        ->assertSessionHasErrors('submission_dates.0.cycle');
});

// BR-FE-07 — langkah paling jauh Laporan Monev X; tanggal siklus 11 tidak dibaca langkah mana pun.
it('rejects a cycle beyond the last step cycle', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.submission-periods.store'), periodPayload([['type' => 'submit', 'cycle' => 11, 'date' => '2030-01-01']]))
        ->assertSessionHasErrors('submission_dates.0.cycle');
});

it('[FE-WINDOW-12] rejects a second Batas Evaluasi Monev I but accepts the main-cycle one', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.submission-periods.store'), periodPayload([
            ['type' => 'evaluation', 'cycle' => 1, 'date' => '2030-01-01'],
            ['type' => 'evaluation', 'cycle' => '1', 'date' => '2030-02-01'],
        ]))
        ->assertSessionHasErrors('submission_dates');

    $this->actingAs($this->admin)
        ->post(route('admin.submission-periods.store'), periodPayload([
            ['type' => 'evaluation', 'cycle' => 1, 'date' => '2030-01-01'],
            ['type' => 'evaluation', 'cycle' => null, 'date' => '2030-02-01'],
        ]))
        ->assertSessionHasNoErrors();
});

it('peneliti tidak bisa menyimpan atau merevisi periode', function () {
    $researcher = User::factory()->create();
    $researcher->assignRole('researcher');
    $period = SubmissionPeriod::factory()->create();
    $payload = periodPayload([['type' => 'submit', 'date' => '2030-01-01']]);

    $this->actingAs($researcher)->post(route('admin.submission-periods.store'), $payload)->assertForbidden();
    $this->actingAs($researcher)->put(route('admin.submission-periods.update', $period), $payload)->assertForbidden();
});

it('rejects a cycle on Batas Akhir (BR-FE-25, W139)', function () {
    $payload = periodPayload([
        ['type' => 'submit', 'date' => '2030-01-01'],
        ['type' => 'output', 'cycle' => 1, 'date' => '2030-06-01'],
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.submission-periods.store'), $payload)
        ->assertSessionHasErrors('submission_dates.1.cycle');
});

it('stores Batas Akhir without a cycle (BR-FE-25, W139)', function () {
    $payload = periodPayload([
        ['type' => 'submit', 'date' => '2030-01-01'],
        ['type' => 'output', 'date' => '2030-06-01'],
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.submission-periods.store'), $payload)
        ->assertSessionHasNoErrors();

    expect(SubmissionPeriod::firstOrFail()->submissionDates()->where('type', 'output')->value('cycle'))->toBeNull();
});
