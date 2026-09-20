<?php

namespace App\Actions\FormSubmission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * **Tidak menulis audit (BR-RPT-09).** Pencarian murni — tidak menulis apa pun. Draft yang tidak ditemukan dijawab null, bukan dibuatkan.
 */
class ResolveWizardSubmission implements ChangesNoState
{
    use AsAction;

    /**
     * Baris pengajuan terbaru milik peneliti untuk sebuah Form di sebuah periode —
     * **apa pun statusnya**.
     */
    public function handle(int $formId, int $submissionPeriodId, User $user): ?FormSubmission
    {
        // Sengaja tidak menyaring status — pemanggilnya yang memutuskan: `StartSubmission`
        // menanyakan Policy apakah baris ini boleh dipakai ulang, `UserFormController`
        // membedakan "belum ada baris" (409) dari "ada tapi terkunci" (403) [SM-STATE-04].
        return FormSubmission::query()
            // `parent_submission_id` NULL: laporan monev adalah child submission, bukan
            // urusan wizard pengajuan (BR-MON-03).
            ->whereNull('parent_submission_id')
            ->where('form_id', $formId)
            ->where('submitted_by', $user->id)
            ->where('submission_period_id', $submissionPeriodId)
            ->latest('id')
            ->first();
    }
}
