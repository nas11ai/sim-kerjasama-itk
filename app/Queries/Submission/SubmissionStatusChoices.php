<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\States\Submission\Draft;
use App\States\Submission\SubmissionStatus;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Opsi filter status untuk layar daftar. Nilainya nama state yang tersimpan di kolom, bukan
 * nama kelasnya: kelas tidak pernah cocok dengan isi kolom `status`.
 *
 * @method static list<array{value: string, label: string}> run()
 */
class SubmissionStatusChoices implements ChangesNoState
{
    use AsAction;

    /**
     * Tanpa Draft: bagi Operator dan ekspor (W154) ia belum ada. Dipakai daftar Operator dan ekspor.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function submitted(): array
    {
        $draft = Draft::getMorphClass();

        return array_values(array_filter(self::run(), fn (array $o): bool => $o['value'] !== $draft));
    }

    /** @return list<array{value: string, label: string}> */
    public function handle(): array
    {
        $choices = [];

        foreach (SubmissionStatus::options() as $state => $label) {
            $choices[] = ['value' => $state::getMorphClass(), 'label' => $label];
        }

        return $choices;
    }
}
