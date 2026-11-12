<?php

namespace App\Actions\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhase;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-33 (G3-W6) — hanya tahap yang sudah pernah terbit bisa ditautkan ke periode: angkatan
 * tanpa versi tidak punya langkah untuk dimulai. Dipanggil semua jalur yang menautkan tahap ke periode.
 *
 * @method static void run(list<int> $phaseIds, string $errorKey)
 */
class AssertPhasesPublished implements ChangesNoState
{
    use AsAction;

    /**
     * @param  list<int>  $phaseIds
     *
     * @throws ValidationException
     */
    public function handle(array $phaseIds, string $errorKey): void
    {
        if (!FormPhase::query()->whereIn('id', $phaseIds)->whereDoesntHave('versions')->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => 'Tahap ini belum pernah diterbitkan, jadi belum bisa ditautkan ke periode.',
        ]);
    }
}
