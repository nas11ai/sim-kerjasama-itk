<?php

// SHARED-01-06 Lengkapi Profil — BR-IAM-03. Gerbang yang mengikat tetap di StartSubmission
// (IAM-VERIFY-04/05/12 di NidnVerificationTest); layar ini jalur produksi yang mengisinya,
// dan wizard hanya mengantar ke sini lebih awal.

use App\Actions\FormSubmission\StartSubmission;
use App\Enums\VerificationStatus;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Draft;
use App\Support\Audit\AuditTrail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => Storage::fake('local'));

function incompleteOwner(string $status = 'active', array $profile = []): User
{
    $user = User::factory()->create();
    UserProfile::factory()->incomplete()->create(['user_id' => $user->id, 'verification_status' => $status, ...$profile]);

    return $user->fresh();
}

function completionPayload(array $overrides = []): array
{
    return ['phone' => '0813-9876-5432', 'expertise' => 'Data Science', 'photo' => UploadedFile::fake()->image('budi.png'), ...$overrides];
}

it('Lengkapi Profil merender kontrak bagi profil belum lengkap', function () {
    $this->actingAs(incompleteOwner())->get(route('profile.complete'))->assertInertia(fn ($page) => $page
        ->component('Profile/CompletePage')
        ->where('next', null)
        ->where('profile.missing', ['Nomor Telepon', 'Bidang Keahlian', 'Foto Profil']));
});

it('hanya next=submission yang diteruskan ke halaman', function () {
    $user = incompleteOwner();

    $this->actingAs($user)->get(route('profile.complete', ['next' => 'submission']))
        ->assertInertia(fn ($page) => $page->where('next', 'submission'));
    $this->actingAs($user)->get(route('profile.complete', ['next' => 'https://evil.example']))
        ->assertInertia(fn ($page) => $page->where('next', null));
});

it('profil lengkap atau tanpa profil dialihkan ke Profil Saya', function () {
    $lengkap = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $lengkap->id]);

    $this->actingAs($lengkap)->get(route('profile.complete'))->assertRedirect(route('profile.edit'));
    $this->actingAs(User::factory()->create())->get(route('profile.complete'))->assertRedirect(route('profile.edit'));
});

it('akun pending dan rejected tetap bisa melengkapi profil', function (string $status) {
    // BR-IAM-04 + IAM-VERIFY-06 — kelengkapan profil gerbang terpisah dari verifikasi.
    $user = incompleteOwner($status);

    $this->actingAs($user)->post(route('profile.complete.store'), completionPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect($user->userProfile->fresh()->isComplete())->toBeTrue();
})->with(['pending', 'rejected']);

it('melengkapi profil tidak mengubah status verifikasi', function () {
    $user = incompleteOwner('pending', ['nidn' => '0011223344']);
    $profile = $user->userProfile;
    $organizationId = $profile->organization_id;

    $this->actingAs($user)->post(route('profile.complete.store'), completionPayload([
        'nidn' => '9999999999', 'organization_id' => 999, 'verification_status' => 'active',
    ]))->assertSessionHasNoErrors();

    $fresh = $profile->fresh();

    expect($fresh->verification_status)->toBe(VerificationStatus::Pending)
        ->and($fresh->nidn)->toBe('0011223344')
        ->and($fresh->organization_id)->toBe($organizationId);
});

it('melengkapi profil diaudit dengan nilai lama dan baru', function () {
    $user = incompleteOwner();

    $this->actingAs($user)->post(route('profile.complete.store'), completionPayload());

    $entry = Activity::query()->where('log_name', AuditTrail::LOG_NAME)->where('description', 'Profil dilengkapi')->sole();

    expect($entry->properties->all())->toBe([
        'old' => ['phone' => null, 'expertise' => null],
        'new' => ['phone' => '0813-9876-5432', 'expertise' => 'Data Science'],
    ]);
});

it('foto wajib bila belum ada, opsional bila sudah ada', function () {
    $tanpaFoto = incompleteOwner();
    $denganFoto = incompleteOwner(profile: ['photo_path' => 'profile-photos/ada.png']);

    $this->actingAs($tanpaFoto)->post(route('profile.complete.store'), completionPayload(['photo' => null]))
        ->assertSessionHasErrors('photo');
    $this->actingAs($denganFoto)->post(route('profile.complete.store'), completionPayload(['photo' => null]))
        ->assertSessionHasNoErrors();

    expect($denganFoto->userProfile->fresh()->isComplete())->toBeTrue();
});

it('telepon dan keahlian wajib', function () {
    $this->actingAs(incompleteOwner())
        ->post(route('profile.complete.store'), completionPayload(['phone' => '', 'expertise' => '']))
        ->assertSessionHasErrors(['phone', 'expertise']);
});

it('next=submission kembali ke wizard', function () {
    $this->actingAs(incompleteOwner())
        ->post(route('profile.complete.store', ['next' => 'submission']), completionPayload())
        ->assertRedirect(route('user.submissions.create'));
});

it('next tak dikenal jatuh ke dasbor', function () {
    $this->actingAs(incompleteOwner())
        ->post(route('profile.complete.store', ['next' => 'https://evil.example']), completionPayload())
        ->assertRedirect(route('dashboard'));
});

it('profil yang dilengkapi lewat layar ini membuka pembuatan pengajuan', function () {
    // Jalur produksi ujung ke ujung IAM-VERIFY-05: Lengkapi Profil → StartSubmission lolos.
    $fx = wizardFixture();
    $fx['user']->userProfile->update(['verification_status' => 'active', 'phone' => null, 'expertise' => null, 'photo_path' => null]);

    $this->actingAs($fx['user'])->post(route('profile.complete.store'), completionPayload())->assertSessionHasNoErrors();

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']->fresh());

    expect($submission->status)->toBeInstanceOf(Draft::class);
});

it('wizard mengalihkan profil belum lengkap ke Lengkapi Profil', function () {
    $fx = wizardFixture();
    $fx['user']->userProfile->update(['expertise' => null]);

    $this->actingAs($fx['user']->fresh())->get(route('user.submissions.create'))
        ->assertRedirect(route('profile.complete', ['next' => 'submission']));
});

it('wizard terbuka bagi profil lengkap', function () {
    $fx = wizardFixture();

    $this->actingAs($fx['user'])->get(route('user.submissions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Submission/WizardPage'));
});
