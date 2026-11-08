<?php

// tests/Feature/Audit/AuditLogListTest.php
//
// OPERATOR-07-03 — daftar audit terfilter (W155): subjek, pelaku, IP, dan filter frame
// (cari, Waktu, Tipe Aksi, Pelaku). Cakupan tetap dari AuditScope (BR-RPT-05).

use App\Models\BudgetComponent;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\User;
use App\States\Submission\Approved;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditScope;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function auditListOperator(string $name = 'Siti Aminah'): User
{
    Permission::findOrCreate(AuditScope::READ_ALL_PERMISSION, 'web');
    $operator = User::factory()->create(['name' => $name]);
    $operator->givePermissionTo(AuditScope::READ_ALL_PERMISSION);

    return $operator;
}

function auditListSubmission(User $owner, string $title = 'Biochar Kaltim'): FormSubmission
{
    return FormSubmission::factory()->create([
        'form_id' => Form::factory()->create(['title' => $title])->id,
        'submitted_by' => $owner->id,
        'status' => Approved::class,
        'is_submitted' => true,
    ]);
}

/** @return array<string, mixed> props halaman */
function auditListProps(User $viewer, array $query = []): array
{
    $props = [];

    test()->actingAs($viewer)
        ->get(route('reporting.audit.index', $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$props) {
            $page->component('Reporting/AuditPage');
            $props = $page->toArray()['props'];
        });

    return $props;
}

it('filter waktu bawaan tujuh hari', function () {
    $budi = verifiedResearcher();
    $submission = auditListSubmission($budi);
    $this->travelTo(now()->subDays(10));
    AuditTrail::record('Pengajuan disubmit', $submission, $budi);
    $this->travelBack();
    $this->travelTo(now()->subDays(3));
    AuditTrail::record('Pengajuan ditarik', $submission, $budi);
    $this->travelBack();
    $operator = auditListOperator();

    expect(auditListProps($operator)['entries']['data'])->toHaveCount(1)
        ->and(auditListProps($operator)['filters']['range'])->toBe('7d')
        ->and(auditListProps($operator, ['range' => 'all'])['entries']['data'])->toHaveCount(2)
        // Nilai di luar pilihan kembali ke bawaan, bukan error atau "semua".
        ->and(auditListProps($operator, ['range' => '5y'])['entries']['data'])->toHaveCount(1);
});

it('pencarian mencocokkan subjek atau nama pelaku tanpa peka huruf', function () {
    $budi = verifiedResearcher();
    $siti = auditListOperator();
    AuditTrail::record('Pengajuan disubmit', auditListSubmission($budi, 'Biochar Kaltim'), $budi);
    AuditTrail::record('Skema disimpan', Scheme::factory()->create(['name' => 'DIPA']), $siti);

    expect(array_column(auditListProps($siti, ['search' => 'biochar'])['entries']['data'], 'action'))->toBe(['Pengajuan disubmit'])
        ->and(array_column(auditListProps($siti, ['search' => 'siti'])['entries']['data'], 'action'))->toBe(['Skema disimpan']);
});

it('filter pelaku Sistem memuat entri tanpa aktor', function () {
    $budi = verifiedResearcher();
    $submission = auditListSubmission($budi);
    AuditTrail::record('Pengajuan disubmit', $submission, $budi);
    AuditTrail::record('Berkas ekspor dihapus', $submission);

    $rows = auditListProps(auditListOperator(), ['actor' => 'system'])['entries']['data'];

    expect(array_column($rows, 'action'))->toBe(['Berkas ekspor dihapus'])
        ->and($rows[0]['actor'])->toBe('Sistem');
});

it('filter pelaku per peran DDD dan tipe aksi', function () {
    $budi = seededRole('researcher');
    $siti = seededRole('operator');
    $siti->givePermissionTo(AuditScope::READ_ALL_PERMISSION);
    $submission = auditListSubmission($budi);
    AuditTrail::record('Pengajuan disubmit', $submission, $budi);
    AuditTrail::record('Pengajuan ditarik', $submission, $siti);

    expect(array_column(auditListProps($siti, ['actor' => 'operator'])['entries']['data'], 'action'))->toBe(['Pengajuan ditarik'])
        ->and(array_column(auditListProps($siti, ['action' => 'Pengajuan disubmit'])['entries']['data'], 'action'))->toBe(['Pengajuan disubmit']);
});

