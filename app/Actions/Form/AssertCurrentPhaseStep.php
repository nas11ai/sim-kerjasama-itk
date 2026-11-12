<?php

namespace App\Actions\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhaseDetail;
use App\Models\FormPhaseVersion;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-32 — langkah yang dikirim layar harus milik versi terbaru tahapnya; versi lama beku.
 * Mengembalikan versi itu. Hanya membaca dan menolak, **tanpa audit (BR-RPT-09).**
 *
 * @method static FormPhaseVersion run(FormPhaseDetail $detail)
 */
class AssertCurrentPhaseStep implements ChangesNoState
{
    use AsAction;

    public const REPUBLISHED = 'Struktur tahap ini baru saja diterbitkan ulang. Muat ulang halaman lalu mulai lagi.';

    /**
     * @throws ValidationException
     */
    public function handle(FormPhaseDetail $detail): FormPhaseVersion
    {
        $latest = FormPhaseVersion::query()
            ->where('form_phase_id', $detail->form_phase_id)
            ->orderByDesc('number')
            ->firstOrFail();

        if ($latest->id !== $detail->form_phase_version_id) {
            throw ValidationException::withMessages(['form_phase_detail_id' => self::REPUBLISHED]);
        }

        return $latest;
    }
}
