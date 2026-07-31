<?php

namespace Database\Factories;

use App\Models\Scheme;
use App\Models\SchemeType;
use App\Models\SubmissionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scheme>
 */
class SchemeFactory extends Factory
{
    protected $model = Scheme::class;

    public function definition(): array
    {
        return [
            'scheme_type_id' => SchemeType::factory(),
            'submission_type_id' => SubmissionType::factory(),

            'name' => fake()->unique()->words(3, true),
            'code' => fake()->unique()->lexify('????-???'),
            'max_budget' => fake()->numberBetween(10_000_000, 500_000_000),
            'max_members' => fake()->numberBetween(3, 10),
            'duration_months' => fake()->numberBetween(6, 36),
            'rules' => [
                'min_reviewer_count' => 2,
                'max_reviewer_workload' => 10,
            ],
            'is_active' => true,
        ];
    }
}
