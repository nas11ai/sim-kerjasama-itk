<?php

namespace App\Models;

use App\Enums\DeadlineType;
use App\Queries\Period\SubmissionPeriodReferences;
use App\Support\Lifecycle\ReferenceGuard;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property bool $is_active
 * @property string $status
 * @property bool $is_force_closed
 * @property Carbon|string|null $start_date
 * @property Carbon|string|null $end_date
 * @property int|null $days_remaining
 * @property int $user_submissions_count
 * @property int $user_draft_count
 * @property int $user_submitted_count
 * @property int $total_submissions
 * @property int $approved_submissions
 * @property int $pending_review
 * @property int $submitted_count hasil withCount SubmissionPeriodList
 * @property-read Collection<int, SubmissionDate> $submissionDates
 * @property-read Collection<int, SubmissionPeriodPhase> $submissionPeriodPhases
 */
class SubmissionPeriod extends Model
{
    use HasFactory;

    /**
     * BR-FE-21 — perluasan BR-FE-19 ke periode: periode yang dipakai pengajuan tidak dihapus.
     */
    protected static function booted(): void
    {
        static::deleting(function (SubmissionPeriod $period): void {
            ReferenceGuard::assertUnreferenced('Periode pengajuan', SubmissionPeriodReferences::run($period));
        });
    }

    protected $fillable = ['name', 'is_force_closed'];

    protected $casts = [
        'is_force_closed' => 'boolean',
    ];

    /** @return HasMany<SubmissionDate, $this> */
    public function submissionDates(): HasMany
    {
        return $this->hasMany(SubmissionDate::class);
    }

    /**
     * BR-FE-26 (ADR 0019) — satu-satunya pintu dari (langkah, jenis) ke tanggal kalender periode
     * ini. `submit`/`output` = jenis + siklus langkah; Batas Evaluasi dan Batas Revisi pada siklus
     * langkah; Batas Revisi yang tak ada kembali ke tanggal kerja langkah. Null = belum diatur.
     */
    public function deadlineFor(FormPhaseDetail $step, DeadlineType $type): ?SubmissionDate
    {
        $own = $this->calendarDate($step->deadline_type, $step->deadline_cycle);

        return match ($type) {
            DeadlineType::Submit, DeadlineType::Output => $own,
            DeadlineType::Evaluation => $this->calendarDate(DeadlineType::Evaluation, $step->deadline_cycle),
            DeadlineType::Revision => $this->calendarDate(DeadlineType::Revision, $step->deadline_cycle) ?? $own,
        };
    }

    /**
     * BR-FE-25 — paling banyak satu tanggal per (jenis, siklus); dibaca dari relasi yang dimuat.
     * Tanpa jatuh-balik Batas Revisi: cek urutan BR-FE-36 membandingkan tanggal yang benar-benar ada.
     */
    public function calendarDate(DeadlineType $type, ?int $cycle): ?SubmissionDate
    {
        $this->loadMissing('submissionDates');

        return $this->submissionDates->first(
            static fn (SubmissionDate $date): bool => $date->type === $type && $date->cycle === $cycle,
        );
    }

    /** @return HasMany<FormSubmission, $this> */
    public function formSubmissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /** @return HasMany<SubmissionPeriodPhase, $this> */
    public function submissionPeriodPhases(): HasMany
    {
        return $this->hasMany(SubmissionPeriodPhase::class);
    }

    /** @return HasMany<PhaseVersionPin, $this> BR-FE-32 — versi struktur tiap angkatan periode ini */
    public function phaseVersionPins(): HasMany
    {
        return $this->hasMany(PhaseVersionPin::class);
    }
}
