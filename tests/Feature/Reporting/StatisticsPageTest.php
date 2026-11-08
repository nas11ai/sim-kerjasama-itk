<?php

// OPERATOR-07-01 — kerangka Dashboard Statistik: siapa membuka (W148), tab = route (W156),
// dan filter tahun submit pertama (W149).

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function statsUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('Operator membuka statistik', function () {
    $this->actingAs(statsUser('operator'))
        ->get(route('reporting.stats'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reporting/StatsPage')
            ->where('tab', 'overview')
            ->has('tabs', 4)
            ->where('tabs.0.label', 'Ringkasan')
            ->where('tabs.3.href', route('reporting.stats', ['tab' => 'follow-up']))
            ->where('filters.year', now()->year)
            ->where('filterKeys', ['year', 'period', 'scheme'])
            ->where('can.viewSubmissions', true)
            ->has('yearOptions')
            ->has('periodOptions')
            ->has('schemeOptions'));
});

it('peneliti dan reviewer tidak membuka statistik', function (string $role) {
    $this->actingAs(statsUser($role))
        ->get(route('reporting.stats'))
        ->assertForbidden();
})->with(['researcher', 'reviewer_internal']);

it('pemegang reporting.view-audit-log saja membuka statistik tanpa daftar pengajuan', function () {
    $auditor = User::factory()->create();
    $auditor->givePermissionTo('reporting.view-audit-log');

    $this->actingAs($auditor)
        ->get(route('reporting.stats'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.viewSubmissions', false));
});

it('tab tak dikenal 404', function () {
    $this->actingAs(statsUser('operator'))
        ->get(route('reporting.stats', ['tab' => 'foo']))
        ->assertNotFound();
});

it('tahun all berarti tanpa filter tahun', function () {
    $this->actingAs(statsUser('operator'))
        ->get(route('reporting.stats', ['year' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('filters.year', null));
});

it('tab Saat Ini tidak menawarkan filter tahun', function () {
    $this->actingAs(statsUser('operator'))
        ->get(route('reporting.stats', ['tab' => 'now']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'now')
            ->where('filterKeys', ['period', 'scheme']));
});

it('opsi tahun = tahun submit pertama ditambah tahun berjalan dan semua tahun', function () {
    $submission = FormSubmission::factory()->create(['is_submitted' => true]);
    $this->travelTo('2024-05-02 08:00:00');
    AuditTrail::record(SubmitFormSubmission::AUDIT_DESCRIPTION, $submission, $submission->submittedBy);
    $this->travelBack();

    $this->actingAs(statsUser('operator'))
        ->get(route('reporting.stats'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('yearOptions', [
                ['value' => 'all', 'label' => 'Semua tahun'],
                ['value' => (string) now()->year, 'label' => (string) now()->year],
                ['value' => '2024', 'label' => '2024'],
            ]));
});
