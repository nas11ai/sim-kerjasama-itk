<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Submission\BudgetItemData;
use App\Data\View\Submission\SubmissionBudgetData;
use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use App\Models\User;
use App\Policies\BudgetLineItemPolicy;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Rencana anggaran beserta jawaban `update-budget` untuk penontonnya. Alasan terkunci
 * dipinjam dari Policy supaya tidak ada salinan kedua daftar status terminal.
 *
 * @method static SubmissionBudgetData run(User $viewer, FormSubmission $submission)
 */
class SubmissionBudgetSummary implements ChangesNoState
{
    use AsAction;

    public function handle(User $viewer, FormSubmission $submission): SubmissionBudgetData
    {
        $items = array_values($submission->budgetLineItems()
            ->with('budgetComponent:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (BudgetLineItem $line): BudgetItemData => new BudgetItemData(
                component: $line->budgetComponent->name,
                item: $line->item_name,
                volume: $line->volume,
                unit: $line->unit,
                unitPrice: $line->unit_price,
                total: $line->total,
            ))
            ->all());
        $editable = $viewer->can('update-budget', $submission);

        return new SubmissionBudgetData(
            items: $items,
            total: array_sum(array_map(fn (BudgetItemData $item): int => $item->total, $items)),
            editable: $editable,
            lockReason: $this->lockReason($submission, $editable),
        );
    }

    private function lockReason(FormSubmission $submission, bool $editable): ?string
    {
        if ($editable) {
            return null;
        }

        // BR-BUD-02 mengunci bagi siapa pun; selain itu BR-BUD-09 — perannya hanya membaca.
        return BudgetLineItemPolicy::lockedByState($submission) ? 'terminal' : 'read_only';
    }
}
