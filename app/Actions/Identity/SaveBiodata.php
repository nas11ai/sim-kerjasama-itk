<?php

namespace App\Actions\Identity;

use App\Actions\Form\AssertFormAccessible;
use App\Data\Request\BiodataInput;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\Submission\FieldAnswerWriter;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\Resubmitted;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\Submitted;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menyimpan biodata seorang user, sebagai draft atau dikirim final. Menyimpan ulang menimpa
 * seluruh jawaban submission terakhirnya, kecuali yang terakhir Rejected: itu final, jadi
 * biodata berikutnya submission baru dan yang ditolak tinggal sebagai riwayat.
 *
 * @method static FormSubmission run(User $user, BiodataInput $input)
 */
class SaveBiodata
{
    use AsAction;

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, BiodataInput $input): FormSubmission
    {
        $form = Form::with('formFields.fieldType')->findOrFail($input->formId);

        AssertFormAccessible::run($user, $form, 'Anda tidak memiliki akses ke form biodata ini.');

        if ($input->isSubmitted) {
            $this->guardRequiredFields($form, $input);
        }

        return DB::transaction(function () use ($user, $form, $input): FormSubmission {
            $submission = $this->currentOrFresh($form, $user);
            $submission->fill([
                'is_submitted' => $input->isSubmitted,
                'submitted_at' => $input->isSubmitted ? now() : null,
            ])->save();

            if ($input->isSubmitted) {
                $this->markSubmitted($submission);
            }

            FieldAnswerWriter::replace($submission, $input->responses, $input->uploads, 'biodata-uploads');

            AuditTrail::record($input->isSubmitted ? 'Biodata dikirim' : 'Biodata disimpan', $submission, $user);

            return $submission;
        });
    }

    private function currentOrFresh(Form $form, User $user): FormSubmission
    {
        $latest = FormSubmission::where('form_id', $form->id)
            ->where('submitted_by', $user->id)
            ->latest('id')
            ->first();

        if ($latest && !$latest->status instanceof Rejected) {
            return $latest;
        }

        return new FormSubmission(['form_id' => $form->id, 'submitted_by' => $user->id]);
    }

    /**
     * BR-SM-03 — status berpindah lewat transitionTo(). Draft dikirim jadi Submitted,
     * NeedsRevision dikirim ulang jadi Resubmitted; yang sudah terkirim tidak ditransisikan lagi.
     *
     * @throws ValidationException
     */
    private function markSubmitted(FormSubmission $submission): void
    {
        $status = $submission->status;

        if ($status instanceof Submitted || $status instanceof Resubmitted) {
            return;
        }

        $target = $status instanceof NeedsRevision ? Resubmitted::class : Submitted::class;

        if (!$status instanceof SubmissionStatus || !$status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'is_submitted' => 'Biodata dengan status ini tidak bisa dikirim ulang.',
            ]);
        }

        $status->transitionTo($target);
    }

    /** @throws ValidationException */
    private function guardRequiredFields(Form $form, BiodataInput $input): void
    {
        foreach ($form->formFields->where('is_required', true) as $field) {
            if ($this->isAnswered($field, $input)) {
                continue;
            }

            throw ValidationException::withMessages([
                'field_'.$field->id => "Field '{$field->label}' wajib diisi.",
            ]);
        }
    }

    /** Field berkas terisi oleh unggahan baru atau path berkas yang tersimpan (bukan URL). */
    private function isAnswered(FormField $field, BiodataInput $input): bool
    {
        $value = $input->responses[$field->id] ?? null;

        if ($field->fieldType?->name !== 'file') {
            return !blank($value);
        }

        return isset($input->uploads[$field->id])
            || ($value !== null && $value !== '0' && filter_var($value, FILTER_VALIDATE_URL) === false);
    }
}
