<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-05 dan BR-SM-09 — menonaktifkan user.
 *
 * Deaktivasi tidak menghapus apa pun; data tetap utuh supaya jejak pengajuan
 * lama tetap terbaca. Yang menghalangi hanyalah tanggung jawab yang belum
 * dialihkan: lead researcher tidak boleh pergi meninggalkan pengajuan aktif
 * tanpa penanggung jawab.
 *
 * Penolakannya menyebut jalan keluarnya — dan kalau jalan keluar itu belum
 * ada (tidak ada co_investigator aktif), itu pun dikatakan. Operator yang
 * hanya diberi tahu "ditolak" akan mencoba berulang tanpa tahu apa yang
 * kurang.
 */
class DeactivateUser
{
    use AsAction;

    /** Status yang tidak lagi menuntut penanggung jawab. */
    private const SETTLED = [Rejected::class, Withdrawn::class];

    /**
     * @throws ValidationException
     */
    public function handle(User $user): User
    {
        $blocking = FormSubmission::query()
            ->where('submitted_by', $user->id)
            // whereNotState, bukan whereNotIn: kolomnya menyimpan morph name
            // ("rejected"), bukan nama kelas — membandingkan class-string di
            // sini tidak akan pernah cocok.
            ->whereNotState('status', self::SETTLED)
            ->get();

        if ($blocking->isNotEmpty()) {
            throw ValidationException::withMessages([
                'user_id' => $this->reason($blocking),
            ]);
        }

        $user->update(['is_active' => false]);

        return $user->refresh();
    }

    /** @param Collection<int, FormSubmission> $blocking */
    private function reason($blocking): string
    {
        $withoutSuccessor = $blocking->filter(
            fn (FormSubmission $s) => !$s->researchMembers()
                ->where('role', ResearchMember::ROLE_CO_INVESTIGATOR)
                ->where('status', 'active')
                ->exists()
        );

        $base = "Masih ada {$blocking->count()} pengajuan aktif atas nama user ini. Pindahkan kepemilikannya lebih dulu.";

        if ($withoutSuccessor->isEmpty()) {
            return $base;
        }

        return $base.' '.$withoutSuccessor->count()
            .' di antaranya belum punya co_investigator aktif sebagai calon penerima.';
    }
}
