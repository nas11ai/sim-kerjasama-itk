<?php

use App\Actions\Period\ExtendSubmissionDates;
use App\Enums\DeadlineType;
use App\Models\FormPhaseDetail;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Queries\Period\SubmissionPeriodReferences;
use Illuminate\Database\QueryException;

// BR-FE-25 — jenis tenggat tetap × siklus menggantikan label teks bebas (W72, K8a).

it('derives the label from type and cycle', function (DeadlineType $type, ?int $cycle, string $label) {
    expect($type->label($cycle))->toBe($label);
})->with([
    [DeadlineType::Submit, null, 'Batas Submit'],
    [DeadlineType::Evaluation, null, 'Batas Evaluasi'],
    [DeadlineType::Revision, null, 'Batas Revisi'],
    [DeadlineType::Submit, 1, 'Batas Laporan Monev I'],
    [DeadlineType::Evaluation, 2, 'Batas Evaluasi Monev II'],
    [DeadlineType::Revision, 10, 'Batas Revisi Monev X'],
    [DeadlineType::Submit, 11, 'Batas Laporan Monev 11'],
]);

it('reads the label of a stored date from its type and cycle', function () {
    $date = SubmissionDate::create([
        'submission_period_id' => SubmissionPeriod::factory()->create()->id,
        'type' => DeadlineType::Evaluation,
        'cycle' => 1,
        'datetime' => now()->addWeek(),
    ]);

    expect($date->fresh()->type)->toBe(DeadlineType::Evaluation)
        ->and($date->fresh()->label())->toBe('Batas Evaluasi Monev I');
});

it('[FE-WINDOW-12] rejects a second date of the same type and cycle in one period', function (?int $cycle) {
    $period = SubmissionPeriod::factory()->create();
    $row = fn (string $at): array => [
        'submission_period_id' => $period->id,
        'type' => DeadlineType::Evaluation,
        'cycle' => $cycle,
        'datetime' => $at,
    ];

    SubmissionDate::create($row('2026-03-20 23:59:59'));

    expect(fn () => SubmissionDate::create($row('2026-03-25 23:59:59')))
        ->toThrow(QueryException::class);
})->with([
    'pengajuan utama (siklus kosong)' => [null],
    'Monev I' => [1],
]);

it('[FE-WINDOW-12] keeps the same type of another cycle or another period', function () {
    $period = SubmissionPeriod::factory()->create();
    $create = fn (SubmissionPeriod $p, ?int $cycle) => SubmissionDate::create([
        'submission_period_id' => $p->id,
        'type' => DeadlineType::Evaluation,
        'cycle' => $cycle,
        'datetime' => now()->addWeek(),
    ]);

    $create($period, null);
    $create($period, 1);
    $create(SubmissionPeriod::factory()->create(), null);

    expect(SubmissionDate::query()->where('type', DeadlineType::Evaluation)->count())->toBe(3);
});

it('rejects cycle zero', function () {
    expect(fn () => SubmissionDate::create([
        'submission_period_id' => SubmissionPeriod::factory()->create()->id,
        'type' => DeadlineType::Submit,
        'cycle' => 0,
        'datetime' => now()->addWeek(),
    ]))->toThrow(QueryException::class);
});

it('scopes dates by type and cycle', function () {
    $period = SubmissionPeriod::factory()->create();
    foreach ([[DeadlineType::Submit, null], [DeadlineType::Submit, 1], [DeadlineType::Evaluation, null]] as [$type, $cycle]) {
        SubmissionDate::create([
            'submission_period_id' => $period->id,
            'type' => $type,
            'cycle' => $cycle,
            'datetime' => now()->addWeek(),
        ]);
    }

    expect(SubmissionDate::query()->ofType(DeadlineType::Submit, null)->count())->toBe(1)
        ->and(SubmissionDate::query()->ofType(DeadlineType::Submit, 1)->count())->toBe(1);
});

// BR-FE-21 + BR-FE-26 — tanggal yang hanya menjadi Batas Evaluasi atau Batas Revisi sebuah
// detail tetap menggerbangi detail itu, jadi tetap terlindung seperti Batas Submit.
it('counts a date used only as an evaluation or revision deadline as a phase reference', function (string $column) {
    $period = SubmissionPeriod::factory()->create();
    $date = SubmissionDate::create([
        'submission_period_id' => $period->id,
        'type' => $column === 'evaluation_date_id' ? DeadlineType::Evaluation : DeadlineType::Revision,
        'datetime' => now()->addMonth(),
    ]);
    FormPhaseDetail::factory()->create([$column => $date->id]);

    expect(SubmissionPeriodReferences::run($period)['detail tahap yang memakai tanggalnya'])->toBe(1)
        ->and(SubmissionDate::query()->whereKey($date->id)->withoutPhaseDetail()->exists())->toBeFalse()
        ->and(SubmissionDate::query()->find($date->id)?->phaseDetailCount())->toBe(1);

    ExtendSubmissionDates::run($period, []);

    expect(SubmissionDate::query()->whereKey($date->id)->exists())->toBeTrue();
})->with(['evaluation_date_id', 'revision_date_id']);
