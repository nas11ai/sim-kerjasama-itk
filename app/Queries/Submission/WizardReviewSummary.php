<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Submission\Wizard\WizardMissingFieldData;
use App\Data\View\Submission\Wizard\WizardReviewData;
use App\Models\BudgetLineItem;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\AccountStateGuard;
use App\Support\RequiredFieldGuard;
use App\Support\WizardStepGate;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Langkah 7 wizard: isi pengajuan dan penghalang submit yang sama dengan yang diperiksa
 * SubmitFormSubmission, supaya peneliti melihatnya sebelum menekan Submit [SM-WIZARD-06].
 *
 * @method static WizardReviewData run(User $viewer, FormSubmission $submission)
 */
class WizardReviewSummary implements ChangesNoState
{
    use AsAction;

    public function handle(User $viewer, FormSubmission $submission): WizardReviewData
    {
        $submission->loadMissing(['scheme:id,name,max_budget', 'trl']);
        $trl = $submission->trl;
        $items = $submission->budgetLineItems()->get(['id', 'total']);

        return new WizardReviewData(
            scheme: $submission->scheme?->name,
            trl: $trl === null ? null : "TRL {$trl->level} — {$trl->name}",
            // Ketua ikut dihitung, sama dengan daftar anggota di layar detail.
            memberCount: 1 + $submission->researchMembers()->where('status', 'active')->count(),
            targetOutputCount: $submission->targetOutputs()->count(),
            budgetTotal: array_sum(array_map(fn (BudgetLineItem $item): int => $item->total, $items->all())),
            maxBudget: $submission->scheme?->max_budget,
            fileNames: $this->fileNames($submission),
            missingFields: $this->missingFields($submission),
            // BR-IAM-04, BR-IAM-13 — pesan yang sama dengan penolakan submit.
            accountBlock: AccountStateGuard::submitBlockReason($viewer),
        );
    }

    /** @return list<string> */
    private function fileNames(FormSubmission $submission): array
    {
        $values = SubmissionAnswers::run($submission);
        $names = [];

        foreach ($submission->form->formFields as $field) {
            $value = $values[$field->id] ?? null;

            if ($field->fieldType?->name === 'file' && $value !== null && $value !== '') {
                $names[] = basename($value);
            }
        }

        return $names;
    }

    /** @return list<WizardMissingFieldData> */
    private function missingFields(FormSubmission $submission): array
    {
        return array_values(RequiredFieldGuard::missingFields($submission)
            ->map(fn (FormField $field): WizardMissingFieldData => new WizardMissingFieldData(
                fieldId: $field->id,
                label: $field->label,
                message: RequiredFieldGuard::message($field),
                step: WizardStepGate::stepForFieldType($field->fieldType->name ?? ''),
            ))
            ->all());
    }
}
