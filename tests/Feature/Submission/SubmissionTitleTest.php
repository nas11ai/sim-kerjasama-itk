<?php

// BR-SM-22 — judul pengajuan: jawaban field `submission_title`, disalin ke
// `form_submissions.title`; tanpa field itu pengajuan tampil dengan judul Form (E2E-11).

use App\Actions\FormSubmission\StartSubmission;
use App\Models\AuditEntry;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\UnderReview;
use App\Support\SubmissionTitleField;
use Database\Seeders\PermissionSeeder;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

/** @return array{owner: User, submission: FormSubmission, title: FormField} */
function titledDraft(string $formTitle = 'Pengajuan Penelitian 2026'): array
{
    $fx = wizardFixture();
    $fx['form']->update(['title' => $formTitle]);
    $title = FormField::create([
        'form_id' => $fx['form']->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => SubmissionTitleField::TYPE])->id,
        'label' => 'Judul Penelitian',
        'is_required' => true,
        'order' => 1,
    ]);

    return [
        'owner' => $fx['user'],
        'submission' => StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']),
        'title' => $title,
    ];
}

function saveTitleStep(User $owner, FormSubmission $submission, FormField $field, ?string $value): TestResponse
{
    return test()->actingAs($owner)->post(route('user.submissions.wizard.save', [$submission, 2]), [
        'goto' => 3,
        'last_updated_at' => $submission->fresh()->updated_at->toISOString(),
        'responses' => [['form_field_id' => $field->id, 'value' => $value]],
    ]);
}

it('[SM-TITLE-01] jawaban field judul menjadi judul pengajuan', function () {
    ['owner' => $owner, 'submission' => $submission, 'title' => $title] = titledDraft();

    saveTitleStep($owner, $submission, $title, '  Biochar Lahan Gambut ')
        ->assertRedirect(route('user.submissions.wizard', [$submission, 3]));

    expect($submission->fresh()->title)->toBe('Biochar Lahan Gambut');

    $this->actingAs($owner)->get(route('user.submissions.index'))
        ->assertInertia(fn (Assert $page) => $page->where('submissions.data.0.title', 'Biochar Lahan Gambut'));
    $this->actingAs($owner)->get(route('user.submissions.show', $submission))
        ->assertInertia(fn (Assert $page) => $page->where('submission.title', 'Biochar Lahan Gambut'));
    $this->actingAs($owner)->get(route('user.submissions.wizard', [$submission, 7]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submission.title', 'Biochar Lahan Gambut')
            ->where('submission.form', 'Pengajuan Penelitian 2026'));
});

it('[SM-TITLE-01] judul hanya diaudit saat berubah', function () {
    ['owner' => $owner, 'submission' => $submission, 'title' => $title] = titledDraft();

    saveTitleStep($owner, $submission, $title, 'Biochar Lahan Gambut');
    saveTitleStep($owner, $submission, $title, 'Biochar Lahan Gambut');

    expect(AuditEntry::query()->auditOnly()->where('description', 'Judul pengajuan diubah')->count())->toBe(1);
});

it('[SM-TITLE-02] Form tanpa field judul memakai judul Form', function () {
    $fx = wizardFixture();
    $fx['form']->update(['title' => 'Hibah Mandiri']);
    $field = wizardTextField($fx['form'], 'Judul');
    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    saveTitleStep($fx['user'], $submission, $field, 'Biochar Lahan Gambut');

    expect($submission->fresh()->title)->toBeNull()
        ->and($submission->fresh()->displayTitle())->toBe('Hibah Mandiri');

    $this->actingAs($fx['user'])->get(route('user.submissions.index'))
        ->assertInertia(fn (Assert $page) => $page->where('submissions.data.0.title', 'Hibah Mandiri'));
});

it('[SM-TITLE-03] jawaban judul yang dikosongkan kembali ke judul Form', function () {
    ['owner' => $owner, 'submission' => $submission, 'title' => $title] = titledDraft();
    saveTitleStep($owner, $submission, $title, 'Biochar Lahan Gambut');

    saveTitleStep($owner, $submission, $title, '   ');

    expect($submission->fresh()->title)->toBeNull()
        ->and($submission->fresh()->displayTitle())->toBe('Pengajuan Penelitian 2026');
});

it('[SM-TITLE-04] Form tidak boleh punya dua field judul pengajuan', function () {
    $this->seed(PermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $form = Form::factory()->create();
    $titleType = FieldType::firstOrCreate(['name' => SubmissionTitleField::TYPE])->id;

    $this->actingAs($admin)->put(route('admin.forms.draft.update', $form), ['fields' => [
        ['field_type_id' => $titleType, 'label' => 'Judul', 'is_required' => true],
        ['field_type_id' => $titleType, 'label' => 'Judul Lain', 'is_required' => false],
    ]])->assertSessionHasErrors(['fields' => 'Form hanya boleh punya satu field bertipe Judul pengajuan.']);

    expect($form->fresh()->draft)->toBeNull();

    $this->actingAs($admin)->put(route('admin.forms.draft.update', $form), ['fields' => [
        ['field_type_id' => $titleType, 'label' => 'Judul', 'is_required' => true],
    ]])->assertSessionHasNoErrors();
});

it('[SM-TITLE-05] pencarian menemukan pengajuan dari judulnya', function () {
    $lead = verifiedResearcher();
    $submission = FormSubmission::factory()->create([
        'form_id' => Form::factory()->create(['title' => 'Pengajuan Penelitian 2026'])->id,
        'submitted_by' => $lead->id,
        'title' => 'Biochar Lahan Gambut',
        'is_submitted' => true,
        'status' => UnderReview::class,
    ]);
    FormSubmission::factory()->create(['is_submitted' => true, 'status' => UnderReview::class]);
    $operator = verifiedResearcher();
    Permission::findOrCreate('submissions.view-all', 'web');
    $operator->givePermissionTo('submissions.view-all');

    $this->actingAs($operator)->get(route('admin.submissions.index', ['search' => 'biochar']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 1)
            ->where('submissions.data.0.id', $submission->id)
            ->where('submissions.data.0.title', 'Biochar Lahan Gambut'));

    $hits = $this->actingAs($operator)->getJson(route('search', ['q' => 'biochar']))->assertOk()->json('submissions');

    expect($hits)->toHaveCount(1)
        ->and($hits[0]['label'])->toBe('Biochar Lahan Gambut');
});
