<?php

// tests/Feature/Audit/AuditTrailTest.php
//
// BR-RPT-04, BR-RPT-05, BR-RPT-09 — jejak audit.
//
// Append-only punya dua cara dilanggar dan keduanya ditutup terpisah:
// implementasi yang hanya melarang DELETE tetap membiarkan UPDATE menulis
// ulang sejarah — dan itu pelanggaran yang lebih berbahaya, karena hasilnya
// tetap tampak sah.

use App\Actions\Budget\SaveBudgetLineItem;
use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\AuditEntry;
use App\Models\BudgetComponent;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\Support\Audit\AppendOnlyViolation;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditScope;
use App\Support\Audit\AuditTrail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function auditOperator(): User
{
    Permission::findOrCreate(AuditScope::READ_ALL_PERMISSION, 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo(AuditScope::READ_ALL_PERMISSION);

    return $operator;
}

function auditedSubmission(User $owner, string $status = Approved::class): FormSubmission
{
    return FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => $status,
        'is_submitted' => true,
    ]);
}

/** Draft siap submit di atas wizardFixture(). */
function auditableDraft(array $fx): FormSubmission
{
    return FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);
}

it('[RPT-AUDIT-01] aksi yang mengubah state menambah entri audit', function () {
    $fx = wizardFixture();
    $draft = auditableDraft($fx);

    expect(AuditEntry::query()->auditOnly()->count())->toBe(0);

    SubmitFormSubmission::run($draft, $fx['user']);

    $entry = AuditEntry::query()->auditOnly()->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->description)->toBe('Pengajuan disubmit')
        ->and($entry->subject_type)->toBe(FormSubmission::class)
        ->and($entry->subject_id)->toBe($draft->id);
});

it('[RPT-AUDIT-02] entri audit tidak bisa dihapus', function () {
    $owner = verifiedResearcher();
    $submission = auditedSubmission($owner);

    $entry = AuditTrail::record('Pengajuan disubmit', $submission, $owner);

    expect(fn () => $entry->delete())->toThrow(AppendOnlyViolation::class);
    expect(AuditEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
});

it('[RPT-AUDIT-03] entri audit tidak bisa disunting', function () {
    $owner = verifiedResearcher();
    $submission = auditedSubmission($owner);

    $entry = AuditTrail::record('Pengajuan disubmit', $submission, $owner);

    expect(function () use ($entry) {
        $entry->description = 'Sesuatu yang lain';
        $entry->save();
    })->toThrow(AppendOnlyViolation::class);

    expect(AuditEntry::query()->whereKey($entry->id)->value('description'))
        ->toBe('Pengajuan disubmit');
});

it('[RPT-AUDIT-04] Operator melihat audit log seluruh sistem', function () {
    $budi = verifiedResearcher();
    $sari = verifiedResearcher();

    $p001 = auditedSubmission($budi);
    $p007 = auditedSubmission($sari);

    AuditTrail::record('Pengajuan disubmit', $p001, $budi);
    AuditTrail::record('Pengajuan disubmit', $p007, $sari);

    $terbaca = AuditScope::forUser(auditOperator())->pluck('subject_id')->all();

    expect($terbaca)->toContain($p001->id)
        ->and($terbaca)->toContain($p007->id);
});

it('[RPT-AUDIT-05] Researcher hanya melihat audit submission miliknya', function () {
    $budi = verifiedResearcher();
    $sari = verifiedResearcher();

    $p001 = auditedSubmission($budi);
    $p007 = auditedSubmission($sari);

    AuditTrail::record('Pengajuan disubmit', $p001, $budi);
    AuditTrail::record('Pengajuan disubmit', $p007, $sari);

    $terbaca = AuditScope::forUser($budi)->pluck('subject_id')->all();

    expect($terbaca)->toContain($p001->id)
        ->and($terbaca)->not->toContain($p007->id);
});

it('[RPT-AUDIT-05] anggota tim ikut membaca jejak pengajuan yang diikutinya', function () {
    // BR-SM-07 — anggota berhak membaca pengajuannya, jadi menyempitkan audit
    // ke `submitted_by` akan menyembunyikan riwayat dari orang yang ikut
    // membuatnya.
    $budi = verifiedResearcher();
    $andi = verifiedResearcher();

    $p001 = auditedSubmission($budi);

    $profile = UserProfile::query()->where('user_id', $andi->id)->firstOrFail();
    ResearchMember::create([
        'form_submission_id' => $p001->id,
        'user_profile_id' => $profile->id,
        'role' => 'member',
        'status' => 'active',
    ]);

    AuditTrail::record('Pengajuan disubmit', $p001, $budi);

    expect(AuditScope::forUser($andi)->pluck('subject_id')->all())->toContain($p001->id);
});

it('[RPT-AUDIT-06] entri audit memuat aktor, waktu, dan nilai sebelum-sesudah', function () {
    $fx = wizardFixture();
    $owner = $fx['user'];
    $draft = auditableDraft($fx);
    $component = BudgetComponent::factory()->create(['is_active' => true]);

    SaveBudgetLineItem::run($draft, $owner, [
        'budget_component_id' => $component->id,
        'item_name' => 'Sewa peralatan',
        'volume' => 2,
        'unit' => 'unit',
        'unit_price' => 3_250_000,
    ]);

    $entry = AuditEntry::query()->auditOnly()->latest('id')->firstOrFail();

    expect($entry->causer_id)->toBe($owner->id)
        ->and($entry->created_at)->not->toBeNull()
        ->and($entry->properties['new']['total'])->toBe(6_500_000)
        ->and($entry->properties['new']['item_name'])->toBe('Sewa peralatan');
});

it('membuka layar audit bagi Operator dengan cakupan penuh', function () {
    $budi = verifiedResearcher();
    AuditTrail::record('Pengajuan disubmit', auditedSubmission($budi), $budi);

    $this->actingAs(auditOperator())
        ->get(route('reporting.audit.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reporting/AuditPage')
            ->where('seesEverything', true)
            ->has('entries.data', 1)
        );
});

it('membuka layar audit bagi peneliti dengan cakupan miliknya', function () {
    $budi = verifiedResearcher();
    $sari = verifiedResearcher();

    AuditTrail::record('Pengajuan disubmit', auditedSubmission($budi), $budi);
    AuditTrail::record('Pengajuan disubmit', auditedSubmission($sari), $sari);

    $this->actingAs($budi)
        ->get(route('reporting.audit.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seesEverything', false)
            ->has('entries.data', 1)
        );
});

it('mencatat nilai lama dan baru lewat AuditChange', function () {
    $budi = User::factory()->create();

    $entry = AuditTrail::record('Skema dipilih', auditedSubmission($budi), $budi, new AuditChange(
        old: ['scheme_id' => 1],
        new: ['scheme_id' => 2],
    ));

    expect($entry->properties['old'])->toBe(['scheme_id' => 1])
        ->and($entry->properties['new'])->toBe(['scheme_id' => 2]);
});
