<?php

namespace App\Listeners;

use App\Events\ProposalSubmitted;
use App\Models\User;
use App\Notifications\ProposalSubmittedNotification;
use App\Support\Notification\Deliver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class SendProposalSubmittedNotification implements ShouldQueue
{
    public function handle(ProposalSubmitted $event): void
    {
        // Operator adalah pemegang submissions.view-all; kalau permission itu
        // belum pernah dibuat, tidak ada operator untuk diberi tahu.
        try {
            $operators = User::permission('submissions.view-all')->get();
        } catch (PermissionDoesNotExist $exception) {
            // Log::error, bukan warning — submissions.view-all entri kanonik di
            // PermissionSeeder, jadi ketiadaannya berarti seeder belum jalan:
            // cacat deploy, bukan kondisi normal.
            Log::error('Gagal mencari operator untuk notifikasi proposal submitted, permission belum ada', [
                'permission' => 'submissions.view-all',
                'submission_id' => $event->submission->id,
                'error' => $exception->getMessage(),
            ]);

            // BR-NOT-03 — notifikasi yang gagal tidak boleh menghentikan proses
            // bisnis: submission sudah tersimpan sebelum event ini di-dispatch.
            return;
        }

        if ($operators->isEmpty()) {
            return;
        }

        Deliver::to($operators, new ProposalSubmittedNotification($event->submission));
    }
}
