<?php

namespace Database\Factories;

use App\Models\Reviewer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reviewer>
 */
class ReviewerFactory extends Factory
{
    protected $model = Reviewer::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reviewer_type' => 'internal',
            'start_date' => now(),
            'end_date' => null,
        ];
    }
}
