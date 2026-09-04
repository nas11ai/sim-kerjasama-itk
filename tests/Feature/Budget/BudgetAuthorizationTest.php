<?php

use App\Actions\Budget\SaveBudgetLineItem;
use App\Models\BudgetComponent;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;
use Illuminate\Auth\Access\AuthorizationException;

function submissionOwnedBy(User $owner, string $state = Draft::class): FormSubmission
{
    return FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => $state,
        'scheme_id' => null,
    ]);
}

/**
 * ResearchMember menunjuk UserProfile, bukan User — sama seperti
 * FormSubmission::hasActiveResearchMember() menelusurinya.
 */
function enrolResearchMember(FormSubmission $submission, User $user, string $role): void
{
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $user->id])->id,
        'role' => $role,
        'status' => 'active',
    ]);
}

it('[BUD-WHO-01] ketua pengaju boleh menyunting anggaran', function () {
    $owner = User::factory()->create();
    $submission = submissionOwnedBy($owner);

    expect($owner->can('update-budget', $submission))->toBeTrue();
});

it('[BUD-WHO-02] co-investigator boleh menyunting anggaran', function () {
    $owner = User::factory()->create();
    $co = User::factory()->create();
    $submission = submissionOwnedBy($owner);
    enrolResearchMember($submission, $co, ResearchMember::ROLE_CO_INVESTIGATOR);

    expect($co->can('update-budget', $submission->fresh()))->toBeTrue();
});

it('[BUD-WHO-03] member biasa hanya bisa membaca anggaran', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $submission = submissionOwnedBy($owner);
    enrolResearchMember($submission, $member, ResearchMember::ROLE_MEMBER);

    expect($member->can('update-budget', $submission->fresh()))->toBeFalse();
});

it('[BUD-LOCK-01] anggaran terkunci pada status terminal', function (string $state) {
    $owner = User::factory()->create();
    $submission = submissionOwnedBy($owner, $state);

    expect($owner->can('update-budget', $submission))->toBeFalse();
})->with([Approved::class, Rejected::class, Withdrawn::class]);

it('[BUD-LOCK-02] anggaran tetap terbuka saat perlu revisi', function () {
    $owner = User::factory()->create();
    $submission = submissionOwnedBy($owner, NeedsRevision::class);

    expect($owner->can('update-budget', $submission))->toBeTrue();
});

/*
 * Task 7 membuktikan Policy menolak. Dua test di bawah membuktikan Action ikut
 * menolak — supaya penguncian tidak bocor lewat jalur yang tidak memanggil Gate.
 */

it('[BUD-LOCK-01] action menolak penyuntingan pada status terminal', function (string $state) {
    $owner = User::factory()->create();
    $submission = submissionOwnedBy($owner, $state);
    $component = BudgetComponent::factory()->create(['is_active' => true]);

    expect(fn () => SaveBudgetLineItem::run($submission, $owner, [
        'budget_component_id' => $component->id,
        'item_name' => 'Sensor kualitas air',
        'volume' => 1,
        'unit' => 'unit',
        'unit_price' => 1_000_000,
    ]))->toThrow(AuthorizationException::class);
})->with([Approved::class, Rejected::class, Withdrawn::class]);

it('[BUD-LOCK-02] action menerima penyuntingan saat perlu revisi', function () {
    $owner = User::factory()->create();
    $submission = submissionOwnedBy($owner, NeedsRevision::class);
    $component = BudgetComponent::factory()->create(['is_active' => true]);

    $item = SaveBudgetLineItem::run($submission, $owner, [
        'budget_component_id' => $component->id,
        'item_name' => 'Sensor kualitas air',
        'volume' => 1,
        'unit' => 'unit',
        'unit_price' => 1_000_000,
    ]);

    expect($item->total)->toBe(1_000_000);
});
