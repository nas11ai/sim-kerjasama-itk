<?php

namespace App\Http\Requests\Form;

use Illuminate\Foundation\Http\FormRequest;

class FormActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return ['active' => 'required|boolean'];
    }
}
