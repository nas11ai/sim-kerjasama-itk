<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FieldType;
use App\Models\FormPhase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pilihan tahap dan tipe field untuk layar Buat/Ubah Form Evaluasi. Hanya detail yang direview
 * yang ditawarkan, sama dengan yang diterima AssertReviewedPhaseDetail.
 *
 * @method static array{formPhases: Collection<int, FormPhase>, fieldTypes: Collection<int, FieldType>} run()
 */
class ReviewEvaluationFormChoices implements ChangesNoState
{
    use AsAction;

    /** @return array{formPhases: Collection<int, FormPhase>, fieldTypes: Collection<int, FieldType>} */
    public function handle(): array
    {
        return [
            'formPhases' => FormPhase::query()
                ->where('is_active', true)
                ->with([
                    'formPhaseDetails' => fn (Relation $detail) => $detail->where('needs_review', true),
                    'formPhaseDetails.formAccessControl.form',
                    'formPhaseDetails.formAccessControl.studyProgram',
                ])
                ->orderBy('title')
                ->get(),
            'fieldTypes' => FieldType::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
