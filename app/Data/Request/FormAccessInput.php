<?php

namespace App\Data\Request;

/**
 * Kontrol akses formulir untuk satu permission pada satu atau beberapa organisasi (K6). Organisasinya
 * boleh unit bertipe apa pun (ADR 0013).
 */
final class FormAccessInput
{
    /** @param  list<int>  $organizationIds */
    public function __construct(
        public readonly int $formId,
        public readonly string $permission,
        public readonly array $organizationIds,
    ) {}

    /** @return array{form_id: int, permission: string, organization_id: int} */
    public function attributesFor(int $organizationId): array
    {
        return [
            'form_id' => $this->formId,
            'permission' => $this->permission,
            'organization_id' => $organizationId,
        ];
    }
}
