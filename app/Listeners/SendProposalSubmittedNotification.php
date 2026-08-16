<?php

namespace App\Listeners;

use App\Events\ProposalSubmitted;
use App\Models\User;
use App\Notifications\ProposalSubmittedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class SendProposalSubmittedNotification implements ShouldQueue
{
    public function handle(ProposalSubmitted $event): void
    {
        // Operator adalah pemegang submissions.view-all. Kalau permission ini
        // belum pernah dibuat di database (mis. seeder belum jalan),
        // perlakukan sama seperti tidak ada operator — BR-NOT-03: notifikasi
        // yang gagal tidak boleh menghentikan proses bisnis, submission
        // sudah tersimpan sebelum event ini di-dispatch.
        try {
            $operators = User::permission('submissions.view-all')->get();
        } catch (PermissionDoesNotExist) {
            return;
        }

        if ($operators->isEmpty()) {
            return;
        }

        Notification::send($operators, new ProposalSubmittedNotification($event->submission));
    }
}
