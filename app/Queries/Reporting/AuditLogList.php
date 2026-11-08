<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Reporting\AuditFilters;
use App\Data\View\Reporting\AuditEntryRowData;
use App\Models\AuditEntry;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Audit\AuditActorLabel;
use App\Support\Audit\AuditScope;
use App\Support\Audit\AuditSubjectLabel;
use App\Support\Identity\RoleCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * OPERATOR-07-03 — Audit Log terfilter (W155) dalam cakupan `AuditScope` (BR-RPT-05). Tidak
 * ada aksi tulis: append-only (BR-RPT-04).
 *
 * @phpstan-type Option array{value: string, label: string}
 * @phpstan-type Result array{entries: LengthAwarePaginator<int, AuditEntryRowData>, actionOptions: list<Option>, actorOptions: list<Option>, rangeOptions: list<Option>, seesEverything: bool}
 *
 * @method static Result run(User $user, AuditFilters $filters)
 */
class AuditLogList implements ChangesNoState
{
    use AsAction;

    private const PER_PAGE = 25;

    /** @return Result */
    public function handle(User $user, AuditFilters $filters): array
    {
        $seesEverything = $user->can(AuditScope::READ_ALL_PERMISSION);
        $query = AuditScope::forUser($user)->with([
            'causer.roles',
            'subject' => AuditSubjectLabel::eager(...),
        ]);
        $this->filter($query, $filters);

        return [
            'entries' => $query->latest('id')
                ->paginate(self::PER_PAGE)
                ->withQueryString()
                ->through(fn (AuditEntry $entry): AuditEntryRowData => $this->row($entry, $seesEverything)),
            'actionOptions' => $this->actionOptions($user),
            'actorOptions' => [
                ...RoleCatalog::options(array_keys(RoleCatalog::LABELS)),
                ['value' => AuditFilters::SYSTEM_ACTOR, 'label' => AuditActorLabel::SYSTEM],
            ],
            'rangeOptions' => array_map(
                fn (string $value): array => ['value' => $value, 'label' => AuditFilters::RANGES[$value]['label']],
                array_keys(AuditFilters::RANGES),
            ),
            'seesEverything' => $seesEverything,
        ];
    }

    /** @param Builder<AuditEntry> $query */
    private function filter(Builder $query, AuditFilters $filters): void
    {
        $days = $filters->days();
        if ($days !== null) {
            $query->where('created_at', '>=', now()->subDays($days));
        }

        $query->when($filters->action !== '', fn (Builder $q) => $q->where('description', $filters->action));
        $query->when($filters->actor === AuditFilters::SYSTEM_ACTOR, fn (Builder $q) => $q->whereNull('causer_id'));
        $query->when(
            $filters->actor !== '' && $filters->actor !== AuditFilters::SYSTEM_ACTOR,
            fn (Builder $q) => $q->whereHasMorph('causer', [User::class], fn (Builder $causer) => $causer
                ->whereHas('roles', fn (Builder $role) => $role->where('name', $filters->actor))),
        );
        $this->search($query, $filters->search);
    }

    /**
     * PostgreSQL — whereLike tidak peka huruf: aksi, nama pelaku, atau judul form pengajuan.
     *
     * @param  Builder<AuditEntry>  $query
     */
    private function search(Builder $query, string $search): void
    {
        $query->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $any) => $any
            ->whereLike('description', "%{$search}%")
            ->orWhereHasMorph('causer', [User::class], fn (Builder $causer) => $causer->whereLike('name', "%{$search}%"))
            ->orWhereHasMorph('subject', [FormSubmission::class], fn (Builder $submission) => $submission
                ->whereHas('form', fn (Builder $form) => $form->whereLike('title', "%{$search}%")))));
    }

    private function row(AuditEntry $entry, bool $seesEverything): AuditEntryRowData
    {
        $properties = $entry->properties ?? [];

        return new AuditEntryRowData(
            id: $entry->id,
            createdAt: $entry->created_at?->toIso8601String() ?? '',
            subject: AuditSubjectLabel::for($entry),
            action: $entry->description,
            actor: AuditActorLabel::for($entry),
            ipAddress: $seesEverything ? $entry->ip_address : null,
            old: self::values($properties['old'] ?? null),
            new: self::values($properties['new'] ?? null),
        );
    }

    /** @return array<string, string|int|float|bool|null>|null */
    private static function values(mixed $values): ?array
    {
        if (!is_array($values)) {
            return null;
        }

        $flat = [];
        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $flat[$key] = is_scalar($value) || $value === null ? $value : (json_encode($value) ?: '');
        }

        return $flat;
    }

    /**
     * Deskripsi yang ada dalam cakupan penanya saja (RPT-AUDIT-05), urut abjad.
     *
     * @return list<Option>
     */
    private function actionOptions(User $user): array
    {
        $options = [];
        foreach (AuditScope::forUser($user)->distinct()->orderBy('description')->pluck('description') as $description) {
            if (is_string($description)) {
                $options[] = ['value' => $description, 'label' => $description];
            }
        }

        return $options;
    }
}
