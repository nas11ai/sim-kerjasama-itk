<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use App\Support\ConflictOfInterestGuard;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-08 — menambahkan anggota penelitian. Konflik kepentingannya dijaga `ConflictOfInterestGuard`,
 * guard yang sama dengan penugasan reviewer: "satu Policy, dua pemanggil" di DDD, karena larangannya
 * dua arah dan menuliskannya dua kali membuat keduanya menyimpang diam-diam.
 */
class AddResearchMember
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormSubmission $submission, User $candidate, string $role): ResearchMember
    {
        // Menangkap tiga hal sekaligus: kandidat adalah ketua pengaju,
        // sudah jadi anggota, atau sudah ditugaskan sebagai reviewer.
        ConflictOfInterestGuard::assertNone($candidate, $submission);

        $this->guardCapacity($submission);

        $profile = $candidate->userProfile;

        if ($profile === null) {
            throw ValidationException::withMessages([
                'user_id' => 'User ini belum melengkapi profil, jadi belum dapat ditambahkan sebagai anggota.',
            ]);
        }

        $tercatat = $submission->researchMembers()->create([
            'user_profile_id' => $profile->id,
            'role' => $role,
            'status' => 'active',
        ]);

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Anggota tim ditambahkan', $tercatat);

        return $tercatat;
    }

    /**
     * Batas anggota berasal dari skema. Skema yang tidak menyatakannya tidak
     * membatasi — sejajar dengan `allowed_output_types` di BR-SM-14.
     *
     * @throws ValidationException
     */
    private function guardCapacity(FormSubmission $submission): void
    {
        $max = $submission->scheme?->max_members;

        if (!is_int($max) || $max <= 0) {
            return;
        }

        // Anggota non-aktif tidak memakai kuota: ia sudah keluar.
        $active = $submission->researchMembers()->where('status', 'active')->count();

        if ($active < $max) {
            return;
        }

        throw ValidationException::withMessages([
            'user_id' => "Jumlah anggota sudah mencapai batas skema ({$max} orang).",
        ]);
    }
}
