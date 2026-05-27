<?php

namespace App\Services;

use App\Mail\DynamicEmailNotification;
use App\Models\FormSubmission;
use App\Models\ReviewComment;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotificationService
{
    /**
     * 1. Notifikasi ketika user submit form
     */
    public function notifyAdminFormSubmission(FormSubmission $submission)
    {
        try {
            $admins = User::role(['Super Admin', 'Admin'])->get();

            $subject = "Form Submission Baru - {$submission->form->title}";
            $params = [
                'judul' => 'Form Submission Baru',
                'isi' => "
                    <p>Halo Admin,</p>
                    <p><strong>{$submission->submittedBy->name}</strong> telah mengajukan form submission baru:</p>
                    <ul>
                        <li><strong>Form:</strong> {$submission->form->title}</li>
                        <li><strong>Tanggal Submit:</strong> {$submission->created_at->format('d F Y H:i')}</li>
                        <li><strong>Status:</strong> {$submission->status->label()}</li>
                    </ul>
                    <p>Silakan cek detail submission di sistem.</p>
                    <p><a href='".route('admin.submissions.show', $submission->id)."' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Lihat Detail Submission</a></p>
                ",
            ];

            foreach ($admins as $admin) {
                Mail::to($admin->email)->queue(
                    new DynamicEmailNotification([
                        'subject' => $subject,
                        'view' => 'emails.template',
                        'params' => $params,
                    ])
                );
            }

            Log::info('Email notifikasi form submission dikirim ke admin', [
                'submission_id' => $submission->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Gagal mengirim email notifikasi form submission', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 2. Notifikasi ketika admin assign reviewer
     */
    public function notifyReviewerAssignment(FormSubmission $submission, Reviewer $reviewer)
    {
        try {
            $subject = "Anda Ditugaskan sebagai Reviewer - {$submission->form->title}";
            $params = [
                'judul' => 'Penugasan Reviewer Baru',
                'isi' => "
                    <p>Halo {$reviewer->user->name},</p>
                    <p>Anda telah ditugaskan sebagai reviewer untuk submission berikut:</p>
                    <ul>
                        <li><strong>Form:</strong> {$submission->form->title}</li>
                        <li><strong>Diajukan oleh:</strong> {$submission->submittedBy->name}</li>
                        <li><strong>Tanggal Submit:</strong> {$submission->created_at->format('d F Y H:i')}</li>
                        <li><strong>Role Reviewer:</strong> {$reviewer->reviewerRole->name}</li>
                    </ul>
                    <p>Silakan review submission ini sesegera mungkin.</p>
                    <p><a href='".route('reviewer.submissions.show', $submission->id)."' style='background-color: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Lihat Submission</a></p>
                ",
            ];

            Mail::to($reviewer->user->email)->queue(
                new DynamicEmailNotification([
                    'subject' => $subject,
                    'view' => 'emails.template',
                    'params' => $params,
                ])
            );

            Log::info('Email notifikasi assignment reviewer dikirim', [
                'submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Gagal mengirim email notifikasi assignment reviewer', [
                'submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 3. Notifikasi ketika admin menghapus reviewer
     */
    public function notifyReviewerRemoval(FormSubmission $submission, Reviewer $reviewer)
    {
        try {
            $subject = "Anda Dihapus dari Reviewer - {$submission->form->title}";
            $params = [
                'judul' => 'Penghapusan Reviewer',
                'isi' => "
                    <p>Halo {$reviewer->user->name},</p>
                    <p>Anda telah dihapus dari daftar reviewer untuk submission berikut:</p>
                    <ul>
                        <li><strong>Form:</strong> {$submission->form->title}</li>
                        <li><strong>Diajukan oleh:</strong> {$submission->submittedBy->name}</li>
                    </ul>
                    <p>Jika ada pertanyaan, silakan hubungi admin.</p>
                ",
            ];

            Mail::to($reviewer->user->email)->queue(
                new DynamicEmailNotification([
                    'subject' => $subject,
                    'view' => 'emails.template',
                    'params' => $params,
                ])
            );

            Log::info('Email notifikasi removal reviewer dikirim', [
                'submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Gagal mengirim email notifikasi removal reviewer', [
                'submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 4. Notifikasi ketika membuat review thread
     */
    public function notifyReviewThreadCreated(ReviewSummary $reviewSummary)
    {
        try {
            $submission = $reviewSummary->formSubmission;
            $creator = $reviewSummary->reviewer
                ? $reviewSummary->reviewer->user
                : Auth::user();

            $subject = "Review Thread Baru - {$submission->form->title}";
            $params = [
                'judul' => 'Review Thread Baru Dibuat',
                'isi' => "
                    <p>Review thread baru telah dibuat untuk submission:</p>
                    <ul>
                        <li><strong>Form:</strong> {$submission->form->title}</li>
                        <li><strong>Dibuat oleh:</strong> {$creator->name}</li>
                        <li><strong>Status:</strong> ".ucfirst($reviewSummary->status).'</li>
                        <li><strong>Catatan:</strong> '.nl2br(e($reviewSummary->summary_notes))."</li>
                    </ul>
                    <p><a href='".route('admin.submissions.show', $submission->id)."' style='background-color: #FF9800; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Lihat Review Thread</a></p>
                ",
            ];

            // Kirim ke admin, semua reviewer, dan submitter
            $recipients = $this->getReviewParticipants($submission);
            $this->sendToMultipleRecipients($recipients, $subject, $params);

            Log::info('Email notifikasi review thread created dikirim', [
                'review_summary_id' => $reviewSummary->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Gagal mengirim email notifikasi review thread created', [
                'review_summary_id' => $reviewSummary->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 5. Notifikasi ketika ada comment/reply di review thread
     */
    public function notifyReviewComment(ReviewComment $comment)
    {
        try {
            $reviewSummary = $comment->reviewSummary;
            /** @var FormSubmission $submission */
            $submission = $reviewSummary->formSubmission;
            $commenter = $comment->user ?: ($comment->reviewer ? $comment->reviewer->user : null);

            $isReply = $comment->parent_comment_id !== null;
            $type = $isReply ? 'Reply' : 'Comment';

            $subject = "{$type} Baru pada Review Thread - {$submission->form->title}";
            $params = [
                'judul' => "{$type} Baru pada Review Thread",
                'isi' => "
                    <p><strong>{$commenter->name}</strong> telah menambahkan {$type} pada review thread:</p>
                    <ul>
                        <li><strong>Form:</strong> {$submission->form->title}</li>
                        <li><strong>{$type}:</strong> ".nl2br(e($comment->comment_text))."</li>
                        <li><strong>Waktu:</strong> {$comment->created_at->format('d F Y H:i')}</li>
                    </ul>
                    <p><a href='".route('admin.submissions.show', $submission->id)."#comment-{$comment->id}' style='background-color: #9C27B0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Lihat {$type}</a></p>
                ",
            ];

            // Kirim ke admin, semua reviewer, dan submitter (kecuali commenter sendiri)
            $recipients = $this->getReviewParticipants($submission, [$commenter->id]);
            $this->sendToMultipleRecipients($recipients, $subject, $params);

            Log::info('Email notifikasi review comment dikirim', [
                'comment_id' => $comment->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Gagal mengirim email notifikasi review comment', [
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 6. Notifikasi ketika status review thread berubah
     */
    public function notifyReviewStatusChanged(ReviewSummary $reviewSummary, string $oldStatus)
    {
        try {
            $submission = $reviewSummary->formSubmission;
            $updater = Auth::user();

            $statusLabels = [
                'open' => 'Dibuka',
                'resolved' => 'Diselesaikan',
                'closed' => 'Ditutup',
            ];

            $subject = "Status Review Thread Diubah - {$submission->form->title}";
            $params = [
                'judul' => 'Status Review Thread Diubah',
                'isi' => "
                    <p><strong>{$updater->name}</strong> telah mengubah status review thread:</p>
                    <ul>
                        <li><strong>Form:</strong> {$submission->form->title}</li>
                        <li><strong>Status Lama:</strong> {$statusLabels[$oldStatus]}</li>
                        <li><strong>Status Baru:</strong> {$statusLabels[$reviewSummary->status]}</li>
                        <li><strong>Waktu:</strong> ".now()->format('d F Y H:i')."</li>
                    </ul>
                    <p><a href='".route('admin.submissions.show', $submission->id)."' style='background-color: #00BCD4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Lihat Review</a></p>
                ",
            ];

            $recipients = $this->getReviewParticipants($submission, [$updater->id]);
            $this->sendToMultipleRecipients($recipients, $subject, $params);

            Log::info('Email notifikasi review status changed dikirim', [
                'review_summary_id' => $reviewSummary->id,
                'old_status' => $oldStatus,
                'new_status' => $reviewSummary->status,
            ]);

        } catch (\Exception $e) {
            Log::error('Gagal mengirim email notifikasi review status changed', [
                'review_summary_id' => $reviewSummary->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 7. Notifikasi ketika status submission berubah
     */
    public function notifySubmissionStatusChanged(FormSubmission $submission, $oldStatus)
    {
        try {
            $updater = Auth::user();

            $subject = "Status Submission Diubah - {$submission->form->title}";
            $params = [
                'judul' => 'Status Submission Diubah',
                'isi' => "
                    <p><strong>{$updater->name}</strong> telah mengubah status submission:</p>
                    <ul>
                        <li><strong>Form:</strong> {$submission->form->title}</li>
                        <li><strong>Diajukan oleh:</strong> {$submission->submittedBy->name}</li>
                        <li><strong>Status Lama:</strong> {$oldStatus->label()}</li>
                        <li><strong>Status Baru:</strong> {$submission->status->label()}</li>
                        <li><strong>Waktu:</strong> ".now()->format('d F Y H:i')."</li>
                    </ul>
                    <p><a href='".route('admin.submissions.show', $submission->id)."' style='background-color: #607D8B; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Lihat Submission</a></p>
                ",
            ];

            $recipients = $this->getReviewParticipants($submission, [$updater->id]);
            $this->sendToMultipleRecipients($recipients, $subject, $params);

            Log::info('Email notifikasi submission status changed dikirim', [
                'submission_id' => $submission->id,
                'old_status' => $oldStatus->value,
                'new_status' => $submission->status->value,
            ]);

        } catch (\Exception $e) {
            Log::error('Gagal mengirim email notifikasi submission status changed', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 8. Notifikasi ketika submission period aktif
     */
    public function notifySubmissionPeriodActive($submissionPeriod)
    {
        try {
            // Dapatkan semua user non-admin
            $users = User::whereDoesntHave('roles', function ($query) {
                $query->whereIn('name', ['Super Admin', 'Admin']);
            })->get();

            $subject = "Periode Submission Baru Aktif - {$submissionPeriod->name}";

            $dates = $submissionPeriod->submissionDates->sortBy('datetime');
            $startDate = $dates->first() ? $dates->first()->datetime->format('d F Y') : '-';
            $endDate = $dates->last() ? $dates->last()->datetime->format('d F Y') : '-';

            $params = [
                'judul' => 'Periode Submission Baru Telah Aktif',
                'isi' => "
                    <p>Periode submission baru telah dibuka:</p>
                    <ul>
                        <li><strong>Nama Periode:</strong> {$submissionPeriod->name}</li>
                        <li><strong>Mulai:</strong> {$startDate}</li>
                        <li><strong>Berakhir:</strong> {$endDate}</li>
                    </ul>
                    <p>Silakan submit form Anda sebelum periode berakhir.</p>
                    <p><a href='".route('user.dashboard')."' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Buka Dashboard</a></p>
                ",
            ];

            foreach ($users as $user) {
                Mail::to($user->email)->queue(
                    new DynamicEmailNotification([
                        'subject' => $subject,
                        'view' => 'emails.template',
                        'params' => $params,
                    ])
                );
            }

            Log::info('Email notifikasi submission period active dikirim', [
                'submission_period_id' => $submissionPeriod->id,
                'recipients_count' => $users->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Gagal mengirim email notifikasi submission period active', [
                'submission_period_id' => $submissionPeriod->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Helper: Dapatkan semua partisipan review (admin, reviewers, submitter)
     */
    private function getReviewParticipants(FormSubmission $submission, array $excludeUserIds = [])
    {
        $recipients = collect();

        // Admin
        $admins = User::role(['Super Admin', 'Admin'])
            ->whereNotIn('id', $excludeUserIds)
            ->get();
        $recipients = $recipients->merge($admins);

        // Reviewers
        $reviewers = $submission->submissionReviewers()
            ->with('reviewer.user')
            ->get()
            ->pluck('reviewer.user')
            ->whereNotIn('id', $excludeUserIds);
        $recipients = $recipients->merge($reviewers);

        // Submitter
        if (!in_array($submission->submitted_by, $excludeUserIds)) {
            $recipients->push($submission->submittedBy);
        }

        return $recipients->unique('id');
    }

    /**
     * Helper: Kirim email ke multiple recipients
     */
    private function sendToMultipleRecipients($recipients, string $subject, array $params)
    {
        foreach ($recipients as $recipient) {
            Mail::to($recipient->email)->queue(
                new DynamicEmailNotification([
                    'subject' => $subject,
                    'view' => 'emails.template',
                    'params' => $params,
                ])
            );
        }
    }
}
