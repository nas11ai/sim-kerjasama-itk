<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sengaja tanpa aturan: filter yang bukan angka berarti tanpa filter, supaya tautan lama tidak
 * berujung galat validasi atau galat bigint di layar daftar.
 */
class ReviewEvaluationFormListRequest extends FormRequest
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

    public function idFilter(string $key): ?int
    {
        return ctype_digit($this->string($key)->value()) ? $this->integer($key) : null;
    }

    public function search(): ?string
    {
        return $this->filled('search') ? $this->string('search')->value() : null;
    }
}
