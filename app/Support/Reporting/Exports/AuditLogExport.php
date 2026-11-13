<?php

namespace App\Support\Reporting\Exports;

use App\Data\Reporting\ExportFilters;
use App\Models\AuditEntry;
use App\Models\User;
use App\Support\Audit\AuditActorLabel;
use App\Support\Audit\AuditScope;
use App\Support\Audit\AuditSubjectLabel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * W154 — Log Audit (DDD: siapa, aksi apa, kapan, dari IP mana) dalam cakupan `AuditScope`
 * peminta (BR-RPT-05, BR-RPT-10). Rentang tahun = tahun entri dicatat.
 */
final class AuditLogExport implements ExportDefinition
{
    public function label(): string
    {
        return 'Log Audit';
    }

    public function filterKeys(): array
    {
        return [ExportFilters::YEAR];
    }

    public function headings(): array
    {
        return ['Waktu', 'Subjek', 'Aksi', 'Pelaku', 'IP Address'];
    }

    public function rowCount(User $user, ExportFilters $filters): int
    {
        return $this->query($user, $filters)->count();
    }

    public function rows(User $user, ExportFilters $filters): array
    {
        $entries = $this->query($user, $filters)->with([
            'causer' => AuditActorLabel::eager(...),
            'causer.roles',
            'subject' => AuditSubjectLabel::eager(...),
        ])->latest('id')->get();
        // W155 — label dan aturan kolom IP sama dengan layar Log Audit (UX pattern 07).
        $seesEverything = $user->can(AuditScope::READ_ALL_PERMISSION);

        return array_values($entries->map(fn (AuditEntry $entry): array => [
            $entry->created_at?->format('Y-m-d H:i:s'),
            AuditSubjectLabel::for($entry),
            $entry->description,
            AuditActorLabel::for($entry),
            $seesEverything ? $entry->ip_address : null,
        ])->all());
    }

    /** @return Builder<AuditEntry> */
    private function query(User $user, ExportFilters $filters): Builder
    {
        $query = AuditScope::forUser($user);
        [$from, $to] = [$filters->yearFrom, $filters->yearTo];

        if ($from !== null) {
            $query->where('created_at', '>=', CarbonImmutable::createMidnightDate($from, 1, 1));
        }

        if ($to !== null) {
            $query->where('created_at', '<', CarbonImmutable::createMidnightDate($to + 1, 1, 1));
        }

        return $query;
    }
}
