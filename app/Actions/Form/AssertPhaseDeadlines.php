<?php

namespace App\Actions\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\FormPhaseDetailInput;
use App\Enums\DeadlineType;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\SubmissionDate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-17 + BR-FE-26 (ADR 0014) — tenggat detail tahap: jenisnya cocok dengan kolomnya,
 * ketiganya satu periode dan satu siklus, dan tenggat baru atau yang dipindah milik periode
 * yang memakai tahap itu. Hanya membaca dan menolak, **tidak menulis audit (BR-RPT-09).**
 *
 * @method static void run(?FormPhase $phase, list<FormPhaseDetailInput> $details, array<int, FormPhaseDetail> $current = [])
 */
class AssertPhaseDeadlines implements ChangesNoState
{
    use AsAction;

    private const COLUMNS = [
        'submission_date_id' => DeadlineType::Submit,
        'evaluation_date_id' => DeadlineType::Evaluation,
        'revision_date_id' => DeadlineType::Revision,
    ];

    /**
     * @param  FormPhase|null  $phase  null saat tahap belum ada
     * @param  list<FormPhaseDetailInput>  $details
     * @param  array<int, FormPhaseDetail>  $current  detail lama per id
     *
     * @throws ValidationException
     */
    public function handle(?FormPhase $phase, array $details, array $current = []): void
    {
        $effective = array_map(fn (FormPhaseDetailInput $d): array => $this->effective($d, $current[$d->id ?? 0] ?? null), $details);
        $dates = $this->dates($effective);
        $selectable = $this->selectable($phase, $details, $current);

        $errors = [];

        foreach ($details as $index => $detail) {
            $errors += $this->bindingErrors($index, $detail->needsReview, $effective[$index], $dates);
            $errors += $this->periodErrors($index, $detail, $current[$detail->id ?? 0] ?? null, $selectable);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Tenggat yang berlaku sesudah simpan: null/absen mempertahankan nilai detail lama.
     *
     * @return array<string, int|null>
     */
    private function effective(FormPhaseDetailInput $detail, ?FormPhaseDetail $current): array
    {
        $posted = $detail->deadlineIds();

        return array_map(
            fn (string $column): ?int => $posted[$column] ?? $this->currentId($current, $column),
            array_combine(array_keys(self::COLUMNS), array_keys(self::COLUMNS)),
        );
    }

    private function currentId(?FormPhaseDetail $current, string $column): ?int
    {
        return $current === null ? null : filter_var($current->getAttribute($column), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    }

    /**
     * @param  list<array<string, int|null>>  $effective
     * @return Collection<int, SubmissionDate>
     */
    private function dates(array $effective): Collection
    {
        $ids = [];

        foreach ($effective as $row) {
            array_push($ids, ...array_values(array_filter($row)));
        }

        return SubmissionDate::query()->whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * BR-FE-26 — jenis per kolom, Batas Evaluasi wajib bila direview, satu periode dan siklus.
     *
     * @param  array<string, int|null>  $ids
     * @param  Collection<int, SubmissionDate>  $dates
     * @return array<string, string>
     */
    private function bindingErrors(int $index, bool $needsReview, array $ids, Collection $dates): array
    {
        $errors = [];
        $submit = $dates->get($ids['submission_date_id'] ?? 0);

        if ($needsReview && $ids['evaluation_date_id'] === null) {
            $errors["phase_details.{$index}.evaluation_date_id"] = 'Detail yang direview wajib punya Batas Evaluasi.';
        }

        foreach (self::COLUMNS as $column => $type) {
            $date = $dates->get($ids[$column] ?? 0);
            $message = $date === null ? null : $this->mismatch($date, $type, $submit);

            if ($message !== null) {
                $errors["phase_details.{$index}.{$column}"] = $message;
            }
        }

        return $errors;
    }

    private function mismatch(SubmissionDate $date, DeadlineType $type, ?SubmissionDate $submit): ?string
    {
        if ($date->type !== $type) {
            return "Tenggat ini harus berjenis {$type->label()}.";
        }

        if ($submit === null || ($date->submission_period_id === $submit->submission_period_id && $date->cycle === $submit->cycle)) {
            return null;
        }

        return 'Tenggat ini harus satu periode dan satu siklus dengan Batas Submit detailnya.';
    }

    /**
     * BR-FE-17 (ADR 0014) — id tenggat baru atau yang dipindah yang boleh dipakai tahap ini.
     *
     * @param  list<FormPhaseDetailInput>  $details
     * @param  array<int, FormPhaseDetail>  $current
     * @return list<int>
     */
    private function selectable(?FormPhase $phase, array $details, array $current): array
    {
        $moved = [];

        foreach ($details as $detail) {
            foreach ($detail->deadlineIds() as $column => $id) {
                if ($id !== null && $id !== $this->currentId($current[$detail->id ?? 0] ?? null, $column)) {
                    $moved[] = $id;
                }
            }
        }

        if ($moved === []) {
            return [];
        }

        return array_values(SubmissionDate::query()->selectableFor($phase)->whereIn('id', $moved)->get(['id'])
            ->map(fn (SubmissionDate $date): int => $date->id)
            ->all());
    }

    /**
     * @param  list<int>  $selectable
     * @return array<string, string>
     */
    private function periodErrors(int $index, FormPhaseDetailInput $detail, ?FormPhaseDetail $current, array $selectable): array
    {
        $errors = [];

        foreach ($detail->deadlineIds() as $column => $id) {
            if ($id === null || $id === $this->currentId($current, $column) || in_array($id, $selectable, true)) {
                continue;
            }

            $errors["phase_details.{$index}.{$column}"] = 'Tenggat harus milik periode yang '
                .'memakai tahap ini, atau periode yang belum ditutup paksa bila belum ada.';
        }

        return $errors;
    }
}
