<?php

namespace App\Data\Request;

use App\Support\Scheme\SchemeRuleCatalog;
use Illuminate\Support\ValidatedInput;

/** BR-SCH-01, BR-SCH-02 — masukan tervalidasi untuk membuat atau menyunting skema. */
final class SchemeInput
{
    /**
     * @param  array<string, int|list<string>|null>|null  $rules  kunci SchemeRuleCatalog yang dikirim; null = jangan sentuh (W108)
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
            rules: self::rules($input),
            allowedTrlIds: self::integerList($input, 'allowed_trl_ids'),
        );
    }

    /**
     * W107 — angka dibaca lewat integer() supaya JSONB menyimpan tipe yang diperiksa pembacanya.
     *
     * @return array<string, int|list<string>|null>|null
     */
    private static function rules(ValidatedInput $input): ?array
    {
        if ($input->input('rules') === null) {
            return null;
        }

        $rules = [];

        foreach (array_keys(SchemeRuleCatalog::INTEGER_KEYS) as $key) {
            if ($input->has("rules.{$key}")) {
                $rules[$key] = $input->filled("rules.{$key}") ? $input->integer("rules.{$key}") : null;
            }
        }

        $outputTypes = 'rules.'.SchemeRuleCatalog::OUTPUT_TYPES_KEY;

        if ($input->has($outputTypes)) {
            $rules[SchemeRuleCatalog::OUTPUT_TYPES_KEY] = array_map(
                static fn (int|string $index): string => $input->string("{$outputTypes}.{$index}")->value(),
                array_keys($input->array($outputTypes)),
            );
        }

        return $rules;
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
