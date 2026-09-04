<?php

use App\Actions\Budget\SaveBudgetLineItem;
use App\Models\BudgetComponent;
use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Draft;
use Illuminate\Validation\ValidationException;

/** @return array{0: User, 1: FormSubmission} */
function plainSubmission(): array
{
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => Draft::class,
        'scheme_id' => null,
    ]);

    return [$owner, $submission];
}

it('[BUD-ITEM-01] menolak volume atau harga satuan yang tidak positif', function (int $volume, int $unitPrice) {
    [$owner, $submission] = plainSubmission();
    $component = BudgetComponent::factory()->create(['is_active' => true]);

    expect(fn () => SaveBudgetLineItem::run($submission, $owner, [
        'budget_component_id' => $component->id,
        'item_name' => 'Reagen analisis',
        'volume' => $volume,
        'unit' => 'paket',
        'unit_price' => $unitPrice,
    ]))->toThrow(ValidationException::class);
})->with([[0, 1_000_000], [-1, 1_000_000], [2, 0], [2, -5_000]]);

it('[BUD-ITEM-02] menyimpan line item sah dan menghitung totalnya', function () {
    [$owner, $submission] = plainSubmission();
    $component = BudgetComponent::factory()->create(['is_active' => true]);

    $item = SaveBudgetLineItem::run($submission, $owner, [
        'budget_component_id' => $component->id,
        'item_name' => 'Sensor kualitas air',
        'volume' => 4,
        'unit' => 'unit',
        'unit_price' => 1_250_000,
    ]);

    expect($item->total)->toBe(5_000_000)
        ->and((int) $submission->fresh()->budgetLineItems()->sum('total'))->toBe(5_000_000);
});

it('[BUD-ITEM-03] menolak komponen anggaran yang tidak aktif', function () {
    [$owner, $submission] = plainSubmission();
    $component = BudgetComponent::factory()->create(['is_active' => false]);

    expect(fn () => SaveBudgetLineItem::run($submission, $owner, [
        'budget_component_id' => $component->id,
        'item_name' => 'Perjalanan dinas',
        'volume' => 1,
        'unit' => 'paket',
        'unit_price' => 1_000_000,
    ]))->toThrow(ValidationException::class);
});

it('[BUD-ITEM-04] membiarkan item lama utuh saat komponennya dinonaktifkan', function () {
    [$owner, $submission] = plainSubmission();
    $component = BudgetComponent::factory()->create(['is_active' => true]);
    $item = SaveBudgetLineItem::run($submission, $owner, [
        'budget_component_id' => $component->id,
        'item_name' => 'Perjalanan dinas',
        'volume' => 1,
        'unit' => 'paket',
        'unit_price' => 1_000_000,
    ]);

    $component->update(['is_active' => false]);

    expect(BudgetLineItem::find($item->id))->not->toBeNull()
        ->and((int) $submission->fresh()->budgetLineItems()->sum('total'))->toBe(1_000_000);
});
