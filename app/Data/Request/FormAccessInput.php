<?php

namespace App\Data\Request;

/**
 * Satu kontrol akses formulir. Organisasinya boleh unit bertipe apa pun (ADR 0013).
 */
final class FormAccessInput
{
    public function __construct(
        public readonly int $formId,
        public readonly string $permission,
        public readonly int $organizationId,
    ) {}

    /** @return array{form_id: int, permission: string, organization_id: int} */
    public function attributes(): array
    {
        return [
            'form_id' => $this->formId,
            'permission' => $this->permission,
            'organization_id' => $this->organizationId,
        ];
    }
}
