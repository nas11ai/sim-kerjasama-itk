<?php

namespace App\Queries\ResearchOutput;

use App\Actions\Concerns\ChangesNoState;
use App\Enums\DeadlineType;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-RO-08, W139 — detail "Upload Luaran" milik sebuah induk: dalam tahap yang sama, sesudah
 * detail induk, berjenis `output` (BR-FE-07; Batas Akhir di kalender periode induk). Satu-satunya detail yang menggerbangi luaran;
 * tanpanya penulisan tidak berbatas waktu (`PhaseAccessGate::isOpenFor(null)`).
 *
 * @method static FormPhaseDetail|null run(FormSubmission $parent)
 */
class OutputUploadDetail implements ChangesNoState
{
    use AsAction;

    public static function for(FormSubmission $parent): ?FormPhaseDetail
    {
        $own = $parent->formPhaseDetail;

        if ($own === null) {
            return null;
        }

        return FormPhaseDetail::query()
            // BR-FE-32 — di versi struktur langkah induk.
            ->where('form_phase_version_id', $own->form_phase_version_id)
            ->where('order', '>', $own->order)
            ->where('deadline_type', DeadlineType::Output->value)
            ->ordered()
            ->first();
    }

    public function handle(FormSubmission $parent): ?FormPhaseDetail
    {
        return self::for($parent);
    }
}
