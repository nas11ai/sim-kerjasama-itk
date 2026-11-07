<?php

namespace App\Queries\ResearchOutput;

use App\Actions\Concerns\ChangesNoState;
use App\Enums\DeadlineType;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-RO-08, W139 — detail "Upload Luaran" milik sebuah induk: dalam tahap yang sama, sesudah
 * detail induk, bertenggat Batas Akhir (`output`). Satu-satunya detail yang menggerbangi luaran;
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
            ->where('form_phase_id', $own->form_phase_id)
            ->where('order', '>', $own->order)
            ->whereHas('submissionDate', fn (Builder $date) => $date->where('type', DeadlineType::Output->value))
            ->with('submissionDate.submissionPeriod')
            ->ordered()
            ->first();
    }

    public function handle(FormSubmission $parent): ?FormPhaseDetail
    {
        return self::for($parent);
    }
}
