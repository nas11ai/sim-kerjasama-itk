<?php

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

/** Peran yang dicentang di dialog "Ubah Peran"; batas ASSIGNABLE dijaga ChangeUserRoles. */
class ChangeUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'roles' => 'present|array',
            'roles.*' => 'string|distinct',
        ];
    }

    /** @return list<string> */
    public function roleNames(): array
    {
        return array_values(array_filter($this->array('roles'), 'is_string'));
    }
}
