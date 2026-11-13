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
use App\Queries\Monev\MonevCycles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * W155 — kolom Subjek Audit Log: "{konteks} — {nama}" seperti frame ("Submission — Biochar
 * Kaltim", "Monev I — Smart Farming"). Tanpa nama/terhapus → "{konteks} #{id}".
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
     * E2E-07 — satu peta untuk label dan pencarian. Pengajuan dinamai lewat judul form
     * (induknya, bagi laporan monev), jadi ditangani terpisah.
     *
     * @var array<class-string<Model>, string>
     */
    private const NAME_COLUMNS = [
        User::class => 'name',
        SubmissionPeriod::class => 'name',
        Scheme::class => 'name',
        Form::class => 'title',
        Announcement::class => 'title',
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

        $subject->morphWith([FormSubmission::class => [
            'form:id,title',
            'parent.form:id,title',
            'formPhaseDetail.phaseType',
        ]]);
    }

    public static function for(AuditEntry $entry): string
    {
        $subject = $entry->subject;

        if ($subject instanceof FormSubmission && $subject->parent !== null && $subject->formPhaseDetail !== null) {
            // E2E-06 — laporan monev dinamai siklusnya, bukan "Submission".
            return MonevCycles::nameOf($subject->formPhaseDetail).' — '.$subject->parent->displayTitle();
        }

        $type = $entry->subject_type ?? '';
        $context = self::CONTEXTS[$type] ?? class_basename($type);
        $name = self::name($subject);

        return $name === null ? "{$context} #{$entry->subject_id}" : "{$context} — {$name}";
    }

    /**
     * E2E-07 — `orWhereHasMorph` per tipe bernama; dipanggil di dalam grup `where` pencarian.
     *
     * @param  Builder<AuditEntry>  $query
     * @return Builder<AuditEntry>
     */
    public static function searchable(Builder $query, string $pattern): Builder
    {
        foreach (self::NAME_COLUMNS as $type => $column) {
            $query->orWhereHasMorph('subject', [$type], fn (Builder $subject) => $subject->whereLike($column, $pattern));
        }

        // BR-SM-22 — judul pengajuan (atau induknya, untuk laporan monev) di samping judul Form.
        return $query->orWhereHasMorph('subject', [FormSubmission::class], fn (Builder $submission) => $submission
            ->whereLike('form_submissions.title', $pattern)
            ->orWhereHas('form', fn (Builder $form) => $form->whereLike('title', $pattern))
            ->orWhereHas('parent', fn (Builder $parent) => $parent->whereLike('title', $pattern))
            ->orWhereHas('parent.form', fn (Builder $form) => $form->whereLike('title', $pattern)));
    }

    private static function name(?Model $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        if ($subject instanceof FormSubmission) {
            return $subject->displayTitle();
        }

        $column = self::NAME_COLUMNS[$subject::class] ?? null;
        $name = $column === null ? null : $subject->getAttribute($column);

        return is_string($name) ? $name : null;
    }
}
