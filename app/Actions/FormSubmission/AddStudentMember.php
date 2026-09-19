<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\StudentMember;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-20 — menambahkan anggota mahasiswa.
 *
 * Tidak ada pengecekan konflik kepentingan di sini, dan itu disengaja:
 * BR-SM-08 menjaga agar penilai tidak menilai penelitiannya sendiri, sementara
 * mahasiswa tidak pernah bisa jadi reviewer. Menyalin guard-nya ke sini hanya
 * akan menyiratkan larangan yang tidak pernah ada.
 *
 * @method static StudentMember run(FormSubmission $submission, User $actor, array{name: string, student_id: string, study_program?: string|null} $attributes)
 */
class AddStudentMember
{
    use AsAction;

    /**
     * @param  array{name: string, student_id: string, study_program?: string|null}  $attributes
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(FormSubmission $submission, User $actor, array $attributes): StudentMember
    {
        // Sumbu siapa dan kapannya sama persis dengan anggota bergelar.
        if (!$actor->can('manageResearchMembers', $submission)) {
            throw new AuthorizationException(
                'Hanya ketua pengaju yang dapat mengatur anggota mahasiswa, dan hanya selama pengajuan belum disetujui, ditolak, atau ditarik.'
            );
        }

        $this->guardCapacity($submission);

        if ($submission->studentMembers()->where('student_id', $attributes['student_id'])->exists()) {
            throw ValidationException::withMessages([
                'student_id' => 'Mahasiswa dengan NIM itu sudah terdaftar di pengajuan ini.',
            ]);
        }

        $tercatat = $submission->studentMembers()->create([
            'name' => $attributes['name'],
            'student_id' => $attributes['student_id'],
            'study_program' => $attributes['study_program'] ?? null,
        ]);

        AuditTrail::record('Anggota mahasiswa ditambahkan', $tercatat, $actor, null, [
            'name' => $tercatat->name,
            'student_id' => $tercatat->student_id,
        ]);

        return $tercatat;
    }

    /**
     * Batasnya berasal dari skema. Skema yang tidak menyatakannya tidak
     * membatasi — sejajar `allowed_output_types` di BR-SM-14 dan `max_members`
     * di BR-SM-08.
     *
     * @throws ValidationException
     */
    private function guardCapacity(FormSubmission $submission): void
    {
        $max = $submission->scheme?->getRule('max_student_members');

        if (!is_int($max) || $max <= 0) {
            return;
        }

        if ($submission->studentMembers()->count() < $max) {
            return;
        }

        throw ValidationException::withMessages([
            'student_id' => "Jumlah anggota mahasiswa sudah mencapai batas skema ({$max} orang). "
                .'Keluarkan salah satu sebelum menambah.',
        ]);
    }
}
