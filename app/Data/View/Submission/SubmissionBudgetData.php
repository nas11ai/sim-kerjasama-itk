<?php

namespace App\Data\View\Submission;

use Spatie\LaravelData\Data;

/**
 * Rencana anggaran di layar detail. `lockReason` membedakan BR-BUD-02 (`terminal`, terkunci
 * bagi siapa pun) dari BR-BUD-09 (`read_only`, peran penonton tidak boleh menyunting).
 */
final class SubmissionBudgetData extends Data
{
    /** @param  list<BudgetItemData>  $items */
    public function __construct(
        public array $items,
        public int $total,
        public bool $editable,
        public ?string $lockReason,
    ) {}
}
