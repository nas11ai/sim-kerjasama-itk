<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Status biodata seorang user, dalam bentuk yang disimpan middleware ke sesi
 * (`biodataStatus`). Reviewer dalam masa tugas dikecualikan (BR-REV-09); yang masa
 * tugasnya belum mulai diperiksa seperti user biasa.
 *
 * @method static array<string, bool|int|string> run(User $user)
 */
class BiodataStatus implements ChangesNoState
{
    use AsAction;

    private const ROLES_REQUIRING_BIODATA = ['Mahasiswa', 'Tenaga Kependidikan'];

    /** @return array<string, bool|int|string> */
    public function handle(User $user): array
    {
        if (!$this->needsBiodata($user)) {
            return ['required' => false, 'completed' => true, 'showAllMenus' => true];
        }

        $form = Form::where('form_type_id', FormType::BIODATA_ID)
            ->where('is_active', true)
            ->first();

        if (!$form) {
            return [
                'required' => false,
                'completed' => true,
                'showAllMenus' => true,
                'error' => true,
                'message' => 'Formulir biodata tidak ditemukan. Silakan hubungi administrator.',
            ];
        }

        // Submission terakhir: biodata yang ditolak tetap ada sebagai riwayat di bawahnya.
        $submission = FormSubmission::where('form_id', $form->id)
            ->where('submitted_by', $user->id)
            ->latest('id')
            ->first();

        return $this->forSubmission($form, $submission);
    }

    /** @return array<string, bool|int|string> */
    private function forSubmission(Form $form, ?FormSubmission $submission): array
    {
        $status = $submission?->status;

        // Status null (tak mungkin pada baris yang sudah dikirim) dianggap belum dikirim.
        if (!$submission || !$submission->is_submitted || $status === null) {
            return [
                'required' => true,
                'completed' => false,
                'showAllMenus' => false,
                'status' => 'not_submitted',
                'message' => 'Silakan lengkapi biodata Anda terlebih dahulu.',
                'form_id' => $form->id,
            ];
        }

        if ($status instanceof Approved) {
            return [
                'required' => true,
                'completed' => true,
                'showAllMenus' => true,
                'status' => 'approved',
                'submission_id' => $submission->id,
            ];
        }

        return [
            'required' => true,
            'completed' => false,
            'showAllMenus' => false,
            'status' => $status->key(),
            'message' => $this->messageFor($status),
            'submission_id' => $submission->id,
            'form_id' => $form->id,
            'can_edit' => $status instanceof Rejected || $status instanceof NeedsRevision,
        ];
    }

    private function needsBiodata(User $user): bool
    {
        if ($user->is_reviewer) {
            return false;
        }

        return $user->hasAnyRole(self::ROLES_REQUIRING_BIODATA);
    }

    private function messageFor(SubmissionStatus $status): string
    {
        return match (true) {
            $status instanceof Submitted => 'Biodata Anda telah dikirim dan sedang menunggu proses review.',
            $status instanceof UnderReview => 'Biodata Anda sedang dalam proses review.',
            $status instanceof Rejected => 'Biodata Anda ditolak. Silakan perbaiki dan kirim ulang.',
            $status instanceof NeedsRevision => 'Biodata Anda memerlukan revisi. Silakan perbaiki.',
            default => 'Biodata Anda belum disetujui.',
        };
    }
}
