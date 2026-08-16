<?php

use App\Models\BudgetComponent;
use App\Models\FieldType;
use Database\Seeders\BudgetComponentSeeder;
use Database\Seeders\FieldTypeSeeder;

it('seeds the scheme and trl selector field types', function () {
    $this->seed(FieldTypeSeeder::class);

    expect(FieldType::where('name', 'scheme_selector')->exists())->toBeTrue()
        ->and(FieldType::where('name', 'trl_selector')->exists())->toBeTrue();
});

it('seeds seven budget components', function () {
    $this->seed(BudgetComponentSeeder::class);

    expect(BudgetComponent::count())->toBe(7)
        ->and(BudgetComponent::where('name', 'Bahan habis pakai')->exists())->toBeTrue();
});

it('is idempotent', function () {
    $this->seed(BudgetComponentSeeder::class);
    $this->seed(BudgetComponentSeeder::class);

    expect(BudgetComponent::count())->toBe(7);
});
