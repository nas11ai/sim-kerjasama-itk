<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Organization;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Seluruh unit di bawah sebuah unit beserta dirinya, aktif maupun nonaktif.
 *
 * @method static list<int> run(int $organizationId)
 */
class OrganizationSubtree implements ChangesNoState
{
    use AsAction;

    /** @return list<int> */
    public function handle(int $organizationId): array
    {
        // BR-IAM-08/17 — struktur, bukan rantai akses (W116): tanpa filter is_active, tanpa
        // cache. UNION (bukan UNION ALL) membuang baris berulang, jadi data bersiklus berhenti.
        $units = Organization::query()->fromQuery('
            WITH RECURSIVE structural_subtree AS (
                SELECT id FROM organizations WHERE id = ?
                UNION
                SELECT o.id FROM organizations o
                INNER JOIN structural_subtree s ON o.parent_id = s.id
            )
            SELECT id FROM structural_subtree
        ', [$organizationId]);

        return array_values($units->map(static fn (Organization $unit): int => $unit->id)->all());
    }
}
