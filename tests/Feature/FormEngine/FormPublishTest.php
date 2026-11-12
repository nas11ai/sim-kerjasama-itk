<?php

use App\Actions\FormSubmission\StartSubmission;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormDraft;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use App\Models\User;
use App\Notifications\FormChangedNotification;
use App\Queries\Submission\SubmissionWizard;
use App\Support\Audit\AuditTrail;
use App\Support\RequiredFieldGuard;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * BR-FE-34 — field Form disunting sebagai draf. Terbitkan menulis field ke baris hidup saat itu
 * juga; sebelum itu peneliti tetap membaca field terbitan terakhir. Semua lewat route editor.
 */

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->text = FieldType::firstOrCreate(['name' => 'text']);
});

function publishField(Form $form, string $label, int $order, bool $required = false): FormField
{
    return FormField::create([
        'form_id' => $form->id,
        'field_type_id' => test()->text->id,
        'label' => $label,
        'is_required' => $required,
        'order' => $order,
    ]);
}

/**
 * Isi `fields` kiriman editor: field lama membawa id-nya, field baru tidak.
 *
 * @param  list<array{0: int|null, 1: string, 2?: bool, 3?: int}>  $fields  [id, label, wajib, tipe]
 * @return array{fields: list<array<string, mixed>>}
 */
function draftFields(array $fields): array
{
    return ['fields' => array_map(fn (array $field): array => array_filter([
        'id' => $field[0],
        'field_type_id' => $field[3] ?? test()->text->id,
        'label' => $field[1],
        'is_required' => $field[2] ?? false,
    ], fn (mixed $value): bool => $value !== null), $fields)];
}

function answer(FormSubmission $submission, FormField $field, string $value): FormFieldResponse
{
    return FormFieldResponse::create(['form_submission_id' => $submission->id, 'form_field_id' => $field->id, 'value' => $value]);
}

function formPublishAudit(Form $form, string $description): ?Activity
{
    return Activity::inLog(AuditTrail::LOG_NAME)
        ->where('subject_type', $form->getMorphClass())
        ->where('subject_id', $form->id)
        ->where('description', $description)
        ->first();
}

it('creates a form with a draft and no published fields', function () {
    $this->actingAs($this->admin)->post(route('admin.forms.store'), [
        'title' => 'Form Baru',
        'form_type_id' => regularFormType()->id,
        ...draftFields([[null, 'Judul', true]]),
    ])->assertSessionHas('success', 'Form dibuat. Susun field-nya lalu terbitkan.');

    $form = Form::query()->where('title', 'Form Baru')->sole();

    expect($form->published_at)->toBeNull()
        ->and($form->formFields()->count())->toBe(0)
        ->and($form->draft()->sole()->payload['fields'][0]['label'])->toBe('Judul');

    $this->actingAs($this->admin)->get(route('admin.forms.edit', $form))
        ->assertInertia(fn (Assert $page) => $page
            ->where('general.hasDraft', true)
            ->where('general.publishedAt', null)
            ->where('general.publishedFields', [])
            ->where('general.fields.0.label', 'Judul')
            ->where('general.fields.0.id', null));
});

