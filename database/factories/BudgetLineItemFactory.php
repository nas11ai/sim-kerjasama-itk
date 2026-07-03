<?php

namespace Database\Factories;

use App\Models\BudgetComponent;
use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetLineItem>
 */
class BudgetLineItemFactory extends Factory
{
    protected $model = BudgetLineItem::class;

    public function definition(): array
    {
        $volume = fake()->numberBetween(1, 100);
        $unitPrice = fake()->numberBetween(10_000, 5_000_000);

        return [
            'form_submission_id' => FormSubmission::factory()->state([
                'submitted_by' => User::factory(),
            ]),
            'budget_component_id' => BudgetComponent::factory(),
            'item_name' => fake()->words(3, true),
            'volume' => $volume,
            'unit' => fake()->randomElement(['unit', 'paket', 'orang', 'hari', 'bulan']),
            'unit_price' => $unitPrice,
            'total' => $volume * $unitPrice,
        ];
    }
}
