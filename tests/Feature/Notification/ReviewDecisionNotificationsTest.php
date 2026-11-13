<?php

// tests/Feature/Notification/ReviewDecisionNotificationsTest.php
//
// BR-NOT-06, ADR 0018 — lima event keputusan review: kesimpulan putaran (siap diputuskan,
// perlu revisi), putaran pemeriksaan, serta Setujui dan Tolak oleh Operator. Kanal tiap event
// dideklarasikan; tanpa barisnya, pengirimannya ditolak (NOT-CHANNEL-04).

use App\Models\FormSubmission;
use App\Models\User;
use App\Notifications\ProposalApprovedNotification;
use App\Notifications\ProposalRejectedNotification;
use App\Notifications\RecheckRequestedNotification;
use App\Notifications\RevisionRequestedNotification;
use App\Notifications\SubmissionReadyForDecisionNotification;
use App\Support\Notification\NotificationChannels;
use App\Support\Notification\NotificationEventCatalog;

dataset('penilaian decision notifications', [
    'SubmissionReadyForDecision' => [SubmissionReadyForDecisionNotification::class, [NotificationChannels::IN_APP]],
    'RevisionRequested' => [RevisionRequestedNotification::class, [NotificationChannels::IN_APP, NotificationChannels::EMAIL]],
    'RecheckRequested' => [RecheckRequestedNotification::class, [NotificationChannels::IN_APP, NotificationChannels::EMAIL]],
    'ProposalApproved' => [ProposalApprovedNotification::class, [NotificationChannels::IN_APP, NotificationChannels::EMAIL]],
    'ProposalRejected' => [ProposalRejectedNotification::class, [NotificationChannels::IN_APP, NotificationChannels::EMAIL]],
]);

it('mendeklarasikan kanal kelima event keputusan review', function (string $class, array $channels) {
    $type = $class::EVENT_TYPE;

    expect(NotificationEventCatalog::defaults()[$type])->toBe($channels)
        ->and(NotificationChannels::for($type))->toBe(NotificationEventCatalog::defaults()[$type]);
})->with('penilaian decision notifications');

it('payload membawa form_submission_id', function (string $class) {
    $submission = FormSubmission::factory()->create();

    $payload = (new $class($submission))->toArray(User::factory()->create());

    expect($payload['form_submission_id'])->toBe($submission->id)
        ->and($payload['title'])->not->toBe('');
})->with('penilaian decision notifications');

it('notifikasi penolakan menyertakan alasannya', function () {
    $submission = FormSubmission::factory()->create(['rejection_reason' => 'Anggaran tidak wajar.']);

    $payload = (new ProposalRejectedNotification($submission))->toArray(User::factory()->create());

    expect($payload['title'])->toBe('Pengajuan ditolak')
        ->and($payload['message'])->toContain('Anggaran tidak wajar.');
});
