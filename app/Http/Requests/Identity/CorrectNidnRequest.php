<?php

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

/** BR-IAM-13 — koreksi NIDN oleh Operator; keunikannya (BR-IAM-02) dijaga CorrectNidn. */
class CorrectNidnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'nidn' => 'required|string|max:32',
        ];
    }

    public function nidn(): string
    {
        return $this->string('nidn')->value();
    }
}
