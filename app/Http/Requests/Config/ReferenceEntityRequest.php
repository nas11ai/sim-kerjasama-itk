<?php

namespace App\Http\Requests\Config;

use App\Data\Request\ReferenceEntityInput;
use App\Support\Config\ReferenceEntityRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/** ADMIN-08-10 — tambah dan ganti nama data acuan. Wewenang dijaga `can:schemes.manage`. */
class ReferenceEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|Unique>> */
    public function rules(): array
    {
        $key = $this->route('entity');
        $entity = ReferenceEntityRegistry::get(is_string($key) ? $key : '');
        $table = (new $entity->model)->getTable();

        return [
            // W114 — baris yang disunting boleh tetap bernama sama.
            'name' => ['required', 'string', 'max:191', Rule::unique($table, 'name')->ignore($this->route('id'))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama harus diisi.',
            'name.unique' => 'Nama itu sudah dipakai.',
        ];
    }

    public function toInput(): ReferenceEntityInput
    {
        return ReferenceEntityInput::fromValidated($this->safe());
    }
}
