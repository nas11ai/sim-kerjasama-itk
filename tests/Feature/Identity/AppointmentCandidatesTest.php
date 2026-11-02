<?php

// BR-IAM-11 — kandidat penunjukan reviewer: user terdaftar yang aktif (BR-IAM-05).

use App\Models\Reviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Identity\AppointmentCandidates;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

function candidatePerson(string $name, bool $active = true, ?string $nidn = null): User
{
    $user = User::factory()->create(['name' => $name, 'is_active' => $active]);
    UserProfile::factory()->create(['user_id' => $user->id, 'nidn' => $nidn]);

    return $user;
}

it('kandidat dicari case-insensitive', function () {
    $rina = candidatePerson('Dr. Rina Wijaya', nidn: '0987654321');
    candidatePerson('Ir. Joko Susilo');

    $byName = AppointmentCandidates::run('RINA');
    $byNidn = AppointmentCandidates::run('09876');

    expect(array_map(fn ($c) => $c->userId, $byName))->toBe([$rina->id])
        ->and(array_map(fn ($c) => $c->userId, $byNidn))->toBe([$rina->id])
        ->and($byName[0]->currentReviewer)->toBeNull();
});

it('akun nonaktif tidak ditawarkan', function () {
    candidatePerson('Dr. Rina Wijaya', active: false);

    expect(AppointmentCandidates::run('rina'))->toBe([]);
});

it('kandidat yang sudah reviewer membawa currentReviewer', function () {
    $rina = candidatePerson('Dr. Rina Wijaya');
    Reviewer::factory()->create([
        'user_id' => $rina->id,
        'reviewer_type' => 'internal',
        'start_date' => '2025-09-01',
        'end_date' => '2099-08-31',
    ]);

    $candidate = AppointmentCandidates::run('rina')[0];

    expect($candidate->currentReviewer)->toBe([
        'type' => ['value' => 'internal', 'label' => 'Internal'],
        'tenureStart' => '2025-09-01',
        'tenureEnd' => '2099-08-31',
        'standing' => ['value' => 'active', 'label' => 'Aktif'],
    ]);
});

it('pencarian kurang dari 2 karakter kosong', function () {
    candidatePerson('Dr. Rina Wijaya');

    expect(AppointmentCandidates::run('r'))->toBe([])
        ->and(AppointmentCandidates::run('  '))->toBe([]);
});

it('kandidat dikirim sebagai prop opsional lewat candidate_search', function () {
    Permission::findOrCreate('reviewers.assign');
    $operator = User::factory()->create();
    $operator->givePermissionTo('reviewers.assign');
    candidatePerson('Dr. Rina Wijaya');

    $this->actingAs($operator)
        ->get(route('admin.reviewers.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('appointmentCandidates')
            ->reloadOnly('appointmentCandidates', fn (AssertableInertia $reload) => $reload
                ->has('appointmentCandidates', 0)));

    $this->actingAs($operator)
        ->get(route('admin.reviewers.index', ['candidate_search' => 'rina']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->reloadOnly('appointmentCandidates', fn (AssertableInertia $reload) => $reload
                ->has('appointmentCandidates', 1)
                ->where('appointmentCandidates.0.name', 'Dr. Rina Wijaya')));
});
