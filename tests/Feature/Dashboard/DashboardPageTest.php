<?php

// tests/Feature/Dashboard/DashboardPageTest.php
//
// SHARED-02-01..04 — satu rute `/dashboard` untuk semua peran (§4.4 K4, W46).

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\Reviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Identity\PostLoginDestination;
use App\States\Submission\Submitted;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function dpUser(string ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

it('kontrak layar dasbor', function () {
    $this->actingAs(dpUser('submissions.view-own'))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/IndexPage')
            ->has('subtitle')
            ->has('sections', 1)
            ->has('sections.0.cards', 4)
            ->has('pending')
            ->has('activities')
            ->has('deadlines')
            ->has('announcements')
        );
});

it('terbuka bagi peneliti, reviewer, operator, dan admin', function (array $permissions, int $sections) {
    $user = dpUser(...$permissions);

    if (in_array('reviewers.evaluate', $permissions, true)) {
        Reviewer::factory()->create(['user_id' => $user->id, 'start_date' => now()->subMonth(), 'end_date' => now()->addMonth()]);
    }

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard/IndexPage')->has('sections', $sections));
})->with([
    'peneliti' => [['submissions.create', 'submissions.view-own'], 1],
    'reviewer' => [['reviewers.evaluate'], 1],
    'operator' => [['submissions.view-all', 'reviewers.assign', 'users.verify', 'reporting.export'], 1],
    'admin' => [['submissions.view-all', 'users.manage', 'schemes.manage'], 1],
]);

// BR-IAM-03 — banner "Lengkapi profil" (frame IAM-VERIFY-04) hanya bagi pembuat pengajuan.
it('profileCompletion menyebut kekurangan bagi pembuat pengajuan', function () {
    $user = dpUser('submissions.create', 'submissions.view-own');
    UserProfile::factory()->partiallyComplete()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('profileCompletion.missing', ['Bidang Keahlian', 'Foto Profil']));
});

it('profileCompletion null bila lengkap atau tanpa submissions.create', function () {
    $lengkap = dpUser('submissions.create', 'submissions.view-own');
    UserProfile::factory()->create(['user_id' => $lengkap->id]);
    $reviewer = dpUser('reviewers.evaluate');
    UserProfile::factory()->incomplete()->create(['user_id' => $reviewer->id]);

    $this->actingAs($lengkap)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('profileCompletion', null));
    $this->actingAs($reviewer)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('profileCompletion', null));
});

it('tamu diarahkan ke login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('dasbor tidak lagi mengirim biodataStatus', function () {
    // W61 — gerbang biodata Form-Engine dihapus; sisa data biodata lama tidak memunculkannya lagi.
    $type = FormType::forceCreate(['id' => FormType::BIODATA_ID, 'name' => 'Biodata']);
    $form = Form::factory()->create(['form_type_id' => $type->id, 'is_active' => true]);
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Mahasiswa', 'web'));
    FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
        'is_submitted' => true,
        'status' => Submitted::class,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/IndexPage')
            ->missing('biodataStatus')
        );
});

it('rute dasbor lama tidak lagi merender halaman', function () {
    expect(Route::has('reviewer.submissions.show'))->toBeFalse();
});

it('tujuan setelah login', function () {
    $pending = User::factory()->create();
    UserProfile::factory()->pending()->create(['user_id' => $pending->id]);
    $active = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $active->id]);

    expect(PostLoginDestination::run(dpUser('users.manage')))->toBe('dashboard')
        ->and(PostLoginDestination::run($pending->refresh()))->toBe('verification.status')
        ->and(PostLoginDestination::run($active->refresh()))->toBe('dashboard');
});
