<?php

namespace App\States\Submission;

class Approved extends SubmissionStatus
{
    public static string $name = 'approved';

    public function key(): string
    {
        return self::$name;
    }

    public function label(): string
    {
        return 'Disetujui';
    }

    public function color(): string
    {
        return 'green';
    }

    public function icon(): string
    {
        return 'CheckCircle';
    }

    public function variant(): string
    {
        return 'default';
    }
}
