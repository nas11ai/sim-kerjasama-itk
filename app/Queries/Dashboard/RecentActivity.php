<?php

namespace App\Queries\Dashboard;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Dashboard\ActivityData;
use App\Models\AuditEntry;
use App\Models\User;
use App\Support\Audit\AuditScope;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * "Aktivitas Terbaru" (W44) — lima entri teratas jejak audit dalam cakupan pembacanya
 * (BR-RPT-05). Satu cakupan dengan layar Audit Log, tanpa aturan baru.
 *
 * @method static list<ActivityData> run(User $user)
 */
class RecentActivity implements ChangesNoState
{
    use AsAction;

    private const LIMIT = 5;

    /** @return list<ActivityData> */
    public function handle(User $user): array
    {
        return array_values(AuditScope::forUser($user)
            ->with('causer')
            ->latest()
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(static fn (AuditEntry $entry): ActivityData => new ActivityData(
                description: $entry->description,
                actorName: $entry->causer instanceof User ? $entry->causer->name : null,
                at: $entry->created_at?->toIso8601String() ?? '',
            ))
            ->all());
    }
}
