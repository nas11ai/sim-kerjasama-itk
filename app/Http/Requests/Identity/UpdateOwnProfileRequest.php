<?php

namespace App\Http\Requests\Identity;

use App\Data\Request\ProfileDetailsInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SHARED-01-07 — hanya nama, telepon, dan bidang keahlian. Email, NIDN, dan organisasi
 * tidak punya aturan di sini, jadi tidak pernah ikut tersimpan (BR-IAM-01/02/18).
 */
class UpdateOwnProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $name = ['name' => ['required', 'string', 'max:255']];

        // W63 — user tanpa UserProfile tidak punya data tambahan untuk diisi.
        return $this->hasProfile() ? [...$name, ...ProfileDetailsInput::rules()] : $name;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.required' => 'Nama lengkap wajib diisi.', ...ProfileDetailsInput::messages()];
    }

    public function details(): ?ProfileDetailsInput
    {
        if (!$this->hasProfile()) {
            return null;
        }

        return new ProfileDetailsInput(
            phone: $this->string('phone')->trim()->toString(),
            expertise: $this->string('expertise')->trim()->toString(),
        );
    }

    private function hasProfile(): bool
    {
        return $this->user()?->userProfile !== null;
    }
}
