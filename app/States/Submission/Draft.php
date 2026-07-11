<?php

namespace App\States\Submission;

use App\States\Submission\SubmissionStatus;

class Draft extends SubmissionStatus
{
    public static string $name = 'draft';

    public function key(): string
    {
        return self::$name;
    }

    public function label(): string
    {
        return 'Draft';
    }

    public function color(): string
    {
        return 'gray';
    }

    public function icon(): string
    {
        return 'FileText';
    }

    public function variant(): string
    {
        return 'secondary';
    }
}
