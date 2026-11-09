<?php

namespace App\Queries\Search;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\SearchHitData;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * K2 — grup server command palette (topbar.md). Setiap grup di-scope izinnya sendiri dan
 * kosong, bukan 403, bagi user yang tidak berhak.
 *
 * @method static array{submissions: list<SearchHitData>, users: list<SearchHitData>, reviewers: list<SearchHitData>} run(User $viewer, string $term)
 */
class GlobalSearch implements ChangesNoState
{
    use AsAction;

    public const LIMIT = 5;

    /** @return array{submissions: list<SearchHitData>, users: list<SearchHitData>, reviewers: list<SearchHitData>} */
    public function handle(User $viewer, string $term): array
    {
        return [
            'submissions' => SearchSubmissions::run($viewer, $term),
            'users' => [],
            'reviewers' => [],
        ];
    }
}
