<?php

namespace App\Http\Requests\Form;

use App\Data\Request\FormListInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sengaja tanpa aturan: nilai urutan dan halaman yang tak dikenal dipulihkan ke bawaan oleh
 * FormDirectory, supaya tautan lama tidak berujung galat validasi di layar daftar.
 */
class FormListRequest extends FormRequest
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

    public function toInput(): FormListInput
    {
        return new FormListInput(
            search: $this->string('search')->value(),
            formType: $this->string('form_type')->value(),
            active: $this->string('is_active')->value(),
            sortBy: $this->string('sort_by')->value(),
            sortOrder: $this->string('sort_order')->value(),
            perPage: $this->integer('per_page'),
        );
    }
}
