<?php

namespace App\Http\Requests\Identity;

use App\Models\Organization;
use App\Support\Identity\OrganizationTypeCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** ADMIN-08-02 — membuat dan menyunting unit (BR-IAM-17). Wewenang dijaga `can:users.manage`. */
class OrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|In>> */
    public function rules(): array
    {
        $unit = $this->unit();
        $presence = $unit === null ? 'required' : 'sometimes';

        // W117 — tipe dari katalog; pada sunting, tipe lama unit itu sendiri tetap diterima.
        $types = array_keys(OrganizationTypeCatalog::LABELS);
        if ($unit !== null) {
            $types[] = $unit->type;
        }

        return [
            'name' => [$presence, 'required', 'string', 'max:191'],
            'type' => [$presence, 'required', 'string', Rule::in($types)],
            'parent_id' => [...($unit === null ? [] : ['sometimes']), 'nullable', 'integer', 'exists:organizations,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama unit harus diisi.',
            'type.required' => 'Pilih tipe unit.',
            'type.in' => 'Pilih tipe unit dari daftar.',
            'parent_id.exists' => 'Unit induk yang dipilih tidak ditemukan.',
        ];
    }

    /** @return array{name?: string, type?: string, parent_id?: int|null} */
    public function toAttributes(): array
    {
        $validated = $this->safe();
        $attributes = [];

        if ($validated->has('name')) {
            $attributes['name'] = $validated->string('name')->value();
        }
        if ($validated->has('type')) {
            $attributes['type'] = $validated->string('type')->value();
        }
        if ($validated->has('parent_id')) {
            $attributes['parent_id'] = $validated->filled('parent_id') ? $validated->integer('parent_id') : null;
        }

        return $attributes;
    }

    private function unit(): ?Organization
    {
        $unit = $this->route('organization');

        return $unit instanceof Organization ? $unit : null;
    }
}
