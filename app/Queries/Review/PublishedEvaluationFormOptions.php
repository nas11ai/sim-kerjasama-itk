<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\ReviewEvaluationForm;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pilihan "Pasang Formulir Evaluasi" di editor tahap: hanya form yang sudah pernah terbit (BR-FE-30),
 * urut judul lalu id. `value` id form sebagai string, seperti pilihan lain di layar itu.
 *
 * @method static list<array{value: string, label: string, versionLabel: string}> run()
 */
class PublishedEvaluationFormOptions implements ChangesNoState
{
    use AsAction;

    /** @return list<array{value: string, label: string, versionLabel: string}> */
    public function handle(): array
    {
        return array_values(ReviewEvaluationForm::query()
            ->whereHas('versions')
            ->orderBy('title')
            ->orderBy('id')
            ->with('latestVersion')
            ->get(['id', 'title'])
            ->map(fn (ReviewEvaluationForm $form): array => [
                'value' => (string) $form->id,
                'label' => $form->title,
                // Label versi untuk baris pemasangan draf yang baru dipilih (BR-FE-33).
                'versionLabel' => $form->latestVersion?->label() ?? '',
            ])
            ->all());
    }
}
