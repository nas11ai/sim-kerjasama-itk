<?php

// tests/Feature/SubmissionUniquenessTest.php

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\States\Submission\Draft;
use App\States\Submission\Rejected;
use App\States\Submission\Submitted;
use Illuminate\Database\UniqueConstraintViolationException;

// FormTypeFactory hard-codes 'name' unik, dan FormSubmissionFactory -> Form::factory()
// -> FormType::factory() berantai. Setiap test di sini membuat dua FormSubmission,
// jadi Form dibuat sekali per test dan form_id-nya dipakai ulang agar dua
// FormSubmission tidak bentrok lewat cascade default. Test ini soal index level
// submission, bukan soal Form, jadi berbagi satu Form tidak mengubah apa pun
// yang diuji.
function submissionFor(User $user, SubmissionPeriod $period, ?Scheme $scheme, bool $submitted, string $state, Form $form): FormSubmission
{
    return FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
        'submission_period_id' => $period->id,
        'scheme_id' => $scheme?->id,
        'is_submitted' => $submitted,
        'status' => $state,
    ]);
}

it('blocks a second submitted submission for the same period and scheme', function () {
    $user = User::factory()->create();
    $period = SubmissionPeriod::factory()->create();
    $scheme = Scheme::factory()->create();
    $form = Form::factory()->create();

    submissionFor($user, $period, $scheme, true, Submitted::class, $form);
    submissionFor($user, $period, $scheme, true, Submitted::class, $form);
})->throws(UniqueConstraintViolationException::class);

it('allows several drafts for the same period and scheme', function () {
    $user = User::factory()->create();
    $period = SubmissionPeriod::factory()->create();
    $scheme = Scheme::factory()->create();
    $form = Form::factory()->create();

    submissionFor($user, $period, $scheme, false, Draft::class, $form);
    submissionFor($user, $period, $scheme, false, Draft::class, $form);

    expect(FormSubmission::count())->toBe(2);
});

it('allows resubmitting after a rejection', function () {
    $user = User::factory()->create();
    $period = SubmissionPeriod::factory()->create();
    $scheme = Scheme::factory()->create();
    $form = Form::factory()->create();

    submissionFor($user, $period, $scheme, true, Rejected::class, $form);
    submissionFor($user, $period, $scheme, true, Submitted::class, $form);

    expect(FormSubmission::count())->toBe(2);
});

it('does not constrain submissions without a scheme', function () {
    $user = User::factory()->create();
    $period = SubmissionPeriod::factory()->create();
    $form = Form::factory()->create();

    submissionFor($user, $period, null, true, Submitted::class, $form);
    submissionFor($user, $period, null, true, Submitted::class, $form);

    expect(FormSubmission::count())->toBe(2);
});
