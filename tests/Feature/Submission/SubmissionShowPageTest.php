<?php

// Kontrak RESEARCHER-02-03/02-12 (fe-v2-design §4.5). Akses level pengajuan
// (BR-SM-07) dijaga controller sebelum read model dipanggil.

use App\Models\FormSubmission;
use App\Models\User;
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

it('[SM-ACCESS-03] peneliti lain ditolak', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('user.submissions.show', FormSubmission::factory()->create()))
        ->assertForbidden();
});
