<?php

namespace App\Actions\Review;

use App\Actions\Monev\NotifyMonevEvaluationSubmitted;
use App\Data\Request\EvaluationAnswersInput;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewFormResponse;
use App\Models\User;
use App\Queries\Review\EvaluationAnswerErrors;
use App\Services\Review\EvaluationAnswerWriter;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-08 — jawaban terakhir disimpan lalu dikirim dalam satu transaksi, jadi kiriman yang
 * ditolak tidak meninggalkan apa pun. Kelengkapan dinilai dari jawaban yang dikirim, bukan dari
 * draft terakhir: layar mengaktifkan tombol kirim dari isian di peramban.
 *
 * @method static ReviewFormResponse run(ReviewerFormAssignment $assignment, EvaluationAnswersInput $input, User $reviewer)
 */
class SubmitEvaluationAnswers
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(ReviewerFormAssignment $assignment, EvaluationAnswersInput $input, User $reviewer): ReviewFormResponse
    {
        $response = DB::transaction(function () use ($assignment, $input, $reviewer): ReviewFormResponse {
            $response = OpenEvaluationResponse::run($assignment, $reviewer);

            EvaluationAnswerWriter::write($response, $input);
            AuditTrail::record('Jawaban akhir evaluasi disimpan', $response, $reviewer);

            $errors = EvaluationAnswerErrors::run($response);

            if ($errors !== []) {
                throw ValidationException::withMessages(['responses' => $errors]);
            }

            // Perpindahan statusnya fakta tersendiri; SubmitEvaluationForm yang mencatatnya.
            if (!SubmitEvaluationForm::run($response)) {
                throw ValidationException::withMessages([
                    'error' => 'Formulir tidak dapat dikirim. Harap lengkapi semua bidang yang diperlukan.',
                ]);
            }

            return $response;
        });

        // BR-MON-10 — untuk laporan monev, hasilnya langsung terbuka bagi peneliti (BR-MON-09),
        // jadi ia diberi tahu. Pengajuan awal sengaja tidak: skornya masih ditahan sampai
        // keputusan akhir, dan mengabarkannya hanya mengundang orang membuka halaman kosong.
        NotifyMonevEvaluationSubmitted::run($response->refresh());

        return $response;
    }
}
