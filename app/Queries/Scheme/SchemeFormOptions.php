<?php

namespace App\Queries\Scheme;

use App\Actions\Concerns\ChangesNoState;
use App\Models\OutputTypeDefinition;
use App\Models\Scheme;
use App\Models\SchemeType;
use App\Models\SubmissionType;
use App\Models\TechnologyReadinessLevel;
use App\Support\Scheme\SchemeRuleCatalog;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * ADMIN-08-04 — pilihan form skema. Yang nonaktif hanya ditawarkan bila sudah dipakai
 * skema itu (SC-CONFIG-05, W110), supaya menyimpan ulang tidak membuangnya diam-diam.
 *
 * @phpstan-type Option array{value: string, label: string}
 * @phpstan-type Result array{schemeTypes: list<Option>, submissionTypes: list<Option>, trls: list<Option>, outputTypes: list<Option>, integerRules: list<Option>}
 *
 * @method static Result run(?Scheme $scheme)
 */
class SchemeFormOptions implements ChangesNoState
{
    use AsAction;

    /** @return Result */
    public function handle(?Scheme $scheme): array
    {
        return [
            'schemeTypes' => $this->schemeTypes($scheme?->scheme_type_id),
            'submissionTypes' => array_values(SubmissionType::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (SubmissionType $type): array => ['value' => (string) $type->id, 'label' => $type->name])
                ->all()),
            'trls' => array_values(TechnologyReadinessLevel::query()->orderBy('id')->get(['id', 'name'])
                ->map(fn (TechnologyReadinessLevel $trl): array => ['value' => (string) $trl->id, 'label' => $trl->name])
                ->all()),
            'outputTypes' => $this->outputTypes($scheme),
            'integerRules' => array_map(
                fn (string $key, string $label): array => ['value' => $key, 'label' => $label],
                array_keys(SchemeRuleCatalog::INTEGER_KEYS),
                SchemeRuleCatalog::INTEGER_KEYS,
            ),
        ];
    }

    /** @return list<Option> */
    private function schemeTypes(?int $currentId): array
    {
        return array_values(SchemeType::query()
            ->where(fn (Builder $q) => $q->where('is_active', true)->when($currentId !== null, fn (Builder $q) => $q->orWhere('id', $currentId)))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (SchemeType $type): array => ['value' => (string) $type->id, 'label' => $type->name])
            ->all());
    }

    /** @return list<Option> */
    private function outputTypes(?Scheme $scheme): array
    {
        $selected = $scheme?->codeListRule(SchemeRuleCatalog::OUTPUT_TYPES_KEY) ?? [];

        return array_values(OutputTypeDefinition::query()
            ->where(fn (Builder $q) => $q->where('is_active', true)->orWhereIn('code', $selected))
            ->orderBy('name')
            ->get(['code', 'name', 'is_active'])
            ->map(fn (OutputTypeDefinition $type): array => [
                'value' => $type->code,
                'label' => $type->is_active ? $type->name : "{$type->name} (nonaktif)",
            ])
            ->all());
    }
}
