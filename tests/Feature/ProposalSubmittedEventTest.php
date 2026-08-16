<?php

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Events\ProposalSubmitted;
use App\Listeners\SendProposalSubmittedNotification;
use App\Models\FormSubmission;
use App\Models\User;
use App\Notifications\ProposalSubmittedNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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

it('logs and returns early when submissions.view-all does not exist', function () {
    // Regresi: Spatie melempar PermissionDoesNotExist, bukan koleksi kosong,
    // kalau permission-nya belum pernah di-seed sama sekali. BR-NOT-03 —
    // kegagalan tidak boleh menghentikan proses bisnis, tapi juga tidak
    // boleh didiamkan tanpa jejak.
    Log::spy();

    $submission = FormSubmission::factory()->create();

    (new SendProposalSubmittedNotification)->handle(new ProposalSubmitted($submission));

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['permission'] === 'submissions.view-all'
            && $context['submission_id'] === $submission->id);
});
