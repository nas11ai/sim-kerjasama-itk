<?php

namespace App\States;

use App\States\SubmissionStatus;

class Submitted extends SubmissionStatus
{
    public static string $name = 'submitted';

    public function key(): string
    {
        return self::$name;
    }

    public function label(): string
    {
        return 'Menunggu Review';
    }

    public function color(): string
    {
        return 'yellow';
    }

    public function icon(): string
    {
        return 'Clock';
    }

    public function variant(): string
    {
        return 'outline';
    }
}
