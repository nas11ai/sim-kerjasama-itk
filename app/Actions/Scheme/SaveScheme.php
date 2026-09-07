<?php

namespace App\Actions\Scheme;

use App\Models\Scheme;
use App\Models\SchemeAllowedTrl;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SCH-02, BR-SCH-03 — menyimpan skema beserta batas yang diikatnya.
 *
 * `max_budget` dan `max_members` ditegakkan di FormRequest maupun di sini:
 * keduanya batas yang dipakai M1 untuk menolak anggaran dan anggota, dan
 * skema bernilai nol akan menolak segalanya tanpa ada yang tahu sebabnya.
 *
 * TRL yang diizinkan disimpan sebagai relasi, bukan kolom JSON, karena ia
 * dipakai sebagai penyaring pilihan (BR-SCH-05) dan penyaring itu jauh lebih
 * murah sebagai join.
 *
 * @method static Scheme run(array<string, mixed> $attributes, ?Scheme $scheme = null, ?array<int, int> $allowedTrlIds = null)
 */
class SaveScheme
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int>|null  $allowedTrlIds  null berarti "jangan sentuh"
     */
    public function handle(array $attributes, ?Scheme $scheme = null, ?array $allowedTrlIds = null): Scheme
    {
        return DB::transaction(function () use ($attributes, $scheme, $allowedTrlIds): Scheme {
            if ($scheme === null) {
                $scheme = Scheme::create($attributes);
            } else {
                $scheme->update($attributes);
                $scheme->refresh();
            }

            if ($allowedTrlIds !== null) {
                SchemeAllowedTrl::query()->where('scheme_id', $scheme->id)->delete();

                foreach (array_unique($allowedTrlIds) as $trlId) {
                    SchemeAllowedTrl::create(['scheme_id' => $scheme->id, 'trl_id' => $trlId]);
                }
            }

            return $scheme;
        });
    }
}
