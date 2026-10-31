<?php

// tests/Feature/Review/ReviewThreadAuthorPayloadTest.php
//
// Penulis ulir harus terbaca di ketiga layar pengajuan. Memilih kolom reviewer
// tanpa kunci relasinya membuat Eloquent tak bisa mencocokkan baris, dan
// `reviewer` diam-diam menjadi null (BR-REV-06). Fixture: tests/Support/fixtures.php.

use App\Models\User;
use App\States\Submission\NeedsRevision;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

it('mengirim penulis ulir ke halaman revisi pemilik pengajuan', function () {
    [$owner, , $submission] = threadAuthorFixture(NeedsRevision::class);

    $this->actingAs($owner)
        ->get(route('user.submissions.revision', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('threads.0.reviewer', 'Penilai Ulir')
            ->etc());
});

it('mengirim penulis ulir ke layar Operator', function () {
    [, $author, $submission] = threadAuthorFixture();

    Permission::findOrCreate('users.manage', 'web');
    Permission::findOrCreate('submissions.view-all', 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo(['users.manage', 'submissions.view-all']);

    $this->actingAs($operator)
        ->get(route('admin.submissions.show', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('submission.review_summaries.0.reviewer.user.id', $author->id)
            ->where('submission.review_summaries.0.reviewer.user.name', 'Penilai Ulir')
            ->where('submission.review_summaries.0.reviewer.reviewer_type', 'internal')
            ->etc());
});

it('mengirim penulis ulir ke layar reviewer yang menulisnya', function () {
    [, $author, $submission] = threadAuthorFixture();
    $author->givePermissionTo(Permission::findOrCreate('reviewers.evaluate', 'web'));

    $this->actingAs($author)
        ->get(route('reviewer.assignments.summary', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('threads.0.reviewer', 'Penilai Ulir')
            ->etc());
});
