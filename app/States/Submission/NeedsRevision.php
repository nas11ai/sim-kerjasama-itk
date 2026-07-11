<?php

namespace App\States\Submission;

class NeedsRevision extends SubmissionStatus
{
    public static string $name = 'needs_revision';

    public function key(): string
    {
        return self::$name;
    }

    public function label(): string
    {
        return 'Perlu Revisi';
    }

    public function color(): string
    {
        return 'orange';
    }

    public function icon(): string
    {
        return 'AlertCircle';
    }

    public function variant(): string
    {
        return 'outline';
    }
}
