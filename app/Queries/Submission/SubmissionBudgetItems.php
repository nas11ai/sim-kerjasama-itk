<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Submission\BudgetItemData;
use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Baris rencana anggaran untuk layar detail dan langkah 5 wizard. Item lama tetap tampil
 * walau komponennya kini nonaktif (BR-BUD-05).
 *
 * @method static list<BudgetItemData> run(FormSubmission $submission)
 */
class SubmissionBudgetItems implements ChangesNoState
{
    use AsAction;

    /** @return list<BudgetItemData> */
    public function handle(FormSubmission $submission): array
    {
        return array_values($submission->budgetLineItems()
            ->with('budgetComponent:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (BudgetLineItem $line): BudgetItemData => new BudgetItemData(
                id: $line->id,
                componentId: $line->budget_component_id,
                component: $line->budgetComponent->name,
                item: $line->item_name,
                volume: $line->volume,
                unit: $line->unit,
                unitPrice: $line->unit_price,
                total: $line->total,
            ))
            ->all());
    }

    /** @param  list<BudgetItemData>  $items */
    public static function total(array $items): int
    {
        return array_sum(array_map(fn (BudgetItemData $item): int => $item->total, $items));
    }
}
