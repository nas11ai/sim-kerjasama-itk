<?php

namespace App\Http\Requests\Form;

use Illuminate\Foundation\Http\FormRequest;

class PhasePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return ['submission_period_id' => 'required|integer|exists:submission_periods,id'];
    }
}
