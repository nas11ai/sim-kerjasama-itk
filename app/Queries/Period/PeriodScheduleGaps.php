<?php

namespace App\Queries\Period;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\PeriodDateInput;
use App\Enums\DeadlineType;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\PhaseVersionPin;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Queries\Form\PhaseVersionFor;
use App\Support\SubmissionPeriodGate;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * BR-FE-26 (ADR 0019) — celah jadwal: tanggal yang dibutuhkan langkah fase tertaut tetapi belum ada
 * di kalender periode, untuk kartu fase dan tautan periode. Daftar per angkatan (memblokir atau
 * peringatan) milik CohortReadiness (BR-FE-36).
 */
class PeriodScheduleGaps implements ChangesNoState
{
    /**
     * Kartu fase ADMIN-08-08: per periode tertaut, banyak tanggal (bukan langkah) yang masih kurang.
     * Periode yang sudah tutup sepenuhnya hanya dikejar bila fase itu punya pengajuan di sana.
     *
     * @param  Collection<int, FormPhase>  $phases
     * @return array<int, list<array{period: string, count: int}>>
     */
    public static function forPhases(Collection $phases): array
    {
        $steps = FormPhaseDetail::query()
            ->whereIn('form_phase_id', $phases->pluck('id'))
            ->get();
        $links = SubmissionPeriodPhase::query()
            ->whereIn('form_phase_id', $phases->pluck('id'))
            ->with(['submissionPeriod.submissionDates', 'formPhase.latestVersion.formPhaseDetails'])
            ->get()
            ->sortBy(fn (SubmissionPeriodPhase $link): string => $link->submissionPeriod->name ?? '');

        $inUse = self::periodsInUse($steps);
        $pins = self::pinsOf($phases);
        $gaps = [];

        foreach ($links as $link) {
            $gap = self::gapAt($link, $inUse, $pins);

            if ($gap !== null) {
                $gaps[$link->form_phase_id][] = $gap;
            }
        }

        return $gaps;
    }

    /**
     * Celah satu tautan; null bila tidak ada, atau periodenya sudah tutup tanpa pengajuan fase itu.
     *
     * @param  array<string, true>  $inUse
     * @param  EloquentCollection<string, PhaseVersionPin>  $pins
     * @return array{period: string, count: int}|null
     */
    private static function gapAt(SubmissionPeriodPhase $link, array $inUse, EloquentCollection $pins): ?array
    {
        $period = $link->submissionPeriod;
        $phase = $link->formPhase;
        $key = "{$link->submission_period_id}:{$link->form_phase_id}";

        if ($period === null || $phase === null || (SubmissionPeriodGate::isFullyClosed($period) && !isset($inUse[$key]))) {
            return null;
        }

        $version = $pins->get($key)->formPhaseVersion ?? $phase->latestVersion;
        $count = self::missingDateCount($period, $version === null ? [] : $version->formPhaseDetails->all());

        return $count > 0 ? ['period' => $period->name, 'count' => $count] : null;
    }

    /**
     * BR-FE-32 — versi pakuan tiap angkatan, dimuat sekali untuk semua kartu.
     *
     * @param  Collection<int, FormPhase>  $phases
     * @return EloquentCollection<string, PhaseVersionPin> dikunci "periode:fase"
     */
    private static function pinsOf(Collection $phases): EloquentCollection
    {
        return PhaseVersionPin::query()
            ->whereIn('form_phase_id', $phases->pluck('id'))
            ->with('formPhaseVersion.formPhaseDetails')
            ->get()
            ->keyBy(fn (PhaseVersionPin $pin): string => "{$pin->submission_period_id}:{$pin->form_phase_id}");
    }

    /**
     * Pasangan "periode:fase" yang punya pengajuan (versi mana pun): angkatan berjalan masih butuh
     * tanggal monev walau semua tanggal periodenya lewat atau periodenya ditutup paksa.
     *
     * @param  EloquentCollection<int, FormPhaseDetail>  $steps
     * @return array<string, true>
     */
    private static function periodsInUse(EloquentCollection $steps): array
    {
        $pairs = [];
        $used = FormSubmission::query()
            ->whereIn('form_phase_detail_id', $steps->modelKeys())
            ->whereNotNull('submission_period_id')
            ->distinct()
            ->get(['submission_period_id', 'form_phase_detail_id']);

        foreach ($used as $submission) {
            $phaseId = $steps->find($submission->form_phase_detail_id)?->form_phase_id;
            $pairs["{$submission->submission_period_id}:{$phaseId}"] = true;
        }

        return $pairs;
    }

    /** Banyak tanggal yang masih kurang di `$period` bagi langkah `$phase`. */
    public static function missingCount(SubmissionPeriod $period, FormPhase $phase): int
    {
        return self::missingDateCount($period, self::cohortSteps($period, $phase));
    }

    /**
     * BR-FE-32 — langkah versi struktur angkatan (`$period` × `$phase`): pakuan, atau terbaru.
     *
     * @return list<FormPhaseDetail>
     */
    private static function cohortSteps(SubmissionPeriod $period, FormPhase $phase): array
    {
        $version = PhaseVersionFor::run($period, $phase);

        if ($version === null) {
            return [];
        }

        return array_values($version->formPhaseDetails()->get()->each(fn (FormPhaseDetail $step) => $step->setRelation('formPhase', $phase))->all());
    }

    /**
     * Dua langkah bersiklus sama membaca satu tanggal (FE-WINDOW-04), jadi dihitung per (jenis, siklus).
     *
     * @param  array<int, FormPhaseDetail>  $steps
     */
    private static function missingDateCount(SubmissionPeriod $period, array $steps): int
    {
        $keys = [];

        foreach ($steps as $step) {
            foreach (self::missingTypes($period, $step) as $type) {
                $keys[PeriodDateInput::keyOf($type, $step->deadline_cycle)] = true;
            }
        }

        return count($keys);
    }

    /**
     * Tanggal kerja langkah, ditambah Batas Evaluasi bila direview, yang belum ada di kalender `$period`.
     *
     * @return list<DeadlineType>
     */
    public static function missingTypes(SubmissionPeriod $period, FormPhaseDetail $step): array
    {
        $needed = $step->needs_review ? [$step->deadline_type, DeadlineType::Evaluation] : [$step->deadline_type];

        return array_values(array_filter(
            $needed,
            fn (DeadlineType $type): bool => $period->deadlineFor($step, $type) === null,
        ));
    }
}
