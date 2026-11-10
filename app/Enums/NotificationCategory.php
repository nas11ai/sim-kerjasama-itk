<?php

namespace App\Enums;

use App\Notifications\AccountDeactivatedNotification;
use App\Notifications\AccountReactivatedNotification;
use App\Notifications\ExportCompletedNotification;
use App\Notifications\ExportFailedNotification;
use App\Notifications\FormChangedNotification;
use App\Notifications\MonevEvaluationSubmittedNotification;
use App\Notifications\MonevStageOpenedNotification;
use App\Notifications\NewRegistrationAwaitingVerification;
use App\Notifications\NidnVerificationRejectedNotification;
use App\Notifications\NidnVerifiedNotification;
use App\Notifications\PhaseOverrideGrantedNotification;
use App\Notifications\ProposalApprovedNotification;
use App\Notifications\ProposalRejectedNotification;
use App\Notifications\ProposalSubmittedNotification;
use App\Notifications\RecheckRequestedNotification;
use App\Notifications\ReviewCommentAddedNotification;
use App\Notifications\ReviewerAppointedNotification;
use App\Notifications\ReviewerAssignedNotification;
use App\Notifications\ReviewThreadOpenedNotification;
use App\Notifications\RevisionRequestedNotification;
use App\Notifications\RevisionResolvedNotification;
use App\Notifications\SubmissionDeadlineExtendedNotification;
use App\Notifications\SubmissionPeriodActiveNotification;
use App\Notifications\SubmissionPeriodClosedNotification;
use App\Notifications\SubmissionReadyForDecisionNotification;
use InvalidArgumentException;

/**
 * W40 — kategori notifikasi untuk chip filter layar SHARED-02-05. Dipetakan per
 * kelas (kolom `notifications.type`), sehingga penyaringan cukup `whereIn`.
 */
enum NotificationCategory: string
{
    case Review = 'review';
    case Monev = 'monev';
    case Submission = 'submission';
    case Period = 'period';
    case Account = 'account';
    case Export = 'export';

    public function label(): string
    {
        return match ($this) {
            self::Review => 'Review',
            self::Monev => 'Monev',
            self::Submission => 'Pengajuan',
            self::Period => 'Periode',
            self::Account => 'Akun',
            self::Export => 'Ekspor',
        };
    }

    /** @return list<class-string> */
    public function classes(): array
    {
        return match ($this) {
            self::Review => [
                ReviewerAssignedNotification::class,
                ReviewThreadOpenedNotification::class,
                ReviewCommentAddedNotification::class,
                RevisionResolvedNotification::class,
                ReviewerAppointedNotification::class,
                RevisionRequestedNotification::class,
                RecheckRequestedNotification::class,
            ],
            self::Monev => [MonevStageOpenedNotification::class, MonevEvaluationSubmittedNotification::class],
            self::Submission => [
                ProposalSubmittedNotification::class,
                PhaseOverrideGrantedNotification::class,
                FormChangedNotification::class,
                SubmissionReadyForDecisionNotification::class,
                ProposalApprovedNotification::class,
                ProposalRejectedNotification::class,
            ],
            self::Period => [
                SubmissionPeriodActiveNotification::class,
                SubmissionPeriodClosedNotification::class,
                SubmissionDeadlineExtendedNotification::class,
            ],
            self::Account => [
                NidnVerifiedNotification::class,
                NidnVerificationRejectedNotification::class,
                AccountDeactivatedNotification::class,
                AccountReactivatedNotification::class,
                NewRegistrationAwaitingVerification::class,
            ],
            self::Export => [ExportCompletedNotification::class, ExportFailedNotification::class],
        };
    }

    public static function forClass(string $class): self
    {
        return self::tryForClass($class)
            ?? throw new InvalidArgumentException("Kelas notifikasi {$class} belum punya kategori (W40).");
    }

    /** Baris lama bisa memuat kelas yang sudah dihapus; pembacanya tidak boleh ikut gagal. */
    public static function tryForClass(string $class): ?self
    {
        foreach (self::cases() as $category) {
            if (in_array($class, $category->classes(), true)) {
                return $category;
            }
        }

        return null;
    }
}