it('[FE-FORMPUB-01] keeps draft fields away from researchers until publish', function () {
    $fx = wizardFixture();
    $judul = publishField($fx['form'], 'Judul', 1);
    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $this->actingAs($this->admin)
        ->put(route('admin.forms.draft.update', $fx['form']), draftFields([[$judul->id, 'Judul'], [null, 'Mitra', true]]))
        ->assertSessionHas('success', 'Draf disimpan.');

    $labels = array_map(fn ($field) => $field->label, SubmissionWizard::run($fx['user'], $submission->fresh(), 2)['fields']);

    expect($labels)->toBe(['Judul'])
        ->and($fx['form']->formFields()->count())->toBe(1);

    $this->actingAs($this->admin)->get(route('admin.forms.edit', $fx['form']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('general.hasDraft', true)
            ->has('general.fields', 2)
            ->where('general.fields.0.id', $judul->id)
            ->where('general.fields.1.label', 'Mitra')
            ->has('general.publishedFields', 1));
});

it('[FE-FORMPUB-02] summarises new required fields, running drafts, relabelled answered fields and removals', function () {
    $fx = wizardFixture();
    $judul = publishField($fx['form'], 'Judul', 1);
    $lokasi = publishField($fx['form'], 'Lokasi', 2);
    $first = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    FormSubmission::factory()->create(['form_id' => $fx['form']->id, 'is_submitted' => false]);
    answer($first, $judul, 'A');
    foreach (range(1, 2) as $i) {
        answer(FormSubmission::factory()->create(['form_id' => $fx['form']->id, 'is_submitted' => true]), $judul, "B{$i}");
    }

    $this->actingAs($this->admin)->put(route('admin.forms.draft.update', $fx['form']), draftFields([
        [$judul->id, 'Judul Penelitian'],
        [null, 'Mitra', true],
    ]));

    $this->actingAs($this->admin)->get(route('admin.forms.edit', $fx['form']))
        ->assertInertia(fn (Assert $page) => $page->where('general.impact', [
            'newRequired' => 1,
            'draftOwners' => 2,
            'relabelled' => [['label' => 'Judul', 'answered' => 3]],
            'removed' => 1,
        ]));
    expect($lokasi->fresh())->not->toBeNull();
});

it('counts an existing field that becomes required as a new required field', function () {
    $form = Form::factory()->create();
    $field = publishField($form, 'Mitra', 1);

    $this->actingAs($this->admin)->put(route('admin.forms.draft.update', $form), draftFields([[$field->id, 'Mitra', true]]));

    $this->actingAs($this->admin)->get(route('admin.forms.edit', $form))
        ->assertInertia(fn (Assert $page) => $page
            ->where('general.impact.newRequired', 1)
            ->where('general.impact.relabelled', []));
});

it('[FE-FORMPUB-03] a required field published later does not block a running draft, and removed answers stay readable', function () {
    Notification::fake();
    $fx = wizardFixture();
    $lokasi = publishField($fx['form'], 'Lokasi', 1, required: true);
    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $jawaban = answer($submission, $lokasi, 'Balikpapan');

    $this->actingAs($this->admin)->put(route('admin.forms.draft.update', $fx['form']), draftFields([[null, 'Mitra', true]]));
    $this->travel(1)->days();

    $this->actingAs($this->admin)->post(route('admin.forms.publish', $fx['form']), ['notify_affected' => false])
        ->assertSessionHas('success', 'Form diterbitkan.');

    $mitra = $fx['form']->formFields()->sole();

    expect($mitra->label)->toBe('Mitra')
        ->and($mitra->created_at->greaterThan($submission->created_at))->toBeTrue()
        ->and(RequiredFieldGuard::missingFields($submission->fresh()))->toHaveCount(0)
        ->and(FormField::withTrashed()->find($lokasi->id)?->trashed())->toBeTrue()
        ->and($jawaban->fresh()?->value)->toBe('Balikpapan')
        ->and(FormDraft::query()->where('draftable_id', $fx['form']->id)->exists())->toBeFalse();
    Notification::assertNothingSent();
});

it('[FE-FORMPUB-04] relabelling an answered field is warned, not blocked: answers stay on the same field', function () {
    $form = Form::factory()->create();
    $judul = publishField($form, 'Judul', 1);
    $jawaban = answer(FormSubmission::factory()->create(['form_id' => $form->id]), $judul, 'Isi lama');

    $this->actingAs($this->admin)->put(route('admin.forms.draft.update', $form), draftFields([[$judul->id, 'Judul Penelitian']]))
        ->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('admin.forms.publish', $form))->assertSessionHasNoErrors();

    expect($judul->fresh()->label)->toBe('Judul Penelitian')
        ->and($jawaban->fresh()?->form_field_id)->toBe($judul->id);
});

