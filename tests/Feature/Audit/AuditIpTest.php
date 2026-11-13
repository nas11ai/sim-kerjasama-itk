<?php

// tests/Feature/Audit/AuditIpTest.php
//
// W155 — DDD Reporting: Log Audit mencatat "dari IP mana". IP diambil di middleware web dan
// dibawa Context tersembunyi, jadi job yang di-dispatch dari request ikut mencatat IP peminta.

use App\Actions\FormSubmission\StartSubmission;
use App\Models\AuditEntry;
use App\Models\FormSubmission;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;

it('[RPT-AUDIT-08] entri audit mencatat alamat IP pelaku', function () {
    $fx = wizardFixture();
    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $this->actingAs($fx['user'])
        ->withServerVariables(['REMOTE_ADDR' => '10.10.2.14'])
        ->post(route('user.submissions.submit', $submission))
        ->assertSessionHasNoErrors();

    $entry = AuditEntry::query()->auditOnly()->where('description', 'Pengajuan disubmit')->latest('id')->firstOrFail();

    expect($entry->ip_address)->toBe('10.10.2.14');
});

it('IP peminta terbawa ke queued job', function () {
    // Dengan `sync` (bawaan phpunit.xml) Context tak pernah melewati payload, jadi test ini
    // memakai antrean database dan worker sungguhan.
    config(['queue.default' => 'database']);
    $operator = verifiedResearcher();
    $submission = FormSubmission::factory()->create(['submitted_by' => $operator->id]);

    Route::middleware('web')->post('/_test/audit-job', function () use ($submission, $operator) {
        dispatch(fn () => AuditTrail::record('Ekspor dibuat', $submission, $operator));

        return response()->noContent();
    });

    $this->actingAs($operator)
        ->withServerVariables(['REMOTE_ADDR' => '10.10.2.14'])
        ->post('/_test/audit-job')
        ->assertNoContent();

    expect(AuditEntry::query()->auditOnly()->count())->toBe(0);

    Context::flush();
    // RT1 — request ini juga mengantrekan session.active, jadi antrean dikuras, bukan `--once`.
    // `--memory` dinaikkan: di suite penuh proses test melewati 128 MB bawaan, dan worker
    // berhenti sesudah job pertama.
    Artisan::call('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--memory' => 4096]);

    expect(AuditEntry::query()->auditOnly()->sole()->ip_address)->toBe('10.10.2.14');
});

it('entri tanpa request tanpa IP', function () {
    $owner = verifiedResearcher();

    $entry = AuditTrail::record('Pengajuan disubmit', FormSubmission::factory()->create(['submitted_by' => $owner->id]), $owner);

    expect(AuditEntry::query()->findOrFail($entry->getKey())->ip_address)->toBeNull();
});

it('IP tidak muncul di log aplikasi', function () {
    Route::middleware('web')->get('/_test/audit-context', fn () => response()->json([
        'visible' => Context::get(AuditTrail::IP_CONTEXT),
        'hidden' => Context::getHidden(AuditTrail::IP_CONTEXT),
    ]));

    $this->withServerVariables(['REMOTE_ADDR' => '10.10.2.14'])
        ->get('/_test/audit-context')
        ->assertExactJson(['visible' => null, 'hidden' => '10.10.2.14']);
});
