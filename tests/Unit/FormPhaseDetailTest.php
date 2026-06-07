<?php

use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;

it('returns false when submission period is force closed', function () {

    $period = new SubmissionPeriod;
    $period->is_force_closed = true;

    $formPhase = new FormPhase;
    $formPhase->setRelation('submissionPeriod', $period);

    $submissionDate = new SubmissionDate;
    $submissionDate->datetime = now()->addDays(3);

    $detail = new FormPhaseDetail;

    $detail->setRelation('formPhase', $formPhase);
    $detail->setRelation('submissionDate', $submissionDate);

    expect($detail->isWithinDeadline())->toBeFalse();
});