it('[FE-CHANGE-03] publishing with notify_affected notifies running draft owners', function () {
    Notification::fake();
    $fx = wizardFixture();
    StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    $this->actingAs($this->admin)->put(route('admin.forms.draft.update', $fx['form']), draftFields([[null, 'Mitra', true]]));
    $this->actingAs($this->admin)->post(route('admin.forms.publish', $fx['form']), ['notify_affected' => true])
        ->assertSessionHas('success', 'Form diterbitkan. 1 peneliti diberi tahu.');

    Notification::assertSentTo($fx['user'], FormChangedNotification::class);
});

it('publishes once: published_at keeps the first publish, audits, and drops the draft', function () {
    $form = Form::factory()->create(['published_at' => null]);
    $this->actingAs($this->admin)->put(route('admin.forms.draft.update', $form), draftFields([[null, 'Judul']]));
    $this->actingAs($this->admin)->post(route('admin.forms.publish', $form))->assertSessionHasNoErrors();
    $first = $form->fresh()->published_at;

    $this->travel(1)->days();
    $this->actingAs($this->admin)->put(route('admin.forms.draft.update', $form), draftFields([[null, 'Abstrak']]));
    $this->actingAs($this->admin)->post(route('admin.forms.publish', $form))->assertSessionHasNoErrors();

    $audit = formPublishAudit($form, 'Formulir diterbitkan');

    expect($first)->not->toBeNull()
        ->and($form->fresh()->published_at->equalTo($first))->toBeTrue()
        ->and($form->formFields()->pluck('label')->all())->toBe(['Abstrak'])
        ->and($audit?->properties['new']['fields'])->toContain('Judul')
        ->and($form->draft()->exists())->toBeFalse();
});

it('refuses to publish without a draft', function () {
    $form = Form::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.forms.publish', $form))
        ->assertSessionHasErrors(['draft' => 'Tidak ada draf untuk diterbitkan.']);
});

it('discards a draft without touching published fields', function () {
    $form = Form::factory()->create();
    publishField($form, 'Judul', 1);
    $this->actingAs($this->admin)->put(route('admin.forms.draft.update', $form), draftFields([]));

    $this->actingAs($this->admin)->delete(route('admin.forms.draft.destroy', $form))
        ->assertSessionHas('success', 'Draf dibuang.');

    expect($form->draft()->exists())->toBeFalse()
        ->and($form->formFields()->pluck('label')->all())->toBe(['Judul']);
});

it('still refuses a type change of an answered field at publish', function () {
    $form = Form::factory()->create();
    $field = publishField($form, 'Ringkasan', 1);
    answer(FormSubmission::factory()->create(['form_id' => $form->id]), $field, 'Isi');
    $select = FieldType::firstOrCreate(['name' => 'select']);

    $this->actingAs($this->admin)->put(route('admin.forms.draft.update', $form), draftFields([[$field->id, 'Ringkasan', false, $select->id]]))
        ->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('admin.forms.publish', $form))->assertSessionHasErrors('field_type_id');

    expect($field->fresh()->field_type_id)->toBe($this->text->id)
        ->and($form->draft()->exists())->toBeTrue();
});

it('rejects a field id of another form at publish and leaves that form intact', function () {
    $form = Form::factory()->create();
    $foreign = publishField(Form::factory()->create(), 'Milik Form Lain', 1);

    $this->actingAs($this->admin)->put(route('admin.forms.draft.update', $form), draftFields([[$foreign->id, 'Dibajak']]));
    $this->actingAs($this->admin)->post(route('admin.forms.publish', $form))->assertSessionHasErrors('fields.0.id');

    expect($foreign->fresh()?->label)->toBe('Milik Form Lain');
});

it('rejects a trl_selector without a scheme_selector in the draft (BR-SCH-05)', function () {
    $form = Form::factory()->create();
    $trl = FieldType::firstOrCreate(['name' => 'trl_selector']);

    $this->actingAs($this->admin)->put(route('admin.forms.draft.update', $form), draftFields([[null, 'TRL', true, $trl->id]]))
        ->assertSessionHasErrors('fields');

    expect($form->draft()->exists())->toBeFalse();
});

