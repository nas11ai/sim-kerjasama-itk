<?php

// tests/Feature/FormEngine/PhaseOverrideTest.php
//
// BR-FE-08/09/10 dan BR-SCH-07 — perpanjangan akses satu fase.
//
// FormSubmissionOverride sudah punya model, migrasi, dan unit test sejak lama,
// tapi **nol pemanggil produksi**: tidak satu pun jalur aplikasi pernah
// membacanya. Aturan yang benar dan teruji tapi tidak tersambung tidak
// melindungi siapa pun. Berkas ini menguji jalur nyatanya.

use App\Actions\Override\GrantPhaseOverride;
use App\Actions\Override\RevokePhaseOverride;
use App\Data\Request\PhaseOverrideInput;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\FormSubmissionOverride;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Notifications\PhaseOverrideGrantedNotification;
use App\States\Submission\Draft;
use App\Support\PhaseAccessGate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

function overrideDetail(SubmissionPeriod $period, FormPhase $phase, string $label, string $when, int $order = 1): FormPhaseDetail
{
    $kind = deadlineTypeOf($label);
    SubmissionDate::create([...$kind, 'submission_period_id' => $period->id, 'datetime' => $when]);

    return FormPhaseDetail::factory()->forPeriod($period)->create([
        'form_phase_id' => $phase->id,
        'deadline_type' => $kind['type'],
        'deadline_cycle' => $kind['cycle'],
        'order' => $order,
    ]);
}

/**
 * @return array{period: SubmissionPeriod, phase: FormPhase, detail: FormPhaseDetail, submission: FormSubmission, owner: User, operator: User}
 */
function overrideFixture(string $deadline = '2026-03-01 23:59:00'): array
{
    $period = SubmissionPeriod::factory()->create(['is_force_closed' => false]);
    $phase = FormPhase::create(['title' => 'Tahap Override '.fake()->unique()->numerify('####'), 'is_active' => true]);
    $detail = overrideDetail($period, $phase, 'Batas Pengiriman', $deadline);

    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'form_phase_detail_id' => $detail->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);

    Permission::findOrCreate('submissions.view-all', 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo('submissions.view-all');

    return compact('period', 'phase', 'detail', 'submission', 'owner', 'operator');
}

it('[FE-OVERRIDE-01] akses terbuka sebelum batas fase', function () {
    $this->travelTo('2026-02-20 10:00:00');
    $fx = overrideFixture();

    expect(PhaseAccessGate::isOpenFor($fx['submission'], $fx['detail']))->toBeTrue();
});

it('[FE-OVERRIDE-02] akses ditutup setelah batas tanpa override', function () {
    $this->travelTo('2026-03-02 00:01:00');
    $fx = overrideFixture();

    expect(PhaseAccessGate::isOpenFor($fx['submission'], $fx['detail']))->toBeFalse();

    // Penolakannya menyebut jalan keluarnya — peneliti yang cuma dibilang
    // "403" tidak tahu bahwa perpanjangan memang bisa diminta.
    try {
        PhaseAccessGate::assertOpenFor($fx['submission'], $fx['detail']);
        $this->fail('seharusnya ditolak');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403)
            ->and($e->getMessage())->toContain('Operator');
    }
});

it('[FE-OVERRIDE-03] override aktif membuka kembali akses', function () {
    $this->travelTo('2026-03-02 00:01:00');
    $fx = overrideFixture();

    GrantPhaseOverride::run($fx['submission'], $fx['detail'], $fx['operator'],
        new PhaseOverrideInput('Kendala jaringan kampus', '2026-03-10 23:59:00'));

    expect(PhaseAccessGate::isOpenFor($fx['submission'], $fx['detail']))->toBeTrue();
});

it('[FE-OVERRIDE-04] override yang kedaluwarsa tidak membuka akses', function () {
    $this->travelTo('2026-03-02 00:01:00');
    $fx = overrideFixture();

    GrantPhaseOverride::run($fx['submission'], $fx['detail'], $fx['operator'],
        new PhaseOverrideInput('Kendala jaringan', '2026-03-05 23:59:00'));

    $this->travelTo('2026-03-06 08:00:00');

    expect(PhaseAccessGate::isOpenFor($fx['submission'], $fx['detail']))->toBeFalse();
});

