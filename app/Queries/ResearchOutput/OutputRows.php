<?php

namespace App\Queries\ResearchOutput;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\ResearchOutput\OutputFieldSpecData;
use App\Data\View\ResearchOutput\OutputRowData;
use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\ResearchOutput;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Baris luaran satu pengajuan, terlama dulu — dipakai daftar peneliti dan tab Luaran Operator.
 * BR-RO-09 — judul dan meta dibaca dari spesifikasi tipe; tipe tanpa spesifikasi berjudul namanya.
 *
 * @method static list<OutputRowData> run(FormSubmission $submission)
 */
class OutputRows implements ChangesNoState
{
    use AsAction;

    /** @return list<OutputRowData> */
    public static function forSubmission(FormSubmission $submission): array
    {
        $outputs = $submission->researchOutputs()->with('files:id,research_output_id')->oldest()->oldest('id')->get();
        $types = OutputTypeDefinition::query()->whereIn('code', $outputs->pluck('output_type')->unique())->get()->keyBy('code');

        return array_values($outputs
            ->map(fn (ResearchOutput $output): OutputRowData => self::row($output, $types->get($output->output_type)))
            ->all());
    }

    /** @return list<OutputRowData> */
    public function handle(FormSubmission $submission): array
    {
        return self::forSubmission($submission);
    }

    /** Judul luaran: isian field pertama spesifikasinya, atau nama tipe bila kosong. */
    public static function headline(ResearchOutput $output, ?OutputTypeDefinition $type): string
    {
        $key = $type?->headlineKey();
        $value = $key === null ? null : ($output->metadata[$key] ?? null);

        return $value === null || $value === '' ? self::typeName($output, $type) : (string) $value;
    }

    /** Tipe yang kini tak terdaftar (data lama) tampil dengan kodenya, bukan hilang. */
    public static function typeName(ResearchOutput $output, ?OutputTypeDefinition $type): string
    {
        return $type->name ?? $output->output_type;
    }

    private static function row(ResearchOutput $output, ?OutputTypeDefinition $type): OutputRowData
    {
        $fileCount = $output->files->count();
        $parts = self::requiredValues($output, $type);

        if ($fileCount > 0) {
            $parts[] = "{$fileCount} file lampiran";
        }

        return new OutputRowData(
            id: $output->id,
            typeName: self::typeName($output, $type),
            headline: self::headline($output, $type),
            meta: $parts === [] ? null : implode(' · ', $parts),
            fileCount: $fileCount,
        );
    }

    /**
     * Isian field wajib selain judul yang terisi, berurutan menurut spesifikasi.
     *
     * @return list<string>
     */
    private static function requiredValues(ResearchOutput $output, ?OutputTypeDefinition $type): array
    {
        $headlineKey = $type?->headlineKey();
        $metadata = $output->metadata ?? [];
        $shown = array_filter(
            $type?->fieldSpecs() ?? [],
            fn (OutputFieldSpecData $spec): bool => $spec->required && $spec->key !== $headlineKey
                && isset($metadata[$spec->key]) && $metadata[$spec->key] !== '',
        );

        return array_values(array_map(
            fn (OutputFieldSpecData $spec): string => $spec->displayValue($metadata[$spec->key] ?? ''),
            $shown,
        ));
    }
}
