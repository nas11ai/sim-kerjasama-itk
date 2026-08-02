<?php

namespace Database\Factories;

use App\Models\SubmissionType;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubmissionTypeFactory extends Factory
{
    protected $model = SubmissionType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'is_active' => true,
        ];
    }
}
