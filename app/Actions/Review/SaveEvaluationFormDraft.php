<?php

namespace App\Actions\Review;

use App\Data\Request\EvaluationDraftInput;
use App\Data\Request\ReviewFormFieldInput;
use App\Models\ConfigurationDraft;
use App\Models\ReviewEvaluationForm;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-31 — menyimpan isi Form Evaluasi sebagai draf (satu per form, ditimpa tiap simpan).
 * Draf tidak berlaku bagi reviewer; mereka tetap memakai versi terbit sampai Terbitkan.
 *
 * @method static ConfigurationDraft run(ReviewEvaluationForm $form, EvaluationDraftInput $input, User $actor)
 */
class SaveEvaluationFormDraft
{
    use AsAction;

    public function handle(ReviewEvaluationForm $form, EvaluationDraftInput $input, User $actor): ConfigurationDraft
    {
        $draft = ConfigurationDraft::query()->updateOrCreate(
            ['draftable_type' => ConfigurationDraft::EVALUATION_FORM, 'draftable_id' => $form->id],
            ['payload' => $input->toPayload(), 'updated_by' => $actor->id],
        );
        $form->unsetRelation('draft');

        AuditTrail::record('Draf form evaluasi disimpan', $form, $actor, new AuditChange(new: [
            'fields' => array_map(fn (ReviewFormFieldInput $field): string => $field->label, $input->fields),
        ]));

        return $draft;
    }
}
