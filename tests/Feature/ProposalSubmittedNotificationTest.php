<?php

use App\Models\FormSubmission;
use App\Models\User;
use App\Notifications\ProposalSubmittedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

it('sends over both database and mail channels', function () {
    $submission = FormSubmission::factory()->create();
    $notification = new ProposalSubmittedNotification($submission);

    expect($notification->via(User::factory()->create()))
        ->toBe(['database', 'mail']);
});

it('menyimpan payload NotificationPayload tanpa action_url (W39)', function () {
    // Tujuan "Buka" dihitung saat diklik (NotificationDestination), bukan
    // disimpan: URL tersimpan basi dan melewati pemeriksaan akses BR-SM-07.
    $operator = User::factory()->create();
    $submission = FormSubmission::factory()->create();

    $operator->notify(new ProposalSubmittedNotification($submission));

    $row = $operator->notifications()->first();

    expect($row)->not->toBeNull()
        ->and($row->data['title'])->toBe('Pengajuan Baru Masuk')
        ->and($row->data['message'])->toBe("Pengajuan #{$submission->id} telah dikirim dan menunggu penugasan penilai.")
        ->and($row->data['type'])->toBe('proposal_submitted')
        ->and($row->data['form_submission_id'])->toBe($submission->id)
        ->and($row->data)->not->toHaveKey('action_url');
});

it('implements ShouldQueue so each channel runs as its own queued job', function () {
    // BR-NOT-03 — kegagalan notifikasi tidak boleh menghentikan proses
    // bisnis. Jaminan struktural yang benar-benar memberi itu adalah
    // ShouldQueue: Laravel mem-push satu job queue terpisah PER CHANNEL
    // (lihat Illuminate\Notifications\SendQueuedNotifications), sehingga
    // kegagalan channel `mail` tidak bisa menyentuh submission yang sudah
    // ter-commit maupun channel `database` yang terpisah. Kalau suatu hari
    // ShouldQueue dicabut dari kelas ini, test inilah yang harus gagal.
    $submission = FormSubmission::factory()->create();

    expect(new ProposalSubmittedNotification($submission))->toBeInstanceOf(ShouldQueue::class);
});
