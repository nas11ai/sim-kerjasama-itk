<?php

// tests/Feature/SuperAdminSubmitDivergenceTest.php
//
// Explicitly-out-of-scope item from the M0 final review: AppServiceProvider's
// Gate::before() grants Super Admin every ability unconditionally, which
// contradicts SubmitFormSubmission's own BR-SM-04 enforcement. Fixing that
// bypass is cross-cutting, predates M0, and has no admin-flow test coverage
// today — restructuring it in this fix wave is risk without a net (see
// .github/CODING_STANDARDS.md "Utang yang Diketahui").
//
// This test does NOT approve the divergence. It only pins today's actual
// behaviour so a future change (M1, when UI starts consuming the `can`
// object) is a deliberate decision, not an accidental regression discovered
// in production.

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Draft;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Role;

it('documents a known divergence: Policy says Super Admin can submit, the Action refuses a non-owner Super Admin', function () {
    Role::findOrCreate('Super Admin');

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $submission = FormSubmission::factory()->create(['status' => Draft::class]);

    // Gate::before() bypass — Policy tidak pernah benar-benar dievaluasi.
    expect($admin->can('submit', $submission))->toBeTrue();

    // SubmitFormSubmission tidak lewat Gate sama sekali — BR-SM-04
    // ditegakkan langsung di Action, dan Super Admin bukan submitted_by di
    // sini, jadi Action menolak.
    expect(fn () => SubmitFormSubmission::run($submission, $admin))
        ->toThrow(AuthorizationException::class);
});
