<?php

namespace Database\Factories;

use App\Models\SubmissionPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubmissionPeriod>
 */
class SubmissionPeriodFactory extends Factory
{
    protected $model = SubmissionPeriod::class;

    public function definition(): array
    {
        return [
            // 'name' unique di level DB, jadi harus fake()->unique() agar dua
            // period dalam satu test tidak bentrok.
            'name' => fake()->unique()->words(3, true),
            'is_force_closed' => false,
        ];
    }
}
