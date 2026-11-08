<?php

namespace App\Support\Audit;

use App\Models\Announcement;
use App\Models\AuditEntry;
use App\Models\ExportJob;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\ResearchOutput;
use App\Models\Scheme;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * W155 — kolom Subjek Audit Log: "{konteks} — {nama}" seperti frame ("Submission — Biochar
 * Kaltim"). Subjek tanpa nama atau sudah terhapus → "{konteks} #{id}"; tipe lain → "{Kelas} #{id}".
 */
final class AuditSubjectLabel
{
    /** @var array<class-string<Model>, string> */
    private const CONTEXTS = [
        FormSubmission::class => 'Submission',
        User::class => 'User',
        UserProfile::class => 'Profil',
        SubmissionPeriod::class => 'Periode',
        Scheme::class => 'Skema',
        Form::class => 'Form',
        SubmissionReviewer::class => 'Penugasan Reviewer',
        ResearchOutput::class => 'Luaran',
        ExportJob::class => 'Ekspor',
        Announcement::class => 'Pengumuman',
    ];

    /**
     * Dipasang di `with(['subject' => AuditSubjectLabel::eager(...)])` supaya `for()` tidak
     * menembak query per baris.
     *
     * @param  Relation<*, *, *>  $subject
     */
    public static function eager(Relation $subject): void
    {
        if (!$subject instanceof MorphTo) {
            return;
        }

        $subject->morphWith([FormSubmission::class => ['form:id,title']]);
    }

    public static function for(AuditEntry $entry): string
    {
        $type = $entry->subject_type ?? '';
        $context = self::CONTEXTS[$type] ?? class_basename($type);
        $name = self::name($entry->subject);

        return $name === null ? "{$context} #{$entry->subject_id}" : "{$context} — {$name}";
    }

    private static function name(?Model $subject): ?string
    {
        return match (true) {
            $subject instanceof FormSubmission => $subject->form->title,
            $subject instanceof User, $subject instanceof SubmissionPeriod, $subject instanceof Scheme => $subject->name,
            $subject instanceof Form, $subject instanceof Announcement => $subject->title,
            default => null,
        };
    }
}
