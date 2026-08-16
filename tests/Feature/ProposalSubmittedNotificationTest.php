<?php

use App\Models\FormSubmission;
use App\Models\User;
use App\Notifications\ProposalSubmittedNotification;
use Illuminate\Support\Facades\Notification;

it('sends over both database and mail channels', function () {
    $submission = FormSubmission::factory()->create();
    $notification = new ProposalSubmittedNotification($submission);

    expect($notification->via(User::factory()->create()))
        ->toBe(['database', 'mail']);
});

it('stores an in-app payload with an action url', function () {
    $operator = User::factory()->create();
    $submission = FormSubmission::factory()->create();

    $operator->notify(new ProposalSubmittedNotification($submission));

    $row = $operator->notifications()->first();

    expect($row)->not->toBeNull()
        ->and($row->data['type'])->toBe('info')
        ->and($row->data['action_url'])->toBe("/submissions/{$submission->id}")
        ->and($row->data['meta']['submission_id'])->toBe($submission->id);
});

it('does not break the caller when a channel fails', function () {
    Notification::fake();
    $submission = FormSubmission::factory()->create();

    // BR-NOT-03 — fire and forget.
    Notification::send(User::factory()->create(), new ProposalSubmittedNotification($submission));

    Notification::assertSentTimes(ProposalSubmittedNotification::class, 1);
});
