<?php

namespace App\Queries\ResearchOutput;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\ResearchOutput\OutputWriteAccessData;
use App\Enums\DeadlineType;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Withdrawn;
use App\Support\PhaseAccessGate;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W146 — satu definisi "boleh menulis luaran", disusun dari penegak yang sudah ada tanpa aturan
 * kedua. Urutan alasannya = urutan penegak di controller: keadaan (BR-RO-01) → siapa (BR-RO-07)
 * → kapan (BR-RO-08). Alasan pertama yang cocok menang.
 *
 * @method static OutputWriteAccessData run(User $user, FormSubmission $submission)
 */
class OutputWriteAccess implements ChangesNoState
{
    use AsAction;

    public const WITHDRAWN = 'Pengajuan ini sudah ditarik. Luarannya tetap tersimpan sebagai arsip baca-saja.';

    public const NOT_APPROVED = 'Luaran baru dapat ditambahkan setelah pengajuan disetujui.';

    public const REVIEWER = 'Reviewer dapat membaca luaran, tapi tidak mengubahnya.';

    public const MEMBER = 'Anda terdaftar sebagai anggota. Hanya ketua dan co-investigator yang dapat mengubah luaran.';

    public const PAST_DEADLINE = 'Batas unggah luaran sudah lewat. Luaran yang sudah ada tetap dapat dibaca. '
        .'Hubungi Operator LPPM untuk meminta perpanjangan.';

    public static function for(User $user, FormSubmission $submission): OutputWriteAccessData
    {
        $detail = OutputUploadDetail::for($submission);
        $reason = self::blockedReason($user, $submission, $detail);
        $override = $detail === null ? null : PhaseAccessGate::activeOverride($submission, $detail);

        return new OutputWriteAccessData(
            canWrite: $reason === null,
            blockedReason: $reason,
            // BR-FE-26 — Batas Akhir di kalender periode induk.
            deadline: $detail === null ? null : $submission->submissionPeriod?->deadlineFor($detail, DeadlineType::Output)?->datetime->toIso8601String(),
            overrideUntil: $override?->expires_at?->toIso8601String(),
        );
    }

    public function handle(User $user, FormSubmission $submission): OutputWriteAccessData
    {
        return self::for($user, $submission);
    }

    private static function blockedReason(User $user, FormSubmission $submission, ?FormPhaseDetail $detail): ?string
    {
        if ($submission->status instanceof Withdrawn) {
            return self::WITHDRAWN;
        }

        if (!$submission->status instanceof Approved) {
            return self::NOT_APPROVED;
        }

        if (!$user->can('manageResearchOutput', $submission)) {
            // W143 — reviewer aktif membaca lewat BR-SM-07; selain dia, yang sampai ke sini anggota tim.
            return $submission->hasAssignedReviewer($user) ? self::REVIEWER : self::MEMBER;
        }

        return PhaseAccessGate::isOpenFor($submission, $detail) ? null : self::PAST_DEADLINE;
    }
}
