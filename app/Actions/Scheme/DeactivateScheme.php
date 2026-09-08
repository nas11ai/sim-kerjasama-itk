<?php

namespace App\Actions\Scheme;

use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Support\Audit\AuditTrail;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SCH-01 — skema tidak bisa dinonaktifkan selama masih ada pengajuan aktif
 * yang memakainya.
 *
 * **Aktif** di sini berarti `status NOT IN ('rejected', 'withdrawn')` — dan
 * APPROVED ikut menghalangi. Pengajuan yang disetujui masih kegiatan berjalan:
 * laporan monevnya lahir dari sana, dan skemanya masih memasok `max_budget`
 * serta `duration_months` yang dipakai memvalidasi laporan itu. Menonaktifkan
 * skemanya di tengah jalan mencabut acuan yang masih dipakai.
 *
 * Predikatnya tidak ditulis ulang di sini melainkan dipinjam dari
 * `FormSubmission::active()`, yang juga dipakai index unik BR-SM-01 dan
 * penjagaan deaktivasi user.
 *
 * @method static Scheme run(Scheme $scheme)
 */
class DeactivateScheme
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(Scheme $scheme): Scheme
    {
        $blocking = FormSubmission::query()
            ->where('scheme_id', $scheme->id)
            ->active()
            ->count();

        if ($blocking > 0) {
            throw ValidationException::withMessages([
                'scheme_id' => "Masih ada {$blocking} pengajuan aktif yang memakai skema ini. "
                    .'Selesaikan atau tarik pengajuan itu lebih dulu.',
            ]);
        }

        $scheme->update(['is_active' => false]);

        $tercatat = $scheme->refresh();

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Skema dinonaktifkan', $tercatat);

        return $tercatat;
    }
}
