<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Submission\Wizard\WizardBudgetData;
use App\Models\BudgetComponent;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\WizardStepGate;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Langkah 5 wizard (RESEARCHER-02-04). Batasnya `scheme.max_budget` yang sama dengan
 * BudgetLimitGuard (BR-BUD-01); pengajuan tanpa skema tidak dibatasi (BR-SCH-04).
 *
 * @method static WizardBudgetData run(User $viewer, FormSubmission $submission)
 */
class WizardBudget implements ChangesNoState
{
    use AsAction;

    public function handle(User $viewer, FormSubmission $submission): WizardBudgetData
    {
        $submission->loadMissing('scheme');
        $items = SubmissionBudgetItems::run($submission);

        return new WizardBudgetData(
            items: $items,
            total: SubmissionBudgetItems::total($items),
            maxBudget: $submission->scheme?->max_budget,
            components: $this->activeComponents(),
            blockedReason: WizardStepGate::blockedReason($submission, WizardStepGate::STEP_BUDGET),
            editable: $viewer->can('update-budget', $submission),
        );
    }

    /**
     * BR-BUD-05 — hanya komponen aktif yang bisa dipilih untuk item baru.
     *
     * @return list<array{value: string, label: string}>
     */
    private function activeComponents(): array
    {
        return array_values(BudgetComponent::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn (BudgetComponent $c): array => ['value' => (string) $c->id, 'label' => $c->name])
            ->all());
    }
}
