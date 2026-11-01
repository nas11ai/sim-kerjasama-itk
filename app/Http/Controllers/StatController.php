<?php

namespace App\Http\Controllers;

use App\Queries\Reporting\FormPhaseStatistics;
use App\Queries\Reporting\FormSubmissionStatistics;
use App\Queries\Reporting\SubmissionReviewerStatistics;
use App\Queries\Reporting\UserStatistics;
use Inertia\Inertia;
use Inertia\Response;

class StatController extends Controller
{
    public function formPhaseStatIndex(): Response
    {
        return Inertia::render('Statistics/FormPhaseStats', FormPhaseStatistics::run());
    }

    public function formSubmissionStatIndex(): Response
    {
        return Inertia::render('Statistics/FormSubmissionStats', FormSubmissionStatistics::run());
    }

    public function submissionReviewerStatIndex(): Response
    {
        return Inertia::render('Statistics/SubmissionReviewerStats', SubmissionReviewerStatistics::run());
    }

    public function userStatIndex(): Response
    {
        return Inertia::render('Statistics/UserStats', UserStatistics::run());
    }
}
