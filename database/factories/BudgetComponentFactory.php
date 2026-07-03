<?php

namespace Database\Factories;

use App\Models\BudgetComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetComponent>
 */
class BudgetComponentFactory extends Factory
{
    protected $model = BudgetComponent::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
