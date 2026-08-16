<?php

namespace App\Events;

use App\Models\FormSubmission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProposalSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly FormSubmission $submission) {}
}
