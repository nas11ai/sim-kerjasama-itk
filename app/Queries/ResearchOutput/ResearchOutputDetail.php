<?php

namespace App\Queries\ResearchOutput;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\ResearchOutput\OutputDetailData;
use App\Data\View\ResearchOutput\OutputDetailFieldData;
use App\Data\View\ResearchOutput\OutputFieldSpecData;
use App\Data\View\ResearchOutput\OutputFileData;
use App\Models\OutputTypeDefinition;
use App\Models\ResearchOutput;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Props ResearchOutput/ShowPage. W143 — siapa pun yang boleh membaca pengajuannya membaca
 * luarannya (BR-SM-07, termasuk reviewer aktif); menulis tetap milik `OutputWriteAccess`.
 *
 * @method static OutputDetailData run(User $viewer, ResearchOutput $output)
 */
class ResearchOutputDetail implements ChangesNoState
{
    use AsAction;

    public function handle(User $viewer, ResearchOutput $output): OutputDetailData
    {
        $submission = $output->formSubmission()->with('form:id,title')->firstOrFail();
        abort_unless($submission->isAccessibleBy($viewer), 403);

        $type = OutputTypeDefinition::query()->where('code', $output->output_type)->first();
        $typeName = OutputRows::typeName($output, $type);

        return new OutputDetailData(
            id: $output->id,
            submissionId: $submission->id,
            submissionTitle: $submission->displayTitle(),
            typeName: $typeName,
            headline: OutputRows::headline($output, $type),
            fields: [new OutputDetailFieldData('Tipe', $typeName), ...self::fields($output, $type)],
            files: OutputFileData::many($output),
            access: OutputWriteAccess::for($viewer, $submission),
        );
    }

    /**
     * Field spesifikasi selain judul, lalu kunci tanpa spesifikasi (baris sebelum W140) dengan
     * kuncinya sebagai label. Kosong → `empty_label` ("Masih berjalan", BR-RO-03) atau "—".
     *
     * @return list<OutputDetailFieldData>
     */
    private static function fields(ResearchOutput $output, ?OutputTypeDefinition $type): array
    {
        $metadata = $output->metadata ?? [];
        $specs = $type?->fieldSpecs() ?? [];

        return [...self::specFields($specs, $metadata, $type?->headlineKey()), ...self::unspecifiedFields($specs, $metadata)];
    }

    /**
     * @param  list<OutputFieldSpecData>  $specs
     * @param  array<string, string|int|null>  $metadata
     * @return list<OutputDetailFieldData>
     */
    private static function specFields(array $specs, array $metadata, ?string $headlineKey): array
    {
        $shown = array_filter($specs, fn (OutputFieldSpecData $spec): bool => $spec->key !== $headlineKey);

        return array_values(array_map(
            fn (OutputFieldSpecData $spec): OutputDetailFieldData => new OutputDetailFieldData($spec->label, self::display($spec, $metadata[$spec->key] ?? null)),
            $shown,
        ));
    }

    /**
     * @param  list<OutputFieldSpecData>  $specs
     * @param  array<string, string|int|null>  $metadata
     * @return list<OutputDetailFieldData>
     */
    private static function unspecifiedFields(array $specs, array $metadata): array
    {
        $known = array_flip(array_map(fn (OutputFieldSpecData $spec): string => $spec->key, $specs));
        $fields = [];

        foreach (array_diff_key($metadata, $known) as $key => $value) {
            $fields[] = new OutputDetailFieldData($key, $value === null || $value === '' ? '-' : (string) $value);
        }

        return $fields;
    }

    private static function display(OutputFieldSpecData $spec, string|int|null $value): string
    {
        if ($value === null || $value === '') {
            return $spec->emptyLabel ?? '-';
        }

        return $spec->displayValue($value);
    }
}
