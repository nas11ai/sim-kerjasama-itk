<?php

namespace App\States\Submission;

use App\States\Submission\SubmissionStatus;

class Rejected extends SubmissionStatus
{
    public static string $name = 'rejected';

    public function key(): string
    {
        return self::$name;
    }

    public function label(): string
    {
        return 'Ditolak';
    }

    public function color(): string
    {
        return 'red';
    }

    public function icon(): string
    {
        return 'XCircle';
    }

    public function variant(): string
    {
        return 'destructive';
    }
}
