<?php

use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use Spatie\Activitylog\Models\Activity;

function submissionWithStatus(string $stateClass): FormSubmission
{
    return FormSubmission::factory()
        ->state([
            'submitted_by' => User::factory(),
            'status' => $stateClass,
        ])
        ->create();
}

test('edit budget line item saat NEEDS_REVISION tercatat di audit trail dengan old dan new', function () {
    $submission = submissionWithStatus(NeedsRevision::class);

    $item = BudgetLineItem::factory()->for($submission)->create([
        'item_name' => 'Kertas A4',
        'volume' => 2,
        'unit_price' => 50_000,
        'total' => 100_000,
    ]);

    $item->update([
        'volume' => 5,
        'unit_price' => 60_000,
        'total' => 300_000,
    ]);

    $activity = Activity::inLog('budget')
        ->where('description', 'budget_line_item_changed')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_id)->toBe($item->id)
        ->and($activity->properties['old'])->toMatchArray([
            'item_name' => 'Kertas A4',
            'volume' => 2,
            'unit_price' => 50_000,
            'total' => 100_000,
        ])
        ->and($activity->properties['new'])->toMatchArray([
            'volume' => 5,
            'unit_price' => 60_000,
            'total' => 300_000,
        ]);
});

test('edit budget line item saat status bukan NEEDS_REVISION tidak ter-log', function () {
    $submission = submissionWithStatus(Draft::class);

    $item = BudgetLineItem::factory()->for($submission)->create();

    $item->update([
        'unit_price' => 999_999,
        'total' => $item->volume * 999_999,
    ]);

    expect(Activity::inLog('budget')->count())->toBe(0);
});

test('membuat budget line item baru tidak memicu audit trail', function () {
    $submission = submissionWithStatus(NeedsRevision::class);

    BudgetLineItem::factory()->for($submission)->create();

    expect(Activity::inLog('budget')->count())->toBe(0);
});
