<?php

use App\Actions\Budget\SaveBudgetLineItem;
use App\Models\BudgetComponent;
use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\User;
use App\States\Submission\Draft;
use Illuminate\Validation\ValidationException;

/** @return array{0: User, 1: FormSubmission, 2: BudgetComponent} */
function budgetFixture(int $maxBudget): array
{
    $owner = User::factory()->create();
    $scheme = Scheme::factory()->create(['max_budget' => $maxBudget]);
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => Draft::class,
        'scheme_id' => $scheme->id,
    ]);
    $component = BudgetComponent::factory()->create(['is_active' => true]);

    return [$owner, $submission, $component];
}

/** @return array<string, mixed> */
function lineItemAttributes(BudgetComponent $component, int $volume, int $unitPrice): array
{
    return [
        'budget_component_id' => $component->id,
        'item_name' => 'Sensor kualitas air',
        'volume' => $volume,
        'unit' => 'unit',
        'unit_price' => $unitPrice,
    ];
}

it('[BUD-LIMIT-01] menolak total yang melebihi batas skema', function () {
    [$owner, $submission, $component] = budgetFixture(50_000_000);
    BudgetLineItem::factory()->create([
        'form_submission_id' => $submission->id,
        'budget_component_id' => $component->id,
        'volume' => 1,
        'unit_price' => 48_000_000,
        'total' => 48_000_000,
    ]);

    expect(fn () => SaveBudgetLineItem::run(
        $submission->fresh(), $owner, lineItemAttributes($component, 1, 5_000_000)
    ))->toThrow(ValidationException::class);
});

it('[BUD-LIMIT-02] menerima total yang tepat di batas', function () {
    [$owner, $submission, $component] = budgetFixture(50_000_000);
    BudgetLineItem::factory()->create([
        'form_submission_id' => $submission->id,
        'budget_component_id' => $component->id,
        'volume' => 1,
        'unit_price' => 45_000_000,
        'total' => 45_000_000,
    ]);

    SaveBudgetLineItem::run(
        $submission->fresh(), $owner, lineItemAttributes($component, 1, 5_000_000)
    );

    expect((int) $submission->fresh()->budgetLineItems()->sum('total'))->toBe(50_000_000);
});

it('[BUD-LIMIT-03] tidak membatasi submission tanpa skema', function () {
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => Draft::class,
        'scheme_id' => null,
    ]);
    $component = BudgetComponent::factory()->create(['is_active' => true]);

    SaveBudgetLineItem::run($submission, $owner, lineItemAttributes($component, 1, 90_000_000));

    expect((int) $submission->fresh()->budgetLineItems()->sum('total'))->toBe(90_000_000);
});
