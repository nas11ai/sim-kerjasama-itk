<?php

namespace App\Console\Commands;

use App\Services\EmailNotificationService;
use App\Models\FormSubmission;
use Illuminate\Console\Command;

class TestEmailNotifications extends Command
{
    protected $signature = 'test:email-notifications';
    protected $description = 'Test email notification system';

    public function handle(EmailNotificationService $emailService)
    {
        $this->info('Testing email notifications...');

        // Test dengan submission yang ada
        $submission = FormSubmission::first();

        if ($submission) {
            $emailService->notifyAdminFormSubmission($submission);
            $this->info('Test email sent!');
        } else {
            $this->error('No submission found for testing.');
        }

        return 0;
    }
}
