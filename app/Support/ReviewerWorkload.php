<?php

namespace App\Support;

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;

/**
 * BR-REV-04 — beban reviewer. Melebihi `scheme.rules.max_reviewer_workload`
 * memberi **peringatan, bukan penolakan**: penolakan keras melumpuhkan Operator
 * di masa sibuk. Kelas skenarionya karena itu `@boundary`, bukan `@denial`.
 */
final class ReviewerWorkload
{
    /**
     * Peringatan yang perlu dilihat Operator, atau null kalau beban wajar —
     * dipisahkan dari `AssignReviewer` supaya bedanya terlihat di struktur:
     * guard melempar, ini tidak.
     */
    public static function warningFor(FormSubmission $submission, Reviewer $reviewer): ?string
    {
        $max = self::limitFor($submission);

        if ($max === null) {
            return null;
        }

        $active = SubmissionReviewer::query()
            ->where('reviewer_id', $reviewer->id)
            ->where('status', 'active')
            ->count();

        if ($active < $max) {
            return null;
        }

        return "Reviewer ini sedang memegang {$active} penugasan aktif, melebihi batas {$max} yang ditetapkan skema. Penugasan tetap dapat dilanjutkan.";
    }

    /** Skema yang tidak menyatakan batasnya tidak membatasi apa pun. */
    public static function limitFor(FormSubmission $submission): ?int
    {
        $rules = $submission->scheme?->rules;

        if (!is_array($rules)) {
            return null;
        }

        $max = $rules['max_reviewer_workload'] ?? null;

        return is_int($max) && $max > 0 ? $max : null;
    }
}
