<?php

use App\Console\Commands\NotifyActiveSubmissionPeriods;
use Illuminate\Support\Facades\Schedule;

Schedule::command(NotifyActiveSubmissionPeriods::class)
    ->hourly()
    ->withoutOverlapping();
