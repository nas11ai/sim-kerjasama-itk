<?php

use App\Models\BudgetComponent;
use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use App\Models\User;

test('budget line item bisa dibuat via factory dengan total konsisten', function () {
    $item = BudgetLineItem::factory()->create([
        'volume' => 5,
        'unit_price' => 100_000,
        'total' => 500_000,
    ]);

    $this->assertDatabaseHas('budget_line_items', [
        'id' => $item->id,
        'volume' => 5,
        'unit_price' => 500_000 / 5,
        'total' => 500_000,
    ]);

    expect($item->volume)->toBeInt()
        ->and($item->unit_price)->toBeInt()
        ->and($item->total)->toBe($item->volume * $item->unit_price);
});

test('relasi budget line item ke submission dan component tersambung', function () {
    $item = BudgetLineItem::factory()->create();

    expect($item->formSubmission)->toBeInstanceOf(FormSubmission::class)
        ->and($item->budgetComponent)->toBeInstanceOf(BudgetComponent::class);
});

test('grand total submission dihitung on-the-fly dari sum line items', function () {
    $submission = FormSubmission::factory()->state(['submitted_by' => User::factory()])->create();

    BudgetLineItem::factory()->for($submission)->create(['volume' => 2, 'unit_price' => 150_000, 'total' => 300_000]);
    BudgetLineItem::factory()->for($submission)->create(['volume' => 1, 'unit_price' => 200_000, 'total' => 200_000]);

    // PostgreSQL returns SUM over bigint as a string, so grand-total consumers cast to int.
    expect($submission->budgetLineItems)->toHaveCount(2)
        ->and((int) $submission->budgetLineItems()->sum('total'))->toBe(500_000);
});

test('budget component is_active di-cast boolean dan mendukung state inactive', function () {
    $active = BudgetComponent::factory()->create();
    $inactive = BudgetComponent::factory()->inactive()->create();

    expect($active->is_active)->toBeTrue()
        ->and($inactive->is_active)->toBeFalse();
});

test('budget line item mendukung soft delete', function () {
    $item = BudgetLineItem::factory()->create();

    $item->delete();

    $this->assertSoftDeleted($item);
    expect(BudgetLineItem::count())->toBe(0)
        ->and(BudgetLineItem::withTrashed()->count())->toBe(1);
});
