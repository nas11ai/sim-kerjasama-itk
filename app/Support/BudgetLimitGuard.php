<?php

namespace App\Support;

use App\Models\FormSubmission;
use Illuminate\Validation\ValidationException;

/**
 * BR-BUD-01, cermin BR-SM-06 — grand total tidak melebihi `scheme.max_budget`.
 *
 * Satu validator untuk setiap jalur yang mengubah angka: menambah item,
 * menyuntingnya, dan submit. Dua salinan aturan batas berarti satu di
 * antaranya akan tertinggal saat batasnya berubah, dan yang tertinggal itulah
 * yang dipakai peneliti.
 *
 * Submission tanpa skema tidak punya batas (BR-SCH-04).
 */
final class BudgetLimitGuard
{
    /**
     * @param  int|null  $excludingItemId  item yang sedang disunting — nilai lamanya
     *                                     tidak boleh ikut dihitung, karena ia akan
     *                                     digantikan, bukan ditambahi.
     *
     * @throws ValidationException
     */
    public static function assertWithinScheme(
        FormSubmission $submission,
        int $incomingTotal,
        ?int $excludingItemId = null,
    ): void {
        $scheme = $submission->scheme;

        if ($scheme === null) {
            return;
        }

        $terpakai = $submission->budgetLineItems()
            ->when($excludingItemId !== null, fn ($query) => $query->whereKeyNot($excludingItemId))
            ->sum('total');

        if ((int) $terpakai + $incomingTotal > $scheme->max_budget) {
            throw ValidationException::withMessages([
                'total' => 'Total anggaran melebihi batas maksimum skema.',
            ]);
        }
    }
}
