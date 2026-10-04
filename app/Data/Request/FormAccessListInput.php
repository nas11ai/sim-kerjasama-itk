<?php

namespace App\Data\Request;

/** Filter mentah daftar kontrol akses; string kosong berarti filter itu tidak dipakai. */
final class FormAccessListInput
{
    public function __construct(
        public readonly string $formId,
        public readonly string $permission,
        public readonly string $facultyId,
        public readonly string $organizationId,
        public readonly string $search,
    ) {}
}
