<?php

namespace App\States\Submission;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class SubmissionStatus extends State
{
     abstract public function key(): string;
    abstract public function label(): string;
    abstract public function color(): string;
    abstract public function icon(): string;
    abstract public function variant(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Submitted::class)
            ->allowTransition(Submitted::class, UnderReview::class)
            ->allowTransition(UnderReview::class, NeedsRevision::class)
            ->allowTransition(UnderReview::class, Approved::class)
            ->allowTransition(UnderReview::class, Rejected::class)
            ->allowTransition(NeedsRevision::class, Resubmitted::class)
            ->allowTransition(Resubmitted::class, UnderReview::class)
            ->allowTransition(Approved::class, Withdrawn::class);
    }

    public static function options(): array
    {
        return [
            Draft::class => 'Draft',
            Submitted::class => 'Submitted',
            UnderReview::class => 'Under Review',
            NeedsRevision::class => 'Needs Revision',
            Resubmitted::class => 'Resubmitted',
            Approved::class => 'Approved',
            Rejected::class => 'Rejected',
            Withdrawn::class => 'Withdrawn',
        ];
    }
}
