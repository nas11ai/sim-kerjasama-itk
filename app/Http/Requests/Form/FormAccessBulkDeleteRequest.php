<?php

namespace App\Http\Requests\Form;

use Illuminate\Foundation\Http\FormRequest;

class FormAccessBulkDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:form_access_controls,id',
        ];
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_values(array_map(intval(...), $this->array('ids')));
    }
}
