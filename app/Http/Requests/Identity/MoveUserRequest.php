<?php

namespace App\Http\Requests\Identity;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/** BR-IAM-18 — memindahkan user ke unit lain (ADMIN-08-02). Wewenang dijaga `can:organizations.manage`. */
class MoveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Pilih user yang akan dipindahkan.',
            'user_id.integer' => 'Pilih user yang akan dipindahkan.',
            'user_id.exists' => 'User yang dipilih tidak ditemukan.',
        ];
    }

    public function movedUser(): User
    {
        return User::query()->findOrFail($this->safe()->integer('user_id'));
    }
}
