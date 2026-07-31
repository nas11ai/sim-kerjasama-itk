<?php

namespace Database\Factories;

use App\Models\SchemeType;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchemeTypeFactory extends Factory
{
    protected $model = SchemeType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'is_active' => true,
        ];
    }
}