it('label subjek dan pelaku', function () {
    $budi = seededRole('researcher');
    $budi->update(['name' => 'Dr. Budi Santoso']);
    $agus = seededRole('operator');
    $agus->update(['name' => 'Agus Prasetyo']);
    $agus->assignRole('admin');
    $agus->givePermissionTo(AuditScope::READ_ALL_PERMISSION);
    $submission = auditListSubmission($budi, 'Biochar Kaltim');
    AuditTrail::record('Pengajuan disubmit', $submission, $budi);
    AuditTrail::record('Skema disimpan', Scheme::factory()->create(['name' => 'DIPA']), $agus);

    $rows = auditListProps($agus)['entries']['data'];

    expect($rows[1]['subject'])->toBe('Submission — Biochar Kaltim')
        ->and($rows[1]['actor'])->toBe('Dr. Budi Santoso (Peneliti)')
        ->and($rows[0]['subject'])->toBe('Skema — DIPA')
        // Urutan RoleCatalog, bukan urutan penetapan peran.
        ->and($rows[0]['actor'])->toBe('Agus Prasetyo (Operator, Admin)');
});

it('label subjek terhapus dan tipe tak dikenal', function () {
    $operator = auditListOperator();
    $scheme = Scheme::factory()->create(['name' => 'DIPA']);
    $component = BudgetComponent::factory()->create();
    AuditTrail::record('Skema disimpan', $scheme, $operator);
    AuditTrail::record('Komponen anggaran ditambahkan', $component, $operator);
    $schemeId = $scheme->id;
    $scheme->delete();

    $subjects = array_column(auditListProps($operator)['entries']['data'], 'subject');

    expect($subjects)->toBe(["BudgetComponent #{$component->id}", "Skema #{$schemeId}"]);
});

it('[RPT-AUDIT-05] pilihan tipe aksi peneliti tidak membocorkan aksi di luar scope', function () {
    $budi = verifiedResearcher();
    $sari = verifiedResearcher();
    AuditTrail::record('Pengajuan disubmit', auditListSubmission($budi), $budi);
    AuditTrail::record('Pengajuan ditarik', auditListSubmission($sari), $sari);

    $props = auditListProps($budi);

    expect(array_column($props['actionOptions'], 'value'))->toBe(['Pengajuan disubmit'])
        ->and($props['seesEverything'])->toBeFalse()
        ->and(array_column($props['actorOptions'], 'value'))
        ->toBe(['researcher', 'reviewer_internal', 'reviewer_external', 'operator', 'admin', 'system']);
});

it('[BUD-ITEM-06] baris audit membawa nilai lama dan baru', function () {
    $budi = verifiedResearcher();
    AuditTrail::record('Line item anggaran diubah', auditListSubmission($budi), $budi, new AuditChange(
        old: ['total' => 4_000_000],
        new: ['total' => 6_500_000, 'detail' => ['volume' => 2]],
    ));

    $row = auditListProps(auditListOperator())['entries']['data'][0];

    expect($row['old'])->toBe(['total' => 4_000_000])
        // Nilai bersarang dikirim sebagai teks; layar tidak menebak bentuknya.
        ->and($row['new'])->toBe(['total' => 6_500_000, 'detail' => '{"volume":2}']);
});

it('peneliti tidak menerima alamat IP', function () {
    $budi = verifiedResearcher();
    Context::addHidden(AuditTrail::IP_CONTEXT, '10.10.2.14');
    AuditTrail::record('Pengajuan disubmit', auditListSubmission($budi), $budi);
    Context::forgetHidden(AuditTrail::IP_CONTEXT);

    expect(auditListProps($budi)['entries']['data'][0]['ipAddress'])->toBeNull()
        ->and(auditListProps(auditListOperator())['entries']['data'][0]['ipAddress'])->toBe('10.10.2.14');
});

it('rute audit lama sudah tidak terdaftar', function () {
    expect(Route::has('audit-log.index'))->toBeFalse();
});
