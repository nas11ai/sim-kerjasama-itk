<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\ScoreVisibility;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-11 dan BR-REV-12 — hasil evaluasi yang boleh dibaca pemanggil. Dua sumbu yang sengaja
 * tidak dicampur: rekan reviewer internal setelah semua evaluasi selesai, peneliti setelah
 * keputusan final. Yang tidak lolos menerima daftar kosong beserta alasannya.
 *
 * @phpstan-import-type Score from SubmittedEvaluationSheets
 *
 * @method static array{visible: bool, reason: string|null, scores: list<Score>} run(User $user, FormSubmission $submission)
 */
class VisibleReviewerScores implements ChangesNoState
{
    use AsAction;

    /** @return array{visible: bool, reason: string|null, scores: list<Score>} */
    public function handle(User $user, FormSubmission $submission): array
    {
        $asPeer = ScoreVisibility::peerScoresVisibleTo($user, $submission);
        $asOwner = ScoreVisibility::visibleToResearcher($user, $submission);

        if (!$asPeer && !$asOwner && !$user->can('submissions.view-all')) {
            return ['visible' => false, 'reason' => $this->hiddenReason($user, $submission), 'scores' => []];
        }

        return ['visible' => true, 'reason' => null, 'scores' => SubmittedEvaluationSheets::run($submission, false)];
    }

    private function hiddenReason(User $user, FormSubmission $submission): string
    {
        if ($submission->submitted_by === $user->id) {
            // BR-REV-12 — bukan "review selesai", melainkan keputusan final.
            return 'Hasil review terbuka setelah keputusan akhir atas pengajuan ini diambil.';
        }

        return 'Skor reviewer lain terbuka bagi reviewer internal setelah seluruh evaluasi selesai.';
    }
}
