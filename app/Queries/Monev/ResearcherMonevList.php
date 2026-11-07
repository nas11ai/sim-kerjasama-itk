<?php

namespace App\Queries\Monev;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Monev\MonevSubmissionData;
use App\Data\View\SubmissionStatusData;
use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Submission\TeamParentSubmissions;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W128 — Daftar Monev peneliti (RESEARCHER-05-01): induk yang diketuai atau diikuti
 * (BR-SM-07), APPROVED, atau WITHDRAWN yang sudah punya laporan (MON-CYCLE-11).
 * Tanpa paginasi: jumlah per peneliti kecil.
 *
 * @method static list<MonevSubmissionData> run(User $user)
 */
class ResearcherMonevList implements ChangesNoState
{
    use AsAction;

    /** @return Builder<FormSubmission> */
    public static function query(User $user): Builder
    {
        return TeamParentSubmissions::query($user, 'children');
    }

    /** @return list<MonevSubmissionData> */
    public function handle(User $user): array
    {
        return array_values(self::query($user)
            ->get()
            ->map(fn (FormSubmission $parent): MonevSubmissionData => new MonevSubmissionData(
                id: $parent->id,
                title: $parent->form->title,
                scheme: $parent->scheme?->name,
                status: SubmissionStatusData::of($parent),
                canFill: $user->can('startMonevReport', $parent),
                cycles: MonevCycles::run($parent),
            ))
            ->all());
    }
}
