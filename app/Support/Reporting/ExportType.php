<?php

namespace App\Support\Reporting;

use App\Support\Reporting\Exports\AuditLogExport;
use App\Support\Reporting\Exports\BudgetDetailExport;
use App\Support\Reporting\Exports\ExportDefinition;
use App\Support\Reporting\Exports\MonevRecapExport;
use App\Support\Reporting\Exports\ResearchOutputListExport;
use App\Support\Reporting\Exports\SubmissionRecapExport;
use InvalidArgumentException;

/**
 * W154 — registry kelima jenis ekspor tabular DDD. Isi, filter, dan batas tiap jenis milik
 * `ExportDefinition`-nya (Replace Conditional with Polymorphism); menambah jenis = satu entri.
 */
final class ExportType
{
    public const SUBMISSIONS = 'submissions';

    public const BUDGET = 'budget';

    public const RESEARCH_OUTPUTS = 'research-outputs';

    public const MONEV = 'monev';

    public const AUDIT_LOG = 'audit-log';

    private const DEFINITIONS = [
        self::SUBMISSIONS => SubmissionRecapExport::class,
        self::BUDGET => BudgetDetailExport::class,
        self::RESEARCH_OUTPUTS => ResearchOutputListExport::class,
        self::MONEV => MonevRecapExport::class,
        self::AUDIT_LOG => AuditLogExport::class,
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public static function definition(string $type): ExportDefinition
    {
        $class = self::DEFINITIONS[$type] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Jenis ekspor \"{$type}\" tidak dikenal.");
        }

        return new $class;
    }

    /** Juga untuk baris cetak PDF di tabel yang sama (BR-RPT-11); nilai tak dikenal tampil apa adanya. */
    public static function label(string $type): string
    {
        if (isset(self::DEFINITIONS[$type])) {
            return self::definition($type)->label();
        }

        return in_array($type, PrintableDocument::all(), true) ? PrintableDocument::label($type) : $type;
    }
}
