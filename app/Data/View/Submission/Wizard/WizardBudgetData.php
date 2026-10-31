<?php

namespace App\Data\View\Submission\Wizard;

use App\Data\View\Submission\BudgetItemData;
use Spatie\LaravelData\Data;

/**
 * Langkah 5 wizard. `components` hanya yang aktif (BR-BUD-05); `blockedReason` dari
 * WizardStepGate (BR-SM-17); `maxBudget` null berarti tanpa batas (BR-SCH-04).
 */
final class WizardBudgetData extends Data
{
    /**
     * @param  list<BudgetItemData>  $items
     * @param  list<array{value: string, label: string}>  $components
     */
    public function __construct(
        public array $items,
        public int $total,
        public ?int $maxBudget,
        public array $components,
        public ?string $blockedReason,
        public bool $editable,
    ) {}
}
