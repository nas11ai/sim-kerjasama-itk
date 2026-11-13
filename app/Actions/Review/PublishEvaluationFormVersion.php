<?php

namespace App\Actions\Review;

use App\Data\Request\ReviewFormFieldInput;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewEvaluationFormVersion;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-18 (K1) — menerbitkan isi Formulir Evaluasi sebagai versi berikutnya: field dan opsinya disalin
 * sebagai baris baru (id masukan diabaikan), jadi versi lama dan jawaban yang menunjuknya utuh.
 *
 * @method static ReviewEvaluationFormVersion run(ReviewEvaluationForm $form, list<ReviewFormFieldInput> $fields, User $actor)
 */
class PublishEvaluationFormVersion
{
    use AsAction;

    /**
     * @param  list<ReviewFormFieldInput>  $fields
     *
     * @throws ValidationException
     */
    public function handle(ReviewEvaluationForm $form, array $fields, User $actor): ReviewEvaluationFormVersion
    {
        if ($fields === []) {
            throw ValidationException::withMessages(['fields' => 'Form evaluasi butuh minimal satu field.']);
        }

        return DB::transaction(function () use ($form, $fields, $actor): ReviewEvaluationFormVersion {
            // Kunci baris form: dua terbit bersamaan tidak boleh berebut nomor yang sama.
            ReviewEvaluationForm::query()->whereKey($form->id)->lockForUpdate()->first();

            $version = $form->versions()->create([
                'number' => ($form->latestVersion()->first()->number ?? 0) + 1,
                'published_by' => $actor->id,
                'published_at' => now(),
            ]);

            foreach ($fields as $index => $input) {
                $this->copyField($version, $input, $index + 1);
            }

            $form->unsetRelation('latestVersion');
            AuditTrail::record('Form evaluasi diterbitkan', $form, $actor, new AuditChange(new: [
                'number' => $version->number,
                'fields' => array_map(fn (ReviewFormFieldInput $field): string => $field->label, $fields),
            ]));

            return $version;
        });
    }

    private function copyField(ReviewEvaluationFormVersion $version, ReviewFormFieldInput $input, int $order): void
    {
        $field = $version->reviewFormFields()->create([
            'field_type_id' => $input->fieldTypeId,
            'label' => $input->label,
            'description' => $input->description,
            'is_required' => $input->isRequired,
            'order' => $order,
            'validation_rules' => $input->validationRules,
        ]);

        foreach ($input->options ?? [] as $o => $option) {
            $field->reviewFormFieldOptions()->create([
                'label' => $option['label'],
                'value' => $option['value'],
                'order' => $o + 1,
            ]);
        }
    }
}
