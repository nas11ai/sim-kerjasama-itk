<?php

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Events\ProposalSubmitted;
use App\Models\FormSubmission;
use App\Models\User;
use App\Notifications\ProposalSubmittedNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

it('dispatches ProposalSubmitted when a submission is submitted', function () {
    Event::fake([ProposalSubmitted::class]);

    $user = User::factory()->create();
    $submission = FormSubmission::factory()->create(['submitted_by' => $user->id]);

    SubmitFormSubmission::run($submission, $user);

    Event::assertDispatched(
        ProposalSubmitted::class,
        fn (ProposalSubmitted $event) => $event->submission->is($submission),
    );
});

it('notifies operators holding submissions.view-all', function () {
    Notification::fake();
    Permission::findOrCreate('submissions.view-all', 'web');

    $operator = User::factory()->create();
    $operator->givePermissionTo('submissions.view-all');

    $user = User::factory()->create();
    $submission = FormSubmission::factory()->create(['submitted_by' => $user->id]);

    SubmitFormSubmission::run($submission, $user);

    Notification::assertSentTo(
        $operator,
        ProposalSubmittedNotification::class,
    );
});
