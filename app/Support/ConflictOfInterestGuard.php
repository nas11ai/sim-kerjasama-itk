<?php

namespace App\Support;

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Validation\ValidationException;

/**
 * BR-REV-02 — konflik kepentingan reviewer ↔ anggota, saling memblokir,
 * kanonik di BC Review; sisi anggota BR-SM-08, pembatasan per-role BR-IAM-07.
 * DDD menuntut **satu implementasi, dua pemanggil**. Statis, bukan use case.
 */
final class ConflictOfInterestGuard
{
    /**
     * Apakah $user sudah memegang peran yang bertentangan pada $submission.
     */
    public static function conflicts(User $user, FormSubmission $submission): bool
    {
        return in_array($user->id, self::conflictingUserIds($submission), true);
    }

    /**
     * Seluruh user yang sudah berperan pada $submission — dipakai sekaligus oleh daftar kandidat
     * supaya tidak dua query per baris. `ResearchMember` menunjuk `UserProfile`, `SubmissionReviewer`
     * menunjuk `Reviewer`; keduanya diresolusi ke `User` karena id beda jenis selalu lolos.
     *
     * @return list<int>
     */
    public static function conflictingUserIds(FormSubmission $submission): array
    {
        // BR-SM-04 — ketua pengaju adalah pihak yang dinilai, bukan penilai.
        $members = UserProfile::query()
            ->whereHas('researchMembers', fn ($q) => $q
                ->where('form_submission_id', $submission->id)
                ->where('status', 'active'))
            ->get(['id', 'user_id'])
            ->map(fn (UserProfile $profile): int => $profile->user_id);

        $reviewers = Reviewer::query()
            ->whereHas('submissionReviewers', fn ($q) => $q->where('form_submission_id', $submission->id))
            ->get(['id', 'user_id'])
            ->map(fn (Reviewer $reviewer): int => $reviewer->user_id);

        return array_values(array_unique([
            $submission->submitted_by,
            ...$members->all(),
            ...$reviewers->all(),
        ]));
    }

    /**
     * @throws ValidationException
     */
    public static function assertNone(User $user, FormSubmission $submission): void
    {
        if (!self::conflicts($user, $submission)) {
            return;
        }

        throw ValidationException::withMessages([
            'user_id' => 'Orang ini sudah punya peran pada pengajuan tersebut, jadi tidak dapat ditugaskan sebagai reviewer maupun ditambahkan sebagai anggota.',
        ]);
    }
}
