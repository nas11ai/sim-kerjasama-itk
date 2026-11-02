<?php

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

/** Satu hak akses langsung yang diberikan atau dicabut; katalognya dijaga Action. */
class DirectPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'permission' => 'required|string',
        ];
    }

    public function permissionName(): string
    {
        return $this->string('permission')->value();
    }
}
