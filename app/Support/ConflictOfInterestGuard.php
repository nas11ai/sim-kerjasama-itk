<?php

namespace App\Support;

use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * BR-REV-02 — konflik kepentingan reviewer ↔ anggota, saling memblokir, kanonik
 * di BC Review; BR-SM-08 dan BR-IAM-07 membacanya dari sisi lain, jadi DDD
 * menuntut **satu implementasi, dua pemanggil**. Statis: guard bukan use case.
 */
final class ConflictOfInterestGuard
{
    /**
     * Apakah $user sudah memegang peran yang bertentangan pada $submission.
     */
    public static function conflicts(User $user, FormSubmission $submission): bool
    {
        // BR-SM-04 — ketua pengaju adalah pihak yang dinilai, bukan penilai.
        if ($submission->submitted_by === $user->id) {
            return true;
        }

        // `ResearchMember` menunjuk `UserProfile`, `SubmissionReviewer`
        // menunjuk `Reviewer`; keduanya diresolusi ke `User` lebih dulu karena
        // membandingkan id yang berbeda jenis akan selalu lolos.
        $asMember = $submission->researchMembers()
            ->where('status', 'active')
            ->whereHas('userProfile', fn ($q) => $q->where('user_id', $user->id))
            ->exists();

        if ($asMember) {
            return true;
        }

        return $submission->submissionReviewers()
            ->whereHas('reviewer', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
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
