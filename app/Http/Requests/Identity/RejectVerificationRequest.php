<?php

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

/**
 * BR-IAM-13 — alasan penolakan wajib. Ditegakkan di sini dan di Action: penolakan bisa
 * datang dari jalur lain, dan penolakan tanpa alasan tidak memberi pendaftar apa pun.
 */
class RejectVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:3|max:2000',
        ];
    }

    public function reason(): string
    {
        return $this->string('reason')->value();
    }
}
