<?php

namespace App\Data\Request;

use Illuminate\Support\ValidatedInput;

/** BR-SCH-01, BR-SCH-02 — masukan tervalidasi untuk membuat atau menyunting skema. */
final class SchemeInput
{
    /**
     * @param  array<array-key, mixed>|null  $rules  kolom JSON `schemes.rules`, dibaca lewat Scheme::getRule
     * @param  list<int>|null  $allowedTrlIds  null berarti "jangan sentuh" (BR-SCH-05)
     */
    public function __construct(
        public readonly string $name,
        public readonly string $code,
        public readonly int $maxBudget,
        public readonly int $maxMembers,
        public readonly int $durationMonths,
        public readonly ?int $schemeTypeId = null,
        public readonly ?int $submissionTypeId = null,
        public readonly ?array $rules = null,
        public readonly ?array $allowedTrlIds = null,
    ) {}

    /** Aturan `integer` menerima "10", jadi angka dibaca lewat integer(), bukan diperiksa is_int(). */
    public static function fromValidated(ValidatedInput $input): self
    {
        return new self(
            name: $input->string('name')->value(),
            code: $input->string('code')->value(),
            maxBudget: $input->integer('max_budget'),
            maxMembers: $input->integer('max_members'),
            durationMonths: $input->integer('duration_months'),
            schemeTypeId: $input->filled('scheme_type_id') ? $input->integer('scheme_type_id') : null,
            submissionTypeId: $input->filled('submission_type_id') ? $input->integer('submission_type_id') : null,
            rules: $input->input('rules') === null ? null : $input->array('rules'),
            allowedTrlIds: self::integerList($input, 'allowed_trl_ids'),
        );
    }

    /** @return list<int>|null null kalau kuncinya tidak dikirim, bukan kalau daftarnya kosong */
    private static function integerList(ValidatedInput $input, string $key): ?array
    {
        if ($input->input($key) === null) {
            return null;
        }

        return array_map(
            static fn (int|string $index): int => $input->integer("{$key}.{$index}"),
            array_keys($input->array($key)),
        );
    }
}
