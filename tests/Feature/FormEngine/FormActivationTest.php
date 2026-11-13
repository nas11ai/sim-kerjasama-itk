<?php

// ADMIN-08-05 — aktifkan, nonaktifkan, dan hapus Form (BR-FE-19, W76–W78, W88).

use App\Actions\Form\SetFormActive;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

function activationActor(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('menonaktifkan dan mengaktifkan mengaudit perubahan', function () {
    $form = Form::factory()->create(['is_active' => true]);
    $actor = activationActor();

    SetFormActive::run($form, false, $actor);
    SetFormActive::run($form, true, $actor);

    $logs = Activity::query()->where('subject_type', $form->getMorphClass())->where('subject_id', $form->id)
        ->whereIn('description', ['Formulir dinonaktifkan', 'Formulir diaktifkan'])->orderBy('id')->get();

    expect($form->fresh()->is_active)->toBeTrue()
        ->and($logs->pluck('description')->all())->toBe(['Formulir dinonaktifkan', 'Formulir diaktifkan']);
});

it('menyetel nilai yang sama tidak mengaudit', function () {
    $form = Form::factory()->create(['is_active' => true]);

    SetFormActive::run($form, true, activationActor());

    expect(Activity::query()->where('description', 'Formulir diaktifkan')->count())->toBe(0);
});

it('wewenang: peneliti tidak bisa mengubah status maupun menghapus', function () {
    $form = Form::factory()->create();
    $researcher = activationActor('researcher');

    $this->actingAs($researcher)->patch(route('admin.forms.active', $form), ['active' => false])->assertForbidden();
    $this->actingAs($researcher)->delete(route('admin.forms.destroy', $form))->assertForbidden();

    expect($form->fresh()->is_active)->toBeTrue();
});

it('mengubah status lewat HTTP kembali dengan pesan', function () {
    $form = Form::factory()->create(['is_active' => true]);

    $this->actingAs(activationActor())
        ->from(route('admin.forms.index'))
        ->patch(route('admin.forms.active', $form), ['active' => false])
        ->assertRedirect(route('admin.forms.index'))
        ->assertSessionHas('success', 'Form dinonaktifkan.');

    expect($form->fresh()->is_active)->toBeFalse();
});

it('status wajib boolean', function () {
    $form = Form::factory()->create();

    $this->actingAs(activationActor())
        ->patch(route('admin.forms.active', $form), [])
        ->assertSessionHasErrors('active');
});

it('hapus form kosong lewat HTTP', function () {
    $form = Form::factory()->create();

    $this->actingAs(activationActor())
        ->delete(route('admin.forms.destroy', $form))
        ->assertRedirect(route('admin.forms.index'));

    expect(Form::query()->whereKey($form->id)->exists())->toBeFalse();
});

it('hapus form berpengajuan lewat HTTP ditolak dengan angkanya', function () {
    $form = Form::factory()->create();
    FormSubmission::factory()->count(2)->create(['form_id' => $form->id]);

    $this->actingAs(activationActor())
        ->delete(route('admin.forms.destroy', $form))
        ->assertSessionHasErrors('id');

    expect(session('errors')->first('id'))->toContain('2 pengajuan')
        ->and(Form::query()->whereKey($form->id)->exists())->toBeTrue();
});

it('Form baru tanpa is_active aktif secara bawaan', function () {
    $form = Form::create(['title' => 'Tanpa flag', 'form_type_id' => regularFormType()->id]);

    expect($form->fresh()->is_active)->toBeTrue();
});
