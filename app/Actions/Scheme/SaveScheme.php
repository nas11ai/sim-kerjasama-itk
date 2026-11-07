<?php

namespace App\Actions\Scheme;

use App\Data\Request\SchemeInput;
use App\Models\Scheme;
use App\Models\SchemeAllowedTrl;
use App\Support\Audit\AuditTrail;
use App\Support\Scheme\SchemeRuleCatalog;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SCH-02, BR-SCH-03 — menyimpan skema beserta batas yang diikatnya.
 *
 * @method static Scheme run(SchemeInput $input, ?Scheme $scheme = null)
 */
class SaveScheme
{
    use AsAction;

    /**
     * `max_budget` dan `max_members` ditegakkan di FormRequest maupun di sini: keduanya batas
     * yang dipakai M1 untuk menolak anggaran dan anggota, dan skema bernilai nol akan menolak
     * segalanya tanpa ada yang tahu sebabnya.
     */
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
        ];

        // BR-SCH-06, W108 — null berarti "jangan sentuh"; kiriman digabung ke aturan yang ada.
        if ($input->rules !== null) {
            $attributes['rules'] = SchemeRuleCatalog::merge($scheme?->rules, $input->rules);
        }

        $saved = DB::transaction(function () use ($attributes, $input, $scheme): Scheme {
            if ($scheme === null) {
                $scheme = Scheme::create($attributes);
            } else {
                $scheme->update($attributes);
                $scheme->refresh();
            }

            $this->syncAllowedTrls($scheme, $input->allowedTrlIds);

            return $scheme;
        });

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Skema disimpan', $saved);

        return $saved;
    }

    /**
     * BR-SCH-05 — null berarti "jangan sentuh", daftar kosong berarti "hapus semua". Disimpan
     * sebagai relasi, bukan kolom JSON, karena dipakai sebagai penyaring pilihan (join murah).
     *
     * @param  list<int>|null  $trlIds
     */
    private function syncAllowedTrls(Scheme $scheme, ?array $trlIds): void
    {
        if ($trlIds === null) {
            return;
        }

        SchemeAllowedTrl::query()->where('scheme_id', $scheme->id)->delete();

        foreach (array_unique($trlIds) as $trlId) {
            SchemeAllowedTrl::create(['scheme_id' => $scheme->id, 'trl_id' => $trlId]);
        }
    }
}