it('[FE-OVERRIDE-05] override yang dicabut tidak membuka akses', function () {
    $this->travelTo('2026-03-02 00:01:00');
    $fx = overrideFixture();

    $override = GrantPhaseOverride::run($fx['submission'], $fx['detail'], $fx['operator'],
        new PhaseOverrideInput('Kendala jaringan', '2026-03-10 23:59:00'));
    RevokePhaseOverride::run($override);

    expect(PhaseAccessGate::isOpenFor($fx['submission'], $fx['detail']))->toBeFalse();
    // Dicabut, bukan dihapus — pengecualian yang pernah diberikan tetap
    // terbaca sebagai riwayat keputusan.
    expect(FormSubmissionOverride::find($override->id))->not->toBeNull();
});

it('[FE-OVERRIDE-06] override tanpa alasan ditolak', function () {
    $fx = overrideFixture();

    expect(fn () => GrantPhaseOverride::run($fx['submission'], $fx['detail'], $fx['operator'],
        new PhaseOverrideInput('   ')))
        ->toThrow(ValidationException::class)
        ->and(FormSubmissionOverride::count())->toBe(0);
});

it('[FE-OVERRIDE-06] jalur HTTP menolak alasan kosong pada field reason', function () {
    $fx = overrideFixture();

    $this->actingAs($fx['operator'])
        ->post(route('admin.submissions.overrides.store', $fx['submission']), [
            'form_phase_detail_id' => $fx['detail']->id,
            'reason' => '',
        ])
        ->assertSessionHasErrors('reason');

    expect(FormSubmissionOverride::count())->toBe(0);
});

it('[FE-OVERRIDE-07] override tidak melebar ke fase lain', function () {
    $this->travelTo('2026-06-02 08:00:00');
    $fx = overrideFixture();

    $monev = overrideDetail($fx['period'], $fx['phase'], 'Batas Laporan Monev I', '2026-06-01 23:59:00', 2);

    GrantPhaseOverride::run($fx['submission'], $fx['detail'], $fx['operator'],
        new PhaseOverrideInput('Kendala jaringan', '2026-07-01 23:59:00'));

    expect(PhaseAccessGate::isOpenFor($fx['submission'], $fx['detail']))->toBeTrue()
        ->and(PhaseAccessGate::isOpenFor($fx['submission'], $monev))->toBeFalse();
});

it('[FE-OVERRIDE-08] override tidak melebar ke pengajuan lain', function () {
    $this->travelTo('2026-03-02 00:01:00');
    $fx = overrideFixture();

    $lain = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);

    GrantPhaseOverride::run($fx['submission'], $fx['detail'], $fx['operator'],
        new PhaseOverrideInput('Kendala jaringan', '2026-03-10 23:59:00'));

    expect(PhaseAccessGate::isOpenFor($fx['submission'], $fx['detail']))->toBeTrue()
        ->and(PhaseAccessGate::isOpenFor($lain, $fx['detail']))->toBeFalse();
});

it('[FE-OVERRIDE-11] periode ditutup paksa menutup fase meski batas belum lewat', function () {
    $this->travelTo('2026-02-20 10:00:00');
    $fx = overrideFixture();

    $fx['period']->update(['is_force_closed' => true]);

    expect(PhaseAccessGate::isOpenFor($fx['submission'], $fx['detail']->fresh()))->toBeFalse();
});

it('[FE-OVERRIDE-11] penutupan paksa mengalahkan override yang masih berlaku', function () {
    $this->travelTo('2026-03-02 00:01:00');
    $fx = overrideFixture();

    GrantPhaseOverride::run($fx['submission'], $fx['detail'], $fx['operator'],
        new PhaseOverrideInput('Kendala jaringan', '2026-03-10 23:59:00'));
    $fx['period']->update(['is_force_closed' => true]);

    // Override adalah izin Operator untuk satu orang; force close adalah
    // keputusan Operator menghentikan semuanya. Yang lebih luas menang —
    // kalau tidak, menutup periode menyisakan pintu yang diam-diam terbuka.
    expect(PhaseAccessGate::isOpenFor($fx['submission'], $fx['detail']->fresh()))->toBeFalse();
});

it('memberi override baru mencabut yang lama supaya masa berlakunya tidak bertengkar', function () {
    $this->travelTo('2026-03-02 00:01:00');
    $fx = overrideFixture();

    $lama = GrantPhaseOverride::run($fx['submission'], $fx['detail'], $fx['operator'],
        new PhaseOverrideInput('Alasan pertama', '2026-03-05 23:59:00'));
    $baru = GrantPhaseOverride::run($fx['submission'], $fx['detail'], $fx['operator'],
        new PhaseOverrideInput('Alasan kedua', '2026-03-20 23:59:00'));

    expect($lama->fresh()->is_active)->toBeFalse()
        ->and($baru->is_active)->toBeTrue()
        ->and(PhaseAccessGate::activeOverride($fx['submission'], $fx['detail'])->id)->toBe($baru->id);
});

