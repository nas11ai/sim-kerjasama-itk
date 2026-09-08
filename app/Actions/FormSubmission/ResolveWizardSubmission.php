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
     *
     * Sengaja tidak menyaring status. Pemanggilnya yang memutuskan: `StartSubmission`
     * menanyakan Policy untuk tahu apakah baris ini masih boleh dipakai ulang, dan
     * `UserFormController` membedakan "belum ada baris" (409) dari "ada tapi terkunci"
     * (403) — pembedaan yang dituntut [SM-STATE-04].
     *
     * `parent_submission_id` NULL: laporan monev adalah child submission dan bukan
     * urusan wizard pengajuan (BR-MON-03).
     */
    public function handle(int $formId, int $submissionPeriodId, User $user): ?FormSubmission
    {
        return FormSubmission::query()
            ->whereNull('parent_submission_id')
            ->where('form_id', $formId)
            ->where('submitted_by', $user->id)
            ->where('submission_period_id', $submissionPeriodId)
            ->latest('id')
            ->first();
    }
}
