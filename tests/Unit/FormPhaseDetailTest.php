<?php

namespace Tests\Feature;

use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\SubmissionPeriod;
use Tests\TestCase;

class FormPhaseDetailTest extends TestCase
{
    public function test_is_within_deadline_when_active()
    {
        $submissionPeriod = new SubmissionPeriod();
        $submissionPeriod->is_force_closed = false;

        $formPhase = new FormPhase();
        $formPhase->setRelation('submissionPeriod', $submissionPeriod);

        $formPhaseDetail = new FormPhaseDetail();
        $formPhaseDetail->submissionDate = (object) [
            'datetime' => now()->addDays(2)
        ];
        $formPhaseDetail->setRelation('formPhase', $formPhase);

        $this->assertTrue($formPhaseDetail->isWithinDeadline());
    }

    public function test_is_within_deadline_when_force_closed()
    {
        $submissionPeriod = new SubmissionPeriod();
        $submissionPeriod->is_force_closed = true;

        $formPhase = new FormPhase();
        $formPhase->setRelation('submissionPeriod', $submissionPeriod);

        $formPhaseDetail = new FormPhaseDetail();
        $formPhaseDetail->submissionDate = (object) [
            'datetime' => now()->addDays(2)
        ];
        $formPhaseDetail->setRelation('formPhase', $formPhase);

        $this->assertFalse($formPhaseDetail->isWithinDeadline());
    }
}
