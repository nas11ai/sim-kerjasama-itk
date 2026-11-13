<?php

namespace App\Support\Reporting;

use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Approved;
use App\Support\ScoreVisibility;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * BR-RPT-11 — empat dokumen PDF yang didukung, beserta hak membacanya. Hak
 * mencetak **mengikuti hak membaca pengajuannya**: pintu sendiri berarti satu
 * tombol unduh melewati seluruh aturan keterlihatan M2, dan PDF-nya wajar.
 */
final class PrintableDocument
{
    public const SCORE_SHEET = 'lembar-penilaian';

    public const ACCEPTANCE_LETTER = 'surat-keterangan-diterima';

    public const RESEARCHER_RECAP = 'rekap-submission-peneliti';

    public const MONEV_REPORT = 'laporan-monev';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [
            self::SCORE_SHEET,
            self::ACCEPTANCE_LETTER,
            self::RESEARCHER_RECAP,
            self::MONEV_REPORT,
        ];
    }

    public static function assertSupported(string $document): void
    {
        if (!in_array($document, self::all(), true)) {
            throw new InvalidArgumentException("Dokumen \"{$document}\" tidak didukung.");
        }
    }

    public static function label(string $document): string
    {
        return match ($document) {
            self::SCORE_SHEET => 'Lembar Penilaian',
            self::ACCEPTANCE_LETTER => 'Surat Keterangan Diterima',
            self::RESEARCHER_RECAP => 'Rekap Submission per Peneliti',
            self::MONEV_REPORT => 'Laporan Monev',
            default => $document,
        };
    }

    /**
     * @throws AuthorizationException
     */
    public static function assertPrintable(string $document, User $user, FormSubmission $submission): void
    {
        self::assertSupported($document);

        // Sumbu pertama: hak membaca pengajuannya (BR-SM-07).
        if (!$submission->isAccessibleBy($user)) {
            throw new AuthorizationException(
                'Anda tidak berhak membaca pengajuan ini, jadi dokumennya juga tidak dapat dicetak.'
            );
        }

        // BR-RPT-13 — surat "diterima" hanya untuk pengajuan yang memang disetujui, juga bagi
        // Operator: surat yang terbit sebelum keputusan menyatakan keputusan yang belum ada.
        if ($document === self::ACCEPTANCE_LETTER && !$submission->status instanceof Approved) {
            throw new AuthorizationException(
                'Surat Keterangan Diterima dapat dicetak setelah pengajuan disetujui.'
            );
        }

        if ($document !== self::SCORE_SHEET) {
            return;
        }

        // Sumbu kedua, khusus Lembar Penilaian: cetak tidak membuka skor yang
        // masih ditahan BR-REV-12. Operator yang memang berhak membacanya tetap
        // bisa; yang ditahan pihak researcher sampai keputusan akhir.
        if (SubmissionVisibility::seesEverything($user)) {
            return;
        }

        if (ScoreVisibility::peerScoresVisibleTo($user, $submission)) {
            return;
        }

        if (!ScoreVisibility::visibleToResearcher($user, $submission)) {
            throw new AuthorizationException(
                'Lembar Penilaian dapat dicetak setelah pengajuan disetujui atau ditolak.'
            );
        }
    }
}
