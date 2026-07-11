<?php

namespace App\States\Submission;

use App\States\Submission\SubmissionStatus;

class Withdrawn extends SubmissionStatus
{
    public static string $name = 'withdrawn';

    public function key(): string
    {
        return self::$name;
    }

    public function label(): string
    {
        return 'Ditarik Kembali';
    }

    public function color(): string
    {
        return 'gray';
    }

    public function icon(): string
    {
        return 'Ban';
    }

    public function variant(): string
    {
        return 'secondary';
    }
}
