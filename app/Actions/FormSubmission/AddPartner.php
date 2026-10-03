<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\Partner;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-21 — mencatat mitra eksternal sebuah pengajuan. Tidak ada batas jumlah dari skema, dan itu
 * sengaja: `max_members` dan `max_student_members` membatasi orang yang dibiayai, sedangkan mitra
 * tidak menambah beban anggaran. Membatasinya akan menolak kerja sama yang justru didorong LPPM.
 *
 * @method static Partner run(FormSubmission $submission, User $actor, array{institution_name: string, contact_person?: string|null, description?: string|null} $attributes)
 */
class AddPartner
{
    use AsAction;

    /**
     * @param  array{institution_name: string, contact_person?: string|null, description?: string|null}  $attributes
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(FormSubmission $submission, User $actor, array $attributes): Partner
    {
        // Sumbu siapa dan kapannya sama dengan anggota (BR-SM-18).
        if (!$actor->can('manageResearchMembers', $submission)) {
            throw new AuthorizationException(
                'Hanya ketua pengaju yang dapat mengatur mitra, dan hanya selama pengajuan belum disetujui, ditolak, atau ditarik.'
            );
        }

        $institusi = trim($attributes['institution_name']);

        if ($institusi === '') {
            throw ValidationException::withMessages([
                'institution_name' => 'Nama institusi mitra harus diisi.',
            ]);
        }

        // Institusi yang sama dicatat dua kali membuat hitungan cakupan
        // kemitraan melaporkan satu kerja sama sebagai dua.
        $sudahAda = $submission->partners()
            ->whereRaw('LOWER(institution_name) = ?', [mb_strtolower($institusi)])
            ->exists();

        if ($sudahAda) {
            throw ValidationException::withMessages([
                'institution_name' => 'Institusi itu sudah tercatat sebagai mitra pengajuan ini.',
            ]);
        }

        $tercatat = $submission->partners()->create([
            'institution_name' => $institusi,
            'contact_person' => $attributes['contact_person'] ?? null,
            'description' => $attributes['description'] ?? null,
        ]);

        AuditTrail::record('Mitra ditambahkan', $tercatat, $actor, new AuditChange(new: [
            'institution_name' => $tercatat->institution_name,
        ]));

        return $tercatat;
    }
}
