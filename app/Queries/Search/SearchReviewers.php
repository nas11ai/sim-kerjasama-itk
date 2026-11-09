<?php

namespace App\Queries\Search;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\SearchHitData;
use App\Enums\ReviewerStanding;
use App\Models\Reviewer;
use App\Models\User;
use App\Support\Identity\ReviewerTypeCatalog;
use App\Support\Search\LikePattern;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * K2 grup Reviewer — cocok nama, nama yang diawali istilah lebih dulu. Reviewer tidak punya
 * halaman detail, jadi hasilnya membuka daftar reviewer yang tersaring namanya (W173).
 *
 * @method static list<SearchHitData> run(User $viewer, string $term)
 */
class SearchReviewers implements ChangesNoState
{
    use AsAction;

    /** @return list<SearchHitData> */
    public function handle(User $viewer, string $term): array
    {
        // BR-IAM-11, BR-REV-09 — daftar reviewer milik pemegang reviewers.assign.
        if (!$viewer->can('reviewers.assign')) {
            return [];
        }

        return array_values(Reviewer::query()
            ->join('users', 'users.id', '=', 'reviewers.user_id')
            ->whereLike('users.name', LikePattern::contains($term))
            ->select('reviewers.*')
            // ReviewerStanding::of membaca status akun.
            ->with('user:id,name,is_active')
            ->orderByRaw('CASE WHEN users.name ILIKE ? THEN 0 ELSE 1 END', [LikePattern::startsWith($term)])
            ->orderBy('users.name')
            ->orderBy('reviewers.id')
            ->limit(GlobalSearch::LIMIT)
            ->get()
            ->map(fn (Reviewer $reviewer): SearchHitData => self::hit($reviewer))
            ->all());
    }

    private static function hit(Reviewer $reviewer): SearchHitData
    {
        $name = $reviewer->user->name ?? '';

        return new SearchHitData(
            id: $reviewer->id,
            label: $name,
            sublabel: ReviewerTypeCatalog::option($reviewer->reviewer_type)['label'].' · '.ReviewerStanding::of($reviewer)->label(),
            url: route('admin.reviewers.index', ['search' => $name]),
        );
    }
}
