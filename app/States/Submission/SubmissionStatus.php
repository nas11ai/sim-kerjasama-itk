<?php

namespace App\States\Submission;

use Spatie\ModelStates\State;

abstract class SubmissionStatus extends State
{
    abstract public function label(): string;
}
