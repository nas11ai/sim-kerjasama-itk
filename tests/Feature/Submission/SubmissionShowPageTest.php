<?php

// Kontrak RESEARCHER-02-03/02-12 (fe-v2-design §4.5). Akses level pengajuan
// (BR-SM-07) dijaga controller sebelum read model dipanggil.

use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\ResearchMember;
use App\Models\User;
use App\Models\UserProfile;
use Inertia\Testing\AssertableInertia as Assert;

it('pemilik membuka Submission/ShowPage', function () {
    $submission = FormSubmission::factory()->create();

    $this->actingAs($submission->submittedBy)
        ->get(route('user.submissions.show', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Submission/ShowPage')
            ->where('submission.id', $submission->id)
            ->has('can')
            ->has('budget')
            ->has('scores')
            ->has('history')
            ->has('answers')
            ->has('members')
            ->has('targetOutputs')
            ->has('phaseAccess')
            ->has('printableDocuments')
            ->where('decision', null));
});

it('ketua menerima pilihan tipe dan jejak target luaran', function () {
    OutputTypeDefinition::firstOrCreate(['code' => 'publikasi'], ['name' => 'Publikasi', 'is_active' => true]);
    $submission = FormSubmission::factory()->create();

    $this->actingAs($submission->submittedBy)
        ->get(route('user.submissions.show', $submission))
        ->assertInertia(fn (Assert $page) => $page
            ->has('targetHistory')
            ->where('can.manageTargetOutput', true)
            ->has('targetOutputTypes.0', fn (Assert $type) => $type->hasAll(['value', 'label'])));
});

it('co-investigator tidak menerima pilihan tipe target luaran', function () {
    OutputTypeDefinition::firstOrCreate(['code' => 'publikasi'], ['name' => 'Publikasi', 'is_active' => true]);
    $submission = FormSubmission::factory()->create();
    $coInvestigator = User::factory()->create();
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $coInvestigator->id])->id,
        'role' => ResearchMember::ROLE_CO_INVESTIGATOR,
        'status' => 'active',
    ]);

    $this->actingAs($coInvestigator)
        ->get(route('user.submissions.show', $submission))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageTargetOutput', false)
            ->where('targetOutputTypes', []));
});

it('[SM-ACCESS-03] peneliti lain ditolak', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('user.submissions.show', FormSubmission::factory()->create()))
        ->assertForbidden();
});
