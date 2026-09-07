<?php

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProfile>
 */
class UserProfileFactory extends Factory
{
    protected $model = UserProfile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        // Organization tidak punya factory sendiri (model-nya tidak pakai
        // HasFactory), jadi dibuat langsung lewat create(), sama seperti pola
        // di FormPhaseDetailFactory dan AddRelationsToFormSubmissionsTest.
        $organization = Organization::create([
            'name' => 'Organisasi Uji '.fake()->unique()->numerify('####-####'),
            'type' => 'study_program',
        ]);

        return [
            'user_id' => User::factory(),
            'organization_id' => $organization->id,
            'role_id' => null,
            // Profil bawaan sengaja **lengkap dan terverifikasi**: itulah
            // keadaan peneliti yang sudah berjalan, dan mayoritas test bicara
            // soal pengajuan, bukan soal onboarding. Test yang memang menguji
            // gerbang BR-IAM-03/04 memakai state di bawah.
            'nidn' => fake()->unique()->numerify('##########'),
            'verification_status' => VerificationStatus::Active->value,
            'phone' => '0813-'.fake()->numerify('####-####'),
            'expertise' => 'Bidang Uji',
            'photo_path' => 'profil/uji.jpg',
        ];
    }

    /** BR-IAM-04 — akun yang masih menunggu verifikasi NIDN. */
    public function pending(): static
    {
        return $this->state(fn (): array => [
            'verification_status' => VerificationStatus::Pending->value,
        ]);
    }

    /** BR-IAM-13 — akun yang verifikasinya ditolak; tidak terminal. */
    public function rejected(string $reason = 'NIDN tidak ditemukan di PDDikti.'): static
    {
        return $this->state(fn (): array => [
            'verification_status' => VerificationStatus::Rejected->value,
            'rejection_reason' => $reason,
        ]);
    }

    /** BR-IAM-03 — profil yang belum lengkap; gerbang yang berbeda. */
    public function incomplete(): static
    {
        return $this->state(fn (): array => [
            'phone' => null,
            'expertise' => null,
            'photo_path' => null,
        ]);
    }

    /** Terisi sebagian — menguji isComplete() yang berhenti di field pertama. */
    public function partiallyComplete(): static
    {
        return $this->state(fn (): array => [
            'phone' => '0813-9876-5432',
            'expertise' => null,
            'photo_path' => null,
        ]);
    }
}
