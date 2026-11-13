<?php

namespace App\Actions\FormSubmission;

use App\Data\Request\SubmissionAnswersInput;
use App\Data\Request\WizardStepInput;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Satu perpindahan langkah wizard (ADR 0012): simpan jawaban langkah yang ditinggalkan bila ada,
 * lalu catat kemajuannya. Navigasi bebas maju-mundur (BR-SM-17).
 *
 * @method static FormSubmission run(FormSubmission $submission, WizardStepInput $input, User $user)
 */
class SaveWizardStep
{
    use AsAction;

    /** @throws AuthorizationException */
    public function handle(FormSubmission $submission, WizardStepInput $input, User $user): FormSubmission
    {
        // BR-SM-05, BR-SM-10 — hanya ketua, dan hanya selama pengajuan masih boleh disunting.
        if (!$user->can('update', $submission)) {
            throw new AuthorizationException('Pengajuan ini tidak dapat disunting.');
        }

        if ($input->answers->answers !== [] || $input->answers->uploads !== []) {
            $submission = $this->saveAnswers($submission, $input->answers, $user);
        }

        $this->recordProgress($submission, $input, $user);

        return $submission;
    }

    private function saveAnswers(FormSubmission $submission, SubmissionAnswersInput $answers, User $user): FormSubmission
    {
        // SaveSubmissionDraft menulis ke baris yang diresolusi dari form+periode+peneliti. Kalau itu
        // bukan baris di URL, jawaban akan mendarat di pengajuan lain tanpa ada yang tahu.
        $resolved = ResolveWizardSubmission::make()->handle($answers->formId, $answers->submissionPeriodId, $user);

        if ($resolved?->id !== $submission->id) {
            throw new ConflictHttpException('Pengajuan ini bukan draf terbaru Anda untuk formulir dan periode ini.');
        }

        return SaveSubmissionDraft::make()->handle($answers, $user);
    }

    /**
     * Ditulis tanpa menyentuh `updated_at`: cap itu kunci optimistic lock (BR-FE-14), dan menekan
     * Lanjut tanpa mengubah isi tidak boleh membuat tab lain kena 409.
     */
    private function recordProgress(FormSubmission $submission, WizardStepInput $input, User $user): void
    {
        $before = $submission->last_completed_step;

        if ($input->goto <= $input->step || $input->step <= $before) {
            return;
        }

        $submission->last_completed_step = $input->step;
        FormSubmission::withoutTimestamps(fn (): bool => $submission->saveQuietly());

        // BR-RPT-09 — hanya saat kemajuan bertambah, paling banyak sekali per langkah.
        AuditTrail::record('Langkah wizard diselesaikan', $submission, $user, new AuditChange(
            old: ['last_completed_step' => $before],
            new: ['last_completed_step' => $input->step],
        ));
    }
}
