<?php

namespace App\States;

use App\States\SubmissionStatus;

class UnderReview extends SubmissionStatus
{
    public static string $name = 'under_review';

    public function key(): string
    {
        return self::$name;
    }

    public function label(): string
    {
        return 'Sedang Direview';
    }

    public function color(): string
    {
        return 'blue';
    }

    public function icon(): string
    {
        return 'Search';
    }

    public function variant(): string
    {
        return 'secondary';
    }
}
