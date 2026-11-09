<?php

namespace App\Queries\Search;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\SearchHitData;
use App\Data\View\SubmissionStatusData;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Reporting\SubmissionVisibility;
use App\Support\Search\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * K2 grup Pengajuan (W172) — cocok judul Form atau nama ketua, sama dengan daftar Operator.
 * Cakupannya meniru dua daftar yang sudah ada; laporan monev (anak) tidak ikut di keduanya.
 *
 * @method static list<SearchHitData> run(User $viewer, string $term)
 */
class SearchSubmissions implements ChangesNoState
{
    use AsAction;

    /** @return list<SearchHitData> */
    public function handle(User $viewer, string $term): array
    {
        $viewAll = $viewer->can('submissions.view-all');

        // W172 — reviewer memakai Penugasan Review, bukan grup ini.
        if (!$viewAll && !$viewer->can('submissions.view-own')) {
            return [];
        }

        // BR-SM-07 — view-all melihat semua pengajuan terkirim (OperatorSubmissionList);
        // selain itu hanya yang diketuai atau diikuti sebagai anggota aktif.
        $query = $viewAll
            ? FormSubmission::query()->where('is_submitted', true)
            : SubmissionVisibility::ownedOrJoinedBy($viewer);
        $showRoute = $viewAll ? 'admin.submissions.show' : 'user.submissions.show';
        $pattern = LikePattern::contains($term);

        return array_values($query
            ->whereNull('parent_submission_id')
            ->where(fn (Builder $any) => $any
                ->whereHas('form', fn (Builder $form) => $form->whereLike('title', $pattern))
                ->orWhereHas('submittedBy', fn (Builder $lead) => $lead->whereLike('name', $pattern)))
            ->with(['form:id,title', 'submittedBy:id,name'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(GlobalSearch::LIMIT)
            ->get()
            ->map(fn (FormSubmission $s): SearchHitData => new SearchHitData(
                id: $s->id,
                // E2E-11 — label = judul Form, sama dengan daftar pengajuan.
                label: $s->form->title,
                sublabel: SubmissionStatusData::of($s)->label.' · '.$s->submittedBy->name,
                url: route($showRoute, $s->id),
            ))
            ->all());
    }
}
