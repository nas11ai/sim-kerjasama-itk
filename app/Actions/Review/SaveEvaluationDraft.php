<?php

namespace App\Actions\Review;

use App\Data\Request\EvaluationAnswersInput;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewFormResponse;
use App\Models\User;
use App\Services\Review\EvaluationAnswerWriter;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menyimpan jawaban reviewer selama evaluasinya masih draft (BR-FE-05). Layar memanggilnya
 * juga sebagai simpan otomatis tiap 30 detik, selama ada yang berubah.
 *
 * @method static ReviewFormResponse run(ReviewerFormAssignment $assignment, EvaluationAnswersInput $input, User $reviewer)
 */
class SaveEvaluationDraft
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(ReviewerFormAssignment $assignment, EvaluationAnswersInput $input, User $reviewer): ReviewFormResponse
    {
        return DB::transaction(function () use ($assignment, $input, $reviewer): ReviewFormResponse {
            $response = OpenEvaluationResponse::run($assignment, $reviewer);

            EvaluationAnswerWriter::write($response, $input);

            AuditTrail::record('Draft evaluasi disimpan', $response, $reviewer);

            return $response;
        });
    }
}
