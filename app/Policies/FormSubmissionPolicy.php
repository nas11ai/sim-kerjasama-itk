<?php

namespace App\Policies;

use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;

class FormSubmissionPolicy
{
    /**
     * BR-SM-07 — akses level submission, lapis terpisah dari FormAccessControl.
     */
    public function view(User $user, FormSubmission $submission): bool
    {
        return $submission->isAccessibleBy($user);
    }

    /**
     * I6 — guard isAccessibleBy() dihapus: cabang submitted_by di dalamnya
     * sudah tercakup cek di bawah, jadi ia tidak pernah mengubah hasil tapi
     * menambah dua query exists().
     */
    public function update(User $user, FormSubmission $submission): bool
    {
        // Hanya lead researcher yang boleh mengubah isi pengajuan.
        if ($submission->submitted_by !== $user->id) {
            return false;
        }

        // BR-SM-10 — draft yang diarsipkan berubah read-only. Status saja
        // tidak cukup: pengajuan yang periodenya sudah tutup tetap DRAFT,
        // jadi arsip adalah sumbu kedua yang harus ikut menutup penyuntingan.
        if ($submission->resolveIsArchived()) {
            return false;
        }

        return $submission->status instanceof Draft
            || $submission->status instanceof NeedsRevision;
    }

    public function submit(User $user, FormSubmission $submission): bool
    {
        // BR-SM-04 — hanya submitted_by yang boleh submit dan resubmit.
        return $submission->submitted_by === $user->id;
    }

    public function resubmit(User $user, FormSubmission $submission): bool
    {
        // BR-SM-04 — resubmit sama seperti submit: tindakan submitted_by mengikat
        // dirinya sendiri. ADR 0015 memasukkannya ke INVARIANT_ABILITIES, jadi
        // Gate::before tidak melewatinya dan method ini yang menentukan.
        if ($submission->submitted_by !== $user->id) {
            return false;
        }

        // BR-SM-03 — hanya NEEDS_REVISION yang bisa di-resubmit.
        return $submission->status instanceof NeedsRevision;
    }

    /**
     * BR-SM-15 — target luaran boleh diubah sampai REJECTED atau WITHDRAWN,
     * termasuk setelah APPROVED: target menyesuaikan jalannya kegiatan.
     */
    public function manageTargetOutput(User $user, FormSubmission $submission): bool
    {
        // Hanya `submitted_by`, sengaja berbeda dari BR-RO-07 yang membolehkan
        // co-investigator mengisi luaran realisasi: mengubah janji bukan
        // tindakan yang sama dengan mengisi hasil. Jangan diseragamkan.
        if ($submission->submitted_by !== $user->id) {
            return false;
        }

        return !$submission->status instanceof Rejected
            && !$submission->status instanceof Withdrawn;
    }

    /**
     * BR-SM-18 — mengeluarkan anggota penelitian. Sumbunya sama dengan
     * BR-SM-15: hanya `submitted_by`, hanya selama belum terminal.
     */
    public function manageResearchMembers(User $user, FormSubmission $submission): bool
    {
        if ($submission->submitted_by !== $user->id) {
            return false;
        }

        // Bedanya: APPROVED ikut mengunci — susunan tim yang sudah disetujui
        // adalah bagian dari yang disetujui, sedangkan target luaran justru
        // dibiarkan menyesuaikan jalannya kegiatan.
        return !$submission->status instanceof Approved
            && !$submission->status instanceof Rejected
            && !$submission->status instanceof Withdrawn;
    }

    /**
     * BR-REV-07 — penolakan hanya oleh Operator, dan hanya atas pengajuan
     * yang masih berjalan. Keputusan yang sudah diambil dibatalkan lewat
     * penarikan, bukan penolakan susulan.
     */
    public function reject(User $user, FormSubmission $submission): bool
    {
        return $user->can('submissions.view-all')
            && $submission->status instanceof UnderReview;
    }

    public function withdraw(User $user, FormSubmission $submission): bool
    {
        // BR-SM-11 — withdrawal hanya oleh Operator, bukan self-service.
        return $user->can('submissions.view-all')
            && $submission->status instanceof Approved;
    }

    /**
     * BR-FE-09, BR-FE-10 — perpanjangan akses adalah wewenang Operator.
     *
     * Sengaja tidak diberikan kepada pemilik pengajuan: kalau peneliti bisa
     * memperpanjang batasnya sendiri, batas itu berhenti menjadi batas.
     */
    public function grantOverride(User $user, FormSubmission $submission): bool
    {
        return $user->can('submissions.view-all');
    }

    /**
     * BR-RO-07 — dua sumbu yang sengaja dipisah (register #35): tim penelitian
     * dan staf. ADR 0015 yang menjadikan `update-budget` invarian domain
     * sengaja tidak diikuti: anggaran komitmen peneliti, luaran laporan fakta.
     */
    public function manageResearchOutput(User $user, FormSubmission $submission): bool
    {
        // Staf pemegang `outputs.manage` tetap bisa menyunting: luaran memasok
        // pelaporan LPPM dan merapikan datanya memang tugas mereka. Menutupnya
        // mengunci Operator dari data yang ia pertanggungjawabkan ke luar.
        if ($user->can('outputs.manage')) {
            return true;
        }

        // Di dalam tim penelitian hanya `submitted_by` dan `co_investigator`;
        // anggota biasa dan Reviewer membaca saja.
        if ($submission->submitted_by === $user->id) {
            return true;
        }

        return $submission->researchMembers()
            ->where('role', ResearchMember::ROLE_CO_INVESTIGATOR)
            ->where('status', 'active')
            ->whereHas('userProfile', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }

    /**
     * BR-FE-16 — hanya Operator dan Admin yang boleh membaca `reason`.
     * Peneliti melihat status override-nya, bukan alasannya.
     */
    public function viewOverrideReason(User $user, FormSubmission $submission): bool
    {
        return $user->can('submissions.view-all');
    }
}
