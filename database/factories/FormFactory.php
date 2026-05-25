<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Form>
 */
class FormFactory extends Factory
{
    protected $model = Form::class;

    public function definition(): array
    {
        return [
            'title' => 'Sample Form',
            'description' => 'This is a sample description',
            'is_active' => true,
            'form_type_id' => FormType::factory(),
        ];
    }
}
