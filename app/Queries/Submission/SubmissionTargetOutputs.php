<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Submission\SubmissionTargetOutputData;
use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\SubmissionTargetOutput;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-15 — target luaran pengajuan dengan nama tipenya, untuk detail dan wizard. Akses
 * dijaga pemanggil.
 *
 * @method static list<SubmissionTargetOutputData> run(FormSubmission $submission)
 */
class SubmissionTargetOutputs implements ChangesNoState
{
    use AsAction;

    /** @return list<SubmissionTargetOutputData> */
    public function handle(FormSubmission $submission): array
    {
        $targets = $submission->targetOutputs()->orderBy('id')->get(['id', 'output_type', 'description']);
        $names = [];

        foreach (OutputTypeDefinition::query()->whereIn('code', $targets->pluck('output_type'))->get(['code', 'name']) as $type) {
            $names[$type->code] = $type->name;
        }

        return array_values($targets->map(fn (SubmissionTargetOutput $target): SubmissionTargetOutputData => new SubmissionTargetOutputData(
            id: $target->id,
            type: $names[$target->output_type] ?? $target->output_type,
            description: $target->description,
        ))->all());
    }
}
