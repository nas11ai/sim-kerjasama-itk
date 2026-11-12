<?php

namespace App\Actions\Form;

use App\Models\FormPhaseDetail;
use App\Models\FormPhaseVersion;
use App\Models\PhaseVersionPin;
use App\Models\SubmissionPeriod;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-32 — memaku versi struktur angkatan (`$period` × tahap `$detail`) saat pengajuan pertamanya
 * dibuat. Angkatan baru dipaku ke versi terbaru; langkah dari versi lain ditolak, jadi pengajuan
 * tidak pernah lahir di versi yang bukan milik angkatannya.
 *
 * @method static FormPhaseVersion run(SubmissionPeriod $period, FormPhaseDetail $detail)
 */
class PinPhaseVersion
{
    use AsAction;

    public const REPUBLISHED = 'Struktur tahap ini baru saja diterbitkan ulang. Muat ulang halaman lalu mulai lagi.';

    /**
     * @throws ValidationException
     */
    public function handle(SubmissionPeriod $period, FormPhaseDetail $detail): FormPhaseVersion
    {
        $latest = FormPhaseVersion::query()
            ->where('form_phase_id', $detail->form_phase_id)
            ->orderByDesc('number')
            ->firstOrFail();

        // Dua pengajuan pertama yang hampir bersamaan bertemu di indeks unik angkatan; createOrFirst
        // memakai savepoint, jadi yang kalah membaca pakuan pemenang tanpa membatalkan transaksinya.
        $pin = PhaseVersionPin::query()->createOrFirst(
            ['submission_period_id' => $period->id, 'form_phase_id' => $detail->form_phase_id],
            ['form_phase_version_id' => $latest->id, 'pinned_at' => now()],
        );

        // Layar Mulai memuat langkah versi lama, lalu versi baru terbit sebelum klik peneliti.
        if ($pin->form_phase_version_id !== $detail->form_phase_version_id) {
            throw ValidationException::withMessages([
                'form_phase_detail_id' => self::REPUBLISHED,
            ]);
        }

        if ($pin->wasRecentlyCreated) {
            AuditTrail::record('Struktur tahap dipaku', $pin, change: new AuditChange(new: [
                'form_phase_id' => $detail->form_phase_id,
                'version' => $pin->formPhaseVersion->number,
            ]));
        }

        return $pin->formPhaseVersion;
    }
}
