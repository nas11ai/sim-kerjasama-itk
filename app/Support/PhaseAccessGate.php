<?php

namespace App\Support;

use App\Enums\DeadlineType;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\FormSubmissionOverride;
use App\Models\SubmissionDate;
use App\States\Submission\NeedsRevision;
use Illuminate\Support\Carbon;

/**
 * BR-FE-08, BR-FE-10, BR-SCH-07 — apakah satu pengajuan masih boleh mengisi
 * satu FormPhaseDetail. Dua sumbu: jendela fase yang berlaku untuk semua
 * pengajuan di fase itu, dan override Operator untuk satu pengajuan saja.
 */
final class PhaseAccessGate
{
    public static function isOpenFor(FormSubmission $submission, ?FormPhaseDetail $detail): bool
    {
        // Pengajuan yang belum tertaut fase tidak punya batas untuk ditegakkan.
        // Jalur wizard sudah gagal keras dengan 409 kalau detailnya tak bisa
        // diresolusi (ADR 0014), jadi null di sini data lama, bukan celah baru.
        if ($detail === null) {
            return true;
        }

        // BR-FE-26 — tanggal dibaca dari kalender periode pengajuan. Pengajuan bertahap tanpa
        // periode mustahil (constraint `form_submissions_detail_has_period`); bila terjadi,
        // tidak ada tanggal yang bisa membukanya — hanya override.
        $period = $submission->submissionPeriod;

        // BR-SCH-07 — hard block, tidak bisa ditembus override: override izin Operator untuk
        // satu orang, force close keputusannya menghentikan semua.
        if ($period !== null && SubmissionPeriodGate::isForceClosed($period, $detail)) {
            return false;
        }

        if ($period !== null && $detail->isWithinDeadline($period, self::typeFor($submission))) {
            return true;
        }

        return self::activeOverride($submission, $detail) !== null;
    }

    /**
     * BR-FE-26 (K8c) — tenggat pengisi di kalender periode pengajuan: Batas Revisi selama
     * NEEDS_REVISION (atau tanggal kerja langkah bila tak ada), selain itu tanggal kerja langkah.
     * Reviewer digerbangi Batas Evaluasi lewat penugasannya (EvaluationDueDate).
     */
    public static function deadlineFor(FormSubmission $submission, FormPhaseDetail $detail): ?SubmissionDate
    {
        return $submission->submissionPeriod?->deadlineFor($detail, self::typeFor($submission));
    }

    private static function typeFor(FormSubmission $submission): DeadlineType
    {
        return $submission->status?->equals(NeedsRevision::class) === true ? DeadlineType::Revision : DeadlineType::Submit;
    }

    /**
     * BR-FE-08 — penolakannya menyebut jalan keluarnya. Peneliti yang cuma
     * dibilang "403" tidak tahu bahwa perpanjangan memang bisa diminta.
     */
    public static function assertOpenFor(FormSubmission $submission, ?FormPhaseDetail $detail): void
    {
        if (self::isOpenFor($submission, $detail)) {
            return;
        }

        // BR-FE-26 — tanggal yang belum diatur bukan tanggal yang lewat: yang harus bertindak
        // Operator (mengisi kalender), bukan peneliti (meminta perpanjangan).
        if ($detail !== null && self::deadlineFor($submission, $detail) === null) {
            $name = $detail->deadline_type->stepLabel($detail->deadline_cycle);

            abort(403, "Jadwal {$name} belum diatur di periode ini. Hubungi Operator LPPM.");
        }

        abort(403, 'Batas waktu fase ini sudah lewat. Hubungi Operator LPPM untuk meminta perpanjangan.');
    }

    /**
     * Bentuk yang dikirim ke layar. BR-FE-15 — fase yang lewat batas tetap
     * tampil dengan `is_open` false, bukan hilang dari payload. BR-FE-16 —
     * peneliti melihat perpanjangan ada dan sampai kapan, bukan alasannya.
     *
     * @return array{is_open: bool, deadline: string|null, form_phase_detail_id: int|null, override: array{id: int, status: string, expires_at: string|null, reason: string|null}|null}
     */
    public static function presentFor(
        FormSubmission $submission,
        ?FormPhaseDetail $detail,
        bool $canSeeReason
    ): array {
        $override = $detail === null ? null : self::activeOverride($submission, $detail);
        $latest = $detail === null ? null : self::latestOverride($submission, $detail);

        return [
            'is_open' => self::isOpenFor($submission, $detail),
            'deadline' => $detail === null ? null : self::deadlineFor($submission, $detail)?->datetime->toDateTimeString(),
            'form_phase_detail_id' => $detail?->id,
            'override' => $latest === null ? null : [
                'id' => $latest->id,
                'status' => $override === null ? 'expired' : 'active',
                'expires_at' => $latest->expires_at?->toDateTimeString(),
                'reason' => $canSeeReason ? $latest->reason : null,
            ],
        ];
    }

    /**
     * Override terakhir apa pun keadaannya — dipakai layar untuk menunjukkan
     * bahwa perpanjangan pernah ada dan sekarang sudah habis.
     */
    public static function latestOverride(
        FormSubmission $submission,
        FormPhaseDetail $detail
    ): ?FormSubmissionOverride {
        return FormSubmissionOverride::query()
            ->where('form_submission_id', $submission->id)
            ->where('form_phase_detail_id', $detail->id)
            ->latest('id')
            ->first();
    }

    /**
     * Override yang masih berlaku untuk pasangan pengajuan+fase ini. BR-FE-10 —
     * izin ini tidak pernah melebar ke fase atau pengajuan lain. Dicabut dan
     * kedaluwarsa sama-sama tak berlaku, disaring di query bukan di PHP.
     */
    public static function activeOverride(
        FormSubmission $submission,
        FormPhaseDetail $detail
    ): ?FormSubmissionOverride {
        return FormSubmissionOverride::query()
            ->where('form_submission_id', $submission->id)
            ->where('form_phase_detail_id', $detail->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            })
            ->latest('id')
            ->first();
    }
}
