<?php

namespace App\Http\Requests\Form;

use App\Data\Request\FormPhaseListInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sengaja tanpa aturan: nilai urutan dan halaman yang tak dikenal dipulihkan ke bawaan oleh
 * FormPhaseDirectory, supaya tautan lama tidak berujung galat validasi di layar daftar.
 */
class FormPhaseListRequest extends FormRequest
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

    public function toInput(): FormPhaseListInput
    {
        return new FormPhaseListInput(
            search: $this->string('search')->value(),
            active: $this->string('is_active')->value(),
            sortBy: $this->string('sort_by')->value(),
            sortOrder: $this->string('sort_order')->value(),
            perPage: $this->integer('per_page'),
        );
    }
}
