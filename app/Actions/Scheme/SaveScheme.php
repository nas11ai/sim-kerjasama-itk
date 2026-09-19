<?php

namespace App\Actions\Scheme;

use App\Data\Request\SchemeInput;
use App\Models\Scheme;
use App\Models\SchemeAllowedTrl;
use App\Support\Audit\AuditTrail;
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
 * @method static Scheme run(SchemeInput $input, ?Scheme $scheme = null)
 */
class SaveScheme
{
    use AsAction;

    public function handle(SchemeInput $input, ?Scheme $scheme = null): Scheme
    {
        $attributes = [
            'name' => $input->name,
            'code' => $input->code,
            'max_budget' => $input->maxBudget,
            'max_members' => $input->maxMembers,
            'duration_months' => $input->durationMonths,
            'scheme_type_id' => $input->schemeTypeId,
            'submission_type_id' => $input->submissionTypeId,
            'rules' => $input->rules,
        ];

        $saved = DB::transaction(function () use ($attributes, $input, $scheme): Scheme {
            if ($scheme === null) {
                $scheme = Scheme::create($attributes);
            } else {
                $scheme->update($attributes);
                $scheme->refresh();
            }

            // BR-SCH-05 — null berarti "jangan sentuh", daftar kosong berarti "hapus semua".
            if ($input->allowedTrlIds !== null) {
                SchemeAllowedTrl::query()->where('scheme_id', $scheme->id)->delete();

                foreach (array_unique($input->allowedTrlIds) as $trlId) {
                    SchemeAllowedTrl::create(['scheme_id' => $scheme->id, 'trl_id' => $trlId]);
                }
            }

            return $scheme;
        });

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Skema disimpan', $saved);

        return $saved;
    }
}
