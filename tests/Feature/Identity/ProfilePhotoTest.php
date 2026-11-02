<?php

// W65 — foto profil di disk privat (W15), dibaca hanya oleh pemiliknya sendiri.
// Mengganti foto adalah perubahan oleh user sendiri: diaudit (BR-RPT-09), tanpa
// notifikasi (BR-IAM-15).

use App\Models\User;
use App\Models\UserProfile;
use App\Support\Audit\AuditTrail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

function photoOwner(?string $photoPath = 'profile-photos/lama.png'): User
{
    $user = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $user->id, 'photo_path' => $photoPath]);

    return $user->fresh();
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

it('mengganti foto menyimpannya di disk privat dan menghapus yang lama', function () {
    $user = photoOwner();
    Storage::disk('local')->put('profile-photos/lama.png', 'x');

    $this->actingAs($user)
        ->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('a.png')])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $path = $user->userProfile->fresh()->photo_path;

    expect($path)->toStartWith('profile-photos/')->not->toBe('profile-photos/lama.png');
    Storage::disk('local')->assertExists($path);
    Storage::disk('local')->assertMissing('profile-photos/lama.png');
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('audit foto tanpa path', function () {
    $user = photoOwner();

    $this->actingAs($user)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('a.jpg')]);

    $entry = Activity::query()->where('log_name', AuditTrail::LOG_NAME)->where('description', 'Foto profil diganti')->sole();

    expect($entry->causer_id)->toBe($user->id)
        ->and($entry->properties->all())->toBe([]);
});

it('foto selain JPG/PNG atau lebih dari 2 MB ditolak', function (Closure $file) {
    $user = photoOwner();

    $this->actingAs($user)
        ->post(route('profile.photo.update'), ['photo' => $file()])
        ->assertSessionHasErrors('photo');

    expect($user->userProfile->fresh()->photo_path)->toBe('profile-photos/lama.png')
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'gif' => fn () => UploadedFile::fake()->image('a.gif'),
    'svg' => fn () => UploadedFile::fake()->create('x.svg', 10, 'image/svg+xml'),
    'pdf bernama jpg' => fn () => UploadedFile::fake()->create('x.jpg', 10, 'application/pdf'),
    'lebih dari 2 MB' => fn () => UploadedFile::fake()->image('b.jpg')->size(2049),
]);

it('foto sendiri terbaca, tanpa foto 404, tamu ke login', function () {
    $user = photoOwner();
    Storage::disk('local')->put('profile-photos/lama.png', 'isi-foto');

    $this->actingAs($user)->get(route('profile.photo.show'))->assertOk();
    $this->actingAs(photoOwner(null))->get(route('profile.photo.show'))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('profile.photo.show'))->assertNotFound();

    auth()->logout();
    $this->get(route('profile.photo.show'))->assertRedirect(route('login'));
});

it('mengganti foto tidak mengirim notifikasi', function () {
    Notification::fake();

    $this->actingAs(photoOwner())->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('a.png')]);

    Notification::assertNothingSent();
});
