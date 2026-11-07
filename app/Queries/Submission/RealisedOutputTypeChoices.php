<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\ResearchOutput\OutputTypeData;
use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-RO-02, BR-RO-05, BR-RO-09 — tipe luaran terealisasi: taksonomi aktif ∩ jenis pengajuan.
 * Menawarkan tipe yang pasti ditolak membuat peneliti mencoba lalu gagal tanpa tahu sebabnya.
 *
 * @method static list<OutputTypeData> run(FormSubmission $submission)
 */
class RealisedOutputTypeChoices implements ChangesNoState
{
    use AsAction;

    /** @return list<OutputTypeData> */
    public function handle(FormSubmission $submission): array
    {
        $submissionType = $submission->submissionType()?->name;

        return array_values(OutputTypeDefinition::query()
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('submission_type')->orWhere('submission_type', $submissionType))
            ->orderBy('name')
            ->get()
            ->map(fn (OutputTypeDefinition $type): OutputTypeData => OutputTypeData::of($type))
            ->all());
    }
}
