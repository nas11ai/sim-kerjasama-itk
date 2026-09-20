<?php

namespace App\Support\Reporting;

use App\Models\ResearchOutput;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Jenis ekspor yang didukung, beserta query-nya. Query-nya selalu berangkat
 * dari `SubmissionVisibility` — jenis ekspor tidak boleh menyusun batasnya
 * sendiri (BR-RPT-10); menambah jenis cukup satu entri, batasnya ikut.
 */
final class ExportType
{
    public const SUBMISSIONS = 'submissions';

    public const RESEARCH_OUTPUTS = 'research-outputs';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [self::SUBMISSIONS, self::RESEARCH_OUTPUTS];
    }

    public static function assertSupported(string $type): void
    {
        if (!in_array($type, self::all(), true)) {
            throw new InvalidArgumentException("Jenis ekspor \"{$type}\" tidak dikenal.");
        }
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::SUBMISSIONS => 'Rekap Submission',
            self::RESEARCH_OUTPUTS => 'Daftar Luaran',
            default => $type,
        };
    }

    /**
     * @return Builder<covariant Model>
     */
    public static function query(string $type, User $user): Builder
    {
        self::assertSupported($type);

        $submissions = SubmissionVisibility::for($user);

        return match ($type) {
            self::SUBMISSIONS => $submissions,
            // Luaran ikut batas induknya: yang tidak boleh dibaca pengajuannya
            // juga tidak boleh dibaca luarannya.
            self::RESEARCH_OUTPUTS => ResearchOutput::query()
                ->whereIn('form_submission_id', SubmissionVisibility::idsFor($user)),
            default => $submissions,
        };
    }
}
