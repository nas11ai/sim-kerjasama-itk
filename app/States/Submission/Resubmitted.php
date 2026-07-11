<?php

namespace App\States\Submission;

class Resubmitted extends SubmissionStatus
{
    public static string $name = 'resubmitted';

    public function key(): string
    {
        return self::$name;
    }

    public function label(): string
    {
        return 'Diajukan Ulang';
    }

    public function color(): string
    {
        return 'purple';
    }

    public function icon(): string
    {
        return 'RotateCcw';
    }

    public function variant(): string
    {
        return 'outline';
    }
}
