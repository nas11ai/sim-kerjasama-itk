<?php

namespace App\Queries\Period;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\PeriodDateInput;
use App\Data\View\Period\ReadinessCheckData;
use App\Data\View\Period\ReadinessIssueData;
use App\Enums\DeadlineType;
use App\Enums\ReadinessCheck;
use App\Models\FormPhaseDetail;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;

/**
 * BR-FE-36 — tiga cek kesiapan satu angkatan atas data yang sudah dimuat CohortReadiness.
 * G4-W8: isu langkah Pengajuan dan urutan tanggal memblokir; isu langkah lain peringatan.
 */
final class CohortChecks implements ChangesNoState
{
    /**
     * @param  list<FormPhaseDetail>  $steps  langkah versi struktur angkatan, terurut
     * @param  array<int, true>  $withRequiredForm  id langkah yang punya Formulir Evaluasi wajib aktif
     */
    public function __construct(
        private readonly SubmissionPeriod $period,
        private readonly array $steps,
        private readonly array $withRequiredForm,
    ) {}

    /** @return list<ReadinessCheckData> urutan `ReadinessCheck::cases()` */
    public function all(): array
    {
        return [
            ReadinessCheckData::of(ReadinessCheck::MissingDate, $this->missingDates()),
            ReadinessCheckData::of(ReadinessCheck::DateOrder, $this->dateOrder()),
            ReadinessCheckData::of(ReadinessCheck::RequiredEvaluationForm, $this->requiredForms()),
        ];
    }

    /**
     * BR-FE-26 — satu isu per (jenis, siklus). G4-W8: memblokir bila dibaca langkah Pengajuan.
     *
     * @return list<ReadinessIssueData>
     */
    private function missingDates(): array
    {
        $issues = [];

        foreach ($this->steps as $step) {
            foreach (PeriodScheduleGaps::missingTypes($this->period, $step) as $type) {
                $key = PeriodDateInput::keyOf($type, $step->deadline_cycle);
                $blocking = $step->opensSubmission() || ($issues[$key]->blocking ?? false);
                $issues[$key] = new ReadinessIssueData($this->missingMessage($type, $step, $blocking), $blocking);
            }
        }

        return array_values($issues);
    }

    private function missingMessage(DeadlineType $type, FormPhaseDetail $step, bool $blocking): string
    {
        $label = $type->label($step->deadline_cycle);
        $stepLabel = $step->deadline_type->stepLabel($step->deadline_cycle);

        return match (true) {
            $blocking => "{$label} belum diatur.",
            $type === DeadlineType::Evaluation => "{$label} belum diatur, evaluasi {$stepLabel} tak bertenggat sampai tanggalnya diatur.",
            default => "{$label} belum diatur, langkah {$stepLabel} tertutup sampai tanggalnya diatur.",
        };
    }

    /**
     * BR-FE-17, G4-W5 — hanya antar tanggal yang ada; sama boleh. Selalu memblokir (G4-W8).
     *
     * @return list<ReadinessIssueData>
     */
    private function dateOrder(): array
    {
        $issues = [];

        foreach ([...$this->reviewPairs(), ...$this->cyclePairs()] as [$earlier, $later]) {
            if ($earlier !== null && $later !== null && $later->datetime->lt($earlier->datetime)) {
                $message = "{$later->label()} tidak boleh lebih awal dari {$earlier->label()}.";
                $issues[$message] = new ReadinessIssueData($message, blocking: true);
            }
        }

        return array_values($issues);
    }

    /**
     * Per siklus langkah yang direview: tanggal kerja ≤ Batas Evaluasi ≤ Batas Revisi; Batas Revisi
     * dibanding tanggal kerja bila Batas Evaluasi tidak ada.
     *
     * @return list<array{0: SubmissionDate|null, 1: SubmissionDate|null}>
     */
    private function reviewPairs(): array
    {
        $pairs = [];

        foreach ($this->steps as $step) {
            if (!$step->needs_review) {
                continue;
            }

            $work = $this->period->calendarDate($step->deadline_type, $step->deadline_cycle);
            $evaluation = $this->period->calendarDate(DeadlineType::Evaluation, $step->deadline_cycle);
            $pairs[] = [$work, $evaluation];
            $pairs[] = [$evaluation ?? $work, $this->period->calendarDate(DeadlineType::Revision, $step->deadline_cycle)];
        }

        return $pairs;
    }

    /**
     * Batas langkah `submit` berurutan siklus (utama dulu); siklus yang tanggalnya kosong dilompati.
     *
     * @return list<array{0: SubmissionDate, 1: SubmissionDate}>
     */
    private function cyclePairs(): array
    {
        $cycles = array_unique(array_map(
            static fn (FormPhaseDetail $step): int => $step->deadline_cycle ?? 0,
            array_filter($this->steps, static fn (FormPhaseDetail $step): bool => $step->deadline_type === DeadlineType::Submit),
        ));
        sort($cycles);
        $dates = array_values(array_filter(array_map(
            fn (int $cycle): ?SubmissionDate => $this->period->calendarDate(DeadlineType::Submit, $cycle === 0 ? null : $cycle),
            $cycles,
        )));

        $pairs = [];

        for ($i = 1; $i < count($dates); $i++) {
            $pairs[] = [$dates[$i - 1], $dates[$i]];
        }

        return $pairs;
    }

    /**
     * G4-W8 — memblokir di langkah Pengajuan, peringatan di langkah lain.
     *
     * @return list<ReadinessIssueData>
     */
    private function requiredForms(): array
    {
        $issues = [];

        foreach ($this->steps as $step) {
            if ($step->needs_review && !isset($this->withRequiredForm[$step->id])) {
                $stepLabel = $step->deadline_type->stepLabel($step->deadline_cycle);
                $issues[] = new ReadinessIssueData(
                    "Langkah {$step->order} ({$stepLabel}) dinilai, tapi belum punya Formulir Evaluasi wajib yang aktif.",
                    $step->opensSubmission(),
                );
            }
        }

        return $issues;
    }
}
