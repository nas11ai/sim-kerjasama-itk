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
        $viewOwn = $viewer->can('submissions.view-own');

        // W172 — reviewer memakai Penugasan Review, bukan grup ini.
        if (!$viewAll && !$viewOwn) {
            return [];
        }

        $pattern = LikePattern::contains($term);
        $found = $this->scope($viewer, $viewAll, $viewOwn)
            ->whereNull('parent_submission_id')
            ->where(fn (Builder $any) => $any
                ->whereHas('form', fn (Builder $form) => $form->whereLike('title', $pattern))
                ->orWhereHas('submittedBy', fn (Builder $lead) => $lead->whereLike('name', $pattern)))
            ->with(['form:id,title', 'submittedBy:id,name'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(GlobalSearch::LIMIT)
            ->get();
        $own = $this->ownIds($viewer, $viewAll, $viewOwn, $found->map(fn (FormSubmission $s): int => $s->id)->all());

        return array_values($found
            ->map(fn (FormSubmission $s): SearchHitData => new SearchHitData(
                id: $s->id,
                // E2E-11 — label = judul Form, sama dengan daftar pengajuan.
                label: $s->form->title,
                sublabel: SubmissionStatusData::of($s)->label.' · '.$s->submittedBy->name,
                // E2E-19 — milik sendiri ke halaman peneliti, selebihnya ke halaman Operator.
                url: route(in_array($s->id, $own, true) ? 'user.submissions.show' : 'admin.submissions.show', $s->id),
            ))
            ->all());
    }

    /**
     * BR-SM-07 — view-all melihat semua pengajuan terkirim (OperatorSubmissionList); view-own
     * hanya yang diketuai atau diikuti sebagai anggota aktif. Pemegang keduanya mendapat
     * gabungannya, supaya draft miliknya sendiri tetap ditemukan (E2E-19).
     *
     * @return Builder<FormSubmission>
     */
    private function scope(User $viewer, bool $viewAll, bool $viewOwn): Builder
    {
        if (!$viewAll) {
            return SubmissionVisibility::ownedOrJoinedBy($viewer);
        }

        if (!$viewOwn) {
            return FormSubmission::query()->where('is_submitted', true);
        }

        return FormSubmission::query()->where(fn (Builder $any) => $any
            ->where('is_submitted', true)
            ->orWhereIn('id', SubmissionVisibility::ownedOrJoinedBy($viewer)->select('id')));
    }

    /**
     * Id hasil yang diketuai atau diikuti $viewer — satu kueri untuk seluruh hasil.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function ownIds(User $viewer, bool $viewAll, bool $viewOwn, array $ids): array
    {
        if (!$viewAll) {
            return $ids;
        }

        if (!$viewOwn || $ids === []) {
            return [];
        }

        return SubmissionVisibility::ownedOrJoinedBy($viewer)
            ->whereKey($ids)
            ->get(['id'])
            ->map(fn (FormSubmission $s): int => $s->id)
            ->all();
    }
}
