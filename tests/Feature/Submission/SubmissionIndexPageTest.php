<?php

// Kontrak RESEARCHER-02-01 (fe-v2-design §4.5): nama halaman, kunci props, dan
// route dijaga permission DDD `submissions.view-own`.

use App\Models\FormSubmission;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('peneliti membuka Submission/IndexPage', function () {
    [$researcher] = actingResearcher();
    FormSubmission::factory()->create(['submitted_by' => $researcher->id]);

    $this->actingAs($researcher)
        ->get(route('user.submissions.index', ['status' => 'draft']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Submission/IndexPage')
            ->has('submissions.data', 1)
            ->where('filters', ['search' => '', 'status' => 'draft', 'period' => ''])
            ->has('statusOptions')
            ->has('periodOptions'));
});

it('user tanpa submissions.view-own ditolak', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('user.submissions.index'))
        ->assertForbidden();
});