it('saves General directly and ignores fields sent to update', function () {
    $form = Form::factory()->create();
    publishField($form, 'Judul', 1);

    $this->actingAs($this->admin)->from(route('admin.forms.edit', $form))->put(route('admin.forms.update', $form), [
        'title' => 'Judul Baru',
        'form_type_id' => $form->form_type_id,
        ...draftFields([]),
    ])->assertRedirect(route('admin.forms.edit', $form))->assertSessionHas('success', 'Form diperbarui.');

    expect($form->fresh()->title)->toBe('Judul Baru')
        ->and($form->formFields()->count())->toBe(1)
        ->and($form->draft()->exists())->toBeFalse();
});

it('marks never-published forms in the directory', function () {
    Form::factory()->create(['title' => 'Belum', 'published_at' => null]);

    $this->actingAs($this->admin)->get(route('admin.forms.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('forms.data.0.title', 'Belum')
            ->where('forms.data.0.isPublished', false));
});

it('forbids an operator from the draft and publish routes', function () {
    $form = Form::factory()->create();
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $this->actingAs($operator)->put(route('admin.forms.draft.update', $form), draftFields([]))->assertForbidden();
    $this->actingAs($operator)->delete(route('admin.forms.draft.destroy', $form))->assertForbidden();
    $this->actingAs($operator)->post(route('admin.forms.publish', $form))->assertForbidden();
});

it('locks the form before touching its draft on save, discard, and publish', function () {
    // B13 — urutan kunci sama dengan Terbitkan, jadi simpan/buang yang berpapasan menunggu.
    $form = Form::factory()->create();
    $this->actingAs($this->admin);
    $save = fn () => $this->put(route('admin.forms.draft.update', $form), draftFields([[null, 'Judul']]));
    $discard = fn () => $this->delete(route('admin.forms.draft.destroy', $form));
    $publish = fn () => $this->post(route('admin.forms.publish', $form));

    expect(lockedBefore('forms', 'for update', 'configuration_drafts', $save))->toBeTrue()
        ->and(lockedBefore('forms', 'for update', 'configuration_drafts', $discard))->toBeTrue();
    $save();
    expect(lockedBefore('forms', 'for update', 'configuration_drafts', $publish))->toBeTrue();
});

it('rejects a draft from a stale screen that keeps a field or option another publish deleted', function () {
    // B14 — field terhapus masih ada di tabel (soft delete); `exists` biasa menerimanya.
    $form = Form::factory()->create();
    $field = publishField($form, 'Pilihan', 1);
    $option = FormFieldOption::create(['form_field_id' => $field->id, 'label' => 'A']);
    $option->delete();
    $field->delete();
    $stale = draftFields([[$field->id, 'Pilihan']]);
    $stale['fields'][0]['options'] = [['id' => $option->id, 'label' => 'A']];

    $this->actingAs($this->admin)->put(route('admin.forms.draft.update', $form), $stale)
        ->assertSessionHasErrors([
            'fields.0.id' => 'Field ini sudah dihapus oleh penerbitan lain. Muat ulang halaman lalu coba lagi.',
            'fields.0.options.0.id' => 'Opsi ini sudah dihapus oleh penerbitan lain. Muat ulang halaman lalu coba lagi.',
        ]);

    expect($form->draft()->exists())->toBeFalse();
});

it('counts and notifies the owner of a running monev report draft', function () {
    // B15 — BR-FE-13 tidak mengecualikan Form laporan: draf laporan turunan juga sedang diisi.
    Notification::fake();
    $form = Form::factory()->create();
    $parent = FormSubmission::factory()->create(['is_submitted' => true]);
    $report = FormSubmission::factory()->create(['form_id' => $form->id, 'parent_submission_id' => $parent->id, 'is_submitted' => false]);
    $this->actingAs($this->admin)->put(route('admin.forms.draft.update', $form), draftFields([[null, 'Capaian', true]]));

    $this->get(route('admin.forms.edit', $form))
        ->assertInertia(fn (Assert $page) => $page->where('general.impact.draftOwners', 1));
    $this->post(route('admin.forms.publish', $form), ['notify_affected' => true])
        ->assertSessionHas('success', 'Form diterbitkan. 1 peneliti diberi tahu.');

    Notification::assertSentTo(User::query()->findOrFail($report->submitted_by), FormChangedNotification::class);
});
