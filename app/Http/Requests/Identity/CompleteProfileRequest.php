<?php

namespace App\Http\Requests\Identity;

use App\Data\Request\ProfileDetailsInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\RequiredIf;

/**
 * SHARED-01-06 — BR-IAM-03: ketiga data tambahan wajib. Foto hanya wajib diunggah bila
 * belum ada yang tersimpan. NIDN, organisasi, dan status tidak punya aturan, jadi diabaikan.
 */
class CompleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|File|RequiredIf>> */
    public function rules(): array
    {
        return [
            ...ProfileDetailsInput::rules(),
            'photo' => [
                Rule::requiredIf(fn (): bool => blank($this->user()?->userProfile?->photo_path)),
                'nullable',
                ProfilePhotoRequest::photoRule(),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [...ProfileDetailsInput::messages(), ...ProfilePhotoRequest::photoMessages()];
    }

    public function details(): ProfileDetailsInput
    {
        return new ProfileDetailsInput(
            phone: $this->string('phone')->trim()->toString(),
            expertise: $this->string('expertise')->trim()->toString(),
        );
    }

    public function photo(): ?UploadedFile
    {
        $file = $this->file('photo');

        return $file instanceof UploadedFile ? $file : null;
    }
}
