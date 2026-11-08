<?php

// tests/Feature/Export/ExportPageTest.php
//
// OPERATOR-07-02 — layar Export Data. Satu layar untuk Operator dan peneliti (BR-RPT-10):
// yang membedakan isi berkasnya adalah SubmissionVisibility, bukan pintunya.

use App\Actions\Export\RequestExport;
use App\Data\Reporting\ExportRequestData;
use App\Jobs\GenerateExport;
use App\Models\User;
use App\Support\Reporting\ExportRetention;
use App\Support\Reporting\ExportThreshold;
use App\Support\Reporting\ExportType;
use App\Support\Reporting\ExportWriter;
use App\Support\Reporting\SubmissionVisibility;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

function exportPageOperator(): User
{
    Permission::findOrCreate(SubmissionVisibility::READ_ALL_PERMISSION, 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo(SubmissionVisibility::READ_ALL_PERMISSION);

    return $operator;
}

it('Operator membuka layar Export Data dengan kelima jenis', function () {
    $this->actingAs(exportPageOperator())
        ->get(route('reporting.exports.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Reporting/ExportPage')
            ->has('types', 5)
            ->where('types.0', ['value' => ExportType::SUBMISSIONS, 'label' => 'Rekap Submission', 'filterKeys' => ['year', 'scheme', 'status']])
            ->where('types.4', ['value' => ExportType::AUDIT_LOG, 'label' => 'Audit Log', 'filterKeys' => ['year']])
            ->where('formats', [['value' => 'xlsx', 'label' => 'Excel'], ['value' => 'csv', 'label' => 'CSV']])
            ->where('threshold', ExportThreshold::SYNCHRONOUS_ROWS)
            ->where('retentionHours', ExportRetention::HOURS)
            ->where('maxAttempts', (new GenerateExport(0))->tries)
            ->where('statusOptions', fn ($options) => !collect($options)->contains('value', 'draft'))
            ->where('yearOptions.0.value', (string) now()->year)
            ->has('schemeOptions')
            ->where('initialType', null)
            ->has('jobs', 0));
});

it('peneliti membuka layar yang sama (BR-RPT-10)', function () {
    $this->actingAs(verifiedResearcher())
        ->get(route('reporting.exports.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Reporting/ExportPage')->has('types', 5));
});

it('jenis awal dari query type', function (string $type, ?string $initial) {
    $this->actingAs(exportPageOperator())
        ->get(route('reporting.exports.index', ['type' => $type]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('initialType', $initial));
})->with([
    'audit-log' => ['audit-log', 'audit-log'],
    'tak dikenal' => ['foo', null],
]);

it('baris riwayat membawa batas masa simpan berkas', function () {
    Storage::fake(ExportWriter::DISK);
    $operator = exportPageOperator();
    $this->travelTo('2026-07-02 09:00:00');
    RequestExport::run($operator, new ExportRequestData(ExportType::AUDIT_LOG));

    $this->actingAs($operator)
        ->get(route('reporting.exports.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('jobs.0.file_expires_at', now()->addHours(ExportRetention::HOURS)->toIso8601String()));
});
