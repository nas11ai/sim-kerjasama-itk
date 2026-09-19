<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-18 — mengeluarkan anggota penelitian.
 *
 * Pasangan AddResearchMember yang selama ini tidak ada. Ketiadaannya membuat
 * batas anggota skema (BR-SCH-03) jadi jalan buntu: layar menyuruh
 * "keluarkan anggota lain sebelum menambah", dan tidak ada yang bisa
 * mengeluarkannya.
 *
 * @method static void run(FormSubmission $submission, ResearchMember $member, User $actor)
 */
class RemoveResearchMember
{
    use AsAction;

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(FormSubmission $submission, ResearchMember $member, User $actor): void
    {
        if (!$actor->can('manageResearchMembers', $submission)) {
            throw new AuthorizationException(
                'Hanya ketua pengaju yang dapat mengeluarkan anggota, dan hanya selama pengajuan belum disetujui, ditolak, atau ditarik.'
            );
        }

        // BR-SM-07 — anggota milik pengajuan lain tidak ikut keluar hanya
        // karena id-nya ditebak benar.
        if ($member->form_submission_id !== $submission->id) {
            throw ValidationException::withMessages([
                'id' => 'Anggota ini bukan bagian dari pengajuan tersebut.',
            ]);
        }

        // BR-SM-18 — ketua pengaju tidak bisa mengeluarkan dirinya sendiri.
        // `submitted_by` adalah sumbu access control BR-SM-07, dan pengajuan
        // tanpa ketua tidak punya siapa pun yang berhak menyentuhnya. Yang
        // hendak melepas kepemilikan memakai jalur transfer (BR-SM-09).
        // `user_profile_id` NOT NULL dengan FK ke user_profiles, jadi relasinya
        // selalu terisi — nullsafe di sini hanya akan menyembunyikan data rusak.
        if ($member->userProfile->user_id === $submission->submitted_by) {
            throw ValidationException::withMessages([
                'id' => 'Ketua pengaju tidak dapat dikeluarkan dari pengajuannya sendiri. '
                    .'Pindahkan kepemilikan lebih dulu kalau ia memang harus keluar.',
            ]);
        }

        // Audit ditulis selagi barisnya masih terbaca: entri yang hanya
        // menyebut "seorang anggota keluar" tidak menjawab siapa.
        AuditTrail::record(
            'Anggota tim dikeluarkan',
            $member,
            $actor,
            ['user_profile_id' => $member->user_profile_id, 'role' => $member->role],
            null,
        );

        $member->delete();
    }
}
