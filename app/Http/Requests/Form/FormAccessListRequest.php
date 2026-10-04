<?php

namespace App\Http\Requests\Form;

use App\Data\Request\FormAccessListInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sengaja tanpa aturan: filter yang tak dikenal cukup tidak menemukan apa pun, supaya
 * tautan lama tidak berujung galat validasi di layar daftar.
 */
class FormAccessListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [];
    }

    public function toInput(): FormAccessListInput
    {
        return new FormAccessListInput(
            formId: $this->filter('form_id'),
            permission: $this->filter('permission'),
            facultyId: $this->filter('faculty_id'),
            organizationId: $this->filter('study_program_id'),
            search: $this->filter('search'),
        );
    }

    /** Nilai bukan teks (mis. `?form_id[]=1`) berarti filter tak dipakai, bukan 500. */
    private function filter(string $key): string
    {
        $value = $this->input($key);

        return is_string($value) ? $value : '';
    }
}