// BR-FE-15 dan BR-FE-16 — sisi layar. Keduanya diuji lewat props Inertia
// karena di situlah keputusannya diambil: kalau `reason` sampai ke payload,
// tidak ada CSS yang bisa menyembunyikannya dengan aman.

it('[FE-OVERRIDE-09] peneliti melihat status override tapi tidak alasannya', function () {
    $this->travelTo('2026-03-02 00:01:00');
    $fx = overrideFixture();

    GrantPhaseOverride::run(
        $fx['submission'],
        $fx['detail'],
        $fx['operator'],
        new PhaseOverrideInput('Kendala jaringan kampus', '2026-03-10 23:59:00'),
    );

    $this->actingAs($fx['owner'])
        ->get(route('user.submissions.show', $fx['submission']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Submission/ShowPage')
            ->where('phaseAccess.is_open', true)
            ->where('phaseAccess.override.status', 'active')
            ->where('phaseAccess.override.reason', null)
            ->etc());
});

it('[FE-OVERRIDE-09] Operator tetap membaca alasan override', function () {
    $this->travelTo('2026-03-02 00:01:00');
    $fx = overrideFixture();

    GrantPhaseOverride::run($fx['submission'], $fx['detail'], $fx['operator'],
        new PhaseOverrideInput('Kendala jaringan kampus', '2026-03-10 23:59:00'));

    $this->actingAs($fx['operator'])
        ->get(route('user.submissions.show', $fx['submission']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('phaseAccess.override.reason', 'Kendala jaringan kampus')
            ->etc());
});

it('[FE-OVERRIDE-10] fase yang lewat batas tetap terbaca tapi terkunci', function () {
    $this->travelTo('2026-03-02 00:01:00');
    $fx = overrideFixture();

    $this->actingAs($fx['owner'])
        ->get(route('user.submissions.show', $fx['submission']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Submission/ShowPage')
            // Terbaca: pengajuannya tetap dikirim ke layar, tidak 404 dan
            // tidak dikosongkan.
            ->where('submission.id', $fx['submission']->id)
            // Terkunci: itulah yang membuatnya read-only, bukan hilang.
            ->where('phaseAccess.is_open', false)
            ->where('phaseAccess.override', null)
            ->etc());
});

// ---------------------------------------------------------------------------
// BR-FE-08/16 — pemberitahuan perpanjangan. Notifikasinya sudah ada sejak
// override dibuat tapi tidak punya test sama sekali: perpanjangan yang tidak
// diberitahukan sama saja dengan tidak diberikan, dan diamnya tidak akan
// ketahuan dari layar mana pun.

it('memberi tahu peneliti saat aksesnya dibuka kembali', function () {
    Notification::fake();

    $this->travelTo('2026-03-02 00:01:00');
    $fx = overrideFixture();

    GrantPhaseOverride::run(
        $fx['submission'],
        $fx['detail'],
        $fx['operator'],
        new PhaseOverrideInput('Kendala jaringan kampus', '2026-03-10 23:59:00'),
    );

    Notification::assertSentTo(
        $fx['owner'],
        PhaseOverrideGrantedNotification::class,
        function (PhaseOverrideGrantedNotification $notification) use ($fx) {
            $payload = $notification->toArray($fx['owner']);

            // BR-FE-16 — peneliti membaca *sampai kapan*, bukan *kenapa*.
            return $payload['form_submission_id'] === $fx['submission']->id
                && $payload['expires_at'] === '2026-03-10 23:59:00'
                && !str_contains(json_encode($payload), 'Kendala jaringan kampus');
        }
    );
});

it('tidak memberi tahu siapa pun selain pemilik pengajuan', function () {
    Notification::fake();

    $this->travelTo('2026-03-02 00:01:00');
    $fx = overrideFixture();

    GrantPhaseOverride::run($fx['submission'], $fx['detail'], $fx['operator'],
        new PhaseOverrideInput('Alasan', '2026-03-10 23:59:00'));

    Notification::assertNotSentTo($fx['operator'], PhaseOverrideGrantedNotification::class);
});
