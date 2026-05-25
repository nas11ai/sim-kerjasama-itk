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
        return [
            'name' => 'Tipe Form Standar',
        ];
    }
}
