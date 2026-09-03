<?php

namespace Database\Factories;

use App\Models\FormType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormType>
 */
class FormTypeFactory extends Factory
{
    protected $model = FormType::class;

    public function definition(): array
    {
        // form_types.name unik di level DB; fake()->unique() supaya dua
        // panggilan factory dalam satu test tidak bentrok.
        return [
            'name' => 'Tipe Form '.fake()->unique()->numerify('####-####'),
        ];
    }
}
