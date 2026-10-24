<?php

namespace App\Actions\Review;

use App\Data\Request\ReviewEvaluationFormInput;
use App\Models\ReviewEvaluationForm;
use App\Models\User;
use App\Services\Form\ReviewFormFieldWriter;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menyunting form evaluasi dari layar Ubah Form Evaluasi.
 *
 * @method static void run(ReviewEvaluationForm $form, ReviewEvaluationFormInput $input, User $actor)
 */
class ReviseReviewEvaluationForm
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(ReviewEvaluationForm $form, ReviewEvaluationFormInput $input, User $actor): void
    {
        AssertReviewedPhaseDetail::run($input->formPhaseDetailId);
        $this->assertStaysWithItsAssignments($form, $input->formPhaseDetailId);

        DB::transaction(function () use ($form, $input, $actor): void {
            $old = $form->auditSnapshot();
            $form->update($input->attributes());

            if ($input->fields !== null) {
                ReviewFormFieldWriter::sync($form, $input->fields);
            }

            AuditTrail::record('Form evaluasi diubah', $form, $actor, new AuditChange($old, $form->auditSnapshot()));
        });
    }

    /**
     * Sejalan dengan BR-FE-22: penugasan yang ada menilai pengajuan di detail lamanya.
     * Memindahkan form membuat detail itu kehilangan form wajibnya, sementara penugasannya
     * tetap menunjuk form yang kini milik detail lain.
     *
     * @throws ValidationException
     */
    private function assertStaysWithItsAssignments(ReviewEvaluationForm $form, int $formPhaseDetailId): void
    {
        if ($form->form_phase_detail_id === $formPhaseDetailId) {
            return;
        }

        $assignments = $form->reviewerFormAssignments()->count();

        if ($assignments === 0) {
            return;
        }

        throw ValidationException::withMessages([
            'form_phase_detail_id' => "Form evaluasi ini sudah ditugaskan {$assignments} kali, jadi tidak dapat dipindah ke detail tahap lain. Duplikasi form lalu pasang salinannya.",
        ]);
    }
}
