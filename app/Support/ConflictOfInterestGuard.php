<?php

namespace App\Support;

use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * BR-REV-02 — konflik kepentingan reviewer ↔ anggota, saling memblokir.
 *
 * Kanonik di BC Review. Sisi anggota dinyatakan BR-SM-08, pembatasan per-role
 * dinyatakan BR-IAM-07 — ketiganya aturan yang sama dibaca dari tiga sisi, dan
 * DDD mewajibkan **satu implementasi, dua pemanggil**: penugasan reviewer dan
 * penambahan anggota memanggil guard yang sama.
 *
 * Yang membuat aturan ini tidak sesepele kelihatannya: kedua sisi memakai
 * referensi identitas berbeda — `SubmissionReviewer` menunjuk `Reviewer`,
 * `ResearchMember` menunjuk `UserProfile`. Keduanya diresolusi ke `User`
 * lebih dulu, karena membandingkan id yang berbeda jenis akan selalu lolos.
 *
 * `final class` statis mengikuti preseden `RequiredFieldGuard`: tidak ada state
 * instance yang dipegang, dan sebuah guard bukan use case.
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
