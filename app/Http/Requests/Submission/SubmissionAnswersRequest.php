<?php

namespace App\Http\Requests\Submission;

use App\Data\Request\SubmissionAnswersInput;
use App\Http\Requests\Concerns\ReadsFieldAnswers;
use App\Http\Requests\Concerns\TargetsWizardStep;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Isian satu langkah wizard pengajuan, untuk simpan draft maupun submit. Berkas dikirim di
 * tempat jawabannya, `responses.*.value`.
 */
class SubmissionAnswersRequest extends FormRequest
{
    use ReadsFieldAnswers;
    use TargetsWizardStep;

    private const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            ...$this->wizardStepRules(),
            'responses' => 'required|array',
            'responses.*.form_field_id' => 'required|exists:form_fields,id',
            'responses.*.value' => 'nullable',
            // BR-FE-14 — simpan dan submit membawa cap waktu yang dilihat penyunting. `nullable`, bukan
            // `required`: wajibnya ditegakkan OptimisticLock **setelah** baris diresolusi, supaya "belum
            // ada baris" tetap terbaca 409 dan tidak tertukar jadi 422 ([SM-STATE-04]).
            'last_updated_at' => 'nullable|date',
        ];
    }

    /**
     * Batas berkas tak bisa ditulis `max:` karena field yang sama memuat teks, dan bagi teks `max`
     * berarti jumlah karakter. Dulu hanya submit yang membatasinya.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->uploadedFiles() as $index => $file) {
                if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
                    $validator->errors()->add("responses.{$index}.value", 'Ukuran file terlalu besar. Maksimal 10MB.');
                }
            }
        }];
    }

    public function toInput(): SubmissionAnswersInput
    {
        $uploads = [];

        foreach ($this->uploadedFiles() as $index => $file) {
            $uploads[$this->integer("responses.{$index}.form_field_id")] = $file;
        }

        return new SubmissionAnswersInput(
            formId: $this->integer('form_id'),
            submissionPeriodId: $this->integer('submission_period_id'),
            answers: $this->fieldAnswers(),
            uploads: $uploads,
            lastUpdatedAt: $this->filled('last_updated_at') ? $this->string('last_updated_at')->toString() : null,
        );
    }

    /** @return array<array-key, UploadedFile> indeks jawaban => berkas */
    private function uploadedFiles(): array
    {
        $files = [];

        foreach (array_keys($this->array('responses')) as $index) {
            $file = $this->file("responses.{$index}.value");

            if ($file instanceof UploadedFile && $file->isValid()) {
                $files[$index] = $file;
            }
        }

        return $files;
    }
}
