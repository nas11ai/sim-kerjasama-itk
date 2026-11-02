<?php

namespace App\Http\Requests\Identity;

use App\Enums\InvitationPreset;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * BR-IAM-16, W51 — hak akses hanya dari preset; `permissions[]` kiriman klien diabaikan
 * karena tidak pernah dibaca. Kuota dan masa berlaku wajib (frame ADMIN-08-01).
 */
class IssueInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'integer', Rule::exists(Organization::class, 'id')->where('is_active', true)],
            'preset' => ['required', Rule::enum(InvitationPreset::class)],
            'max_uses' => ['required', 'integer', 'min:1'],
            'expires_at' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    public function organizationId(): int
    {
        return $this->integer('organization_id');
    }

    public function preset(): InvitationPreset
    {
        return $this->enum('preset', InvitationPreset::class) ?? InvitationPreset::Researcher;
    }

    public function maxUses(): int
    {
        return $this->integer('max_uses');
    }

    // Pola W34 — tanggal saja berarti berlaku sampai akhir hari itu.
    public function expiresAt(): string
    {
        return Carbon::parse($this->string('expires_at')->value())->endOfDay()->toDateTimeString();
    }
}
