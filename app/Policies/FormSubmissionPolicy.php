<?php

namespace App\Policies;

use App\Data\View\Review\DecisionReadinessData;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\Queries\Review\DecisionReadiness;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;
use App\Support\Review\AwaitingDecision;

class FormSubmissionPolicy
{
    /** BR-SM-07 — akses level submission, lapis terpisah dari FormAccessControl. */
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

    /**
     * BR-SM-04, W123 — laporan monev dimulai, diisi, dan dikirim ketua induk. ADR 0015:
     * invarian, Super Admin tidak mengisi laporan atas nama peneliti.
     */
    public function startMonevReport(User $user, FormSubmission $parent): bool
    {
        return $parent->submitted_by === $user->id;
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
    public function discussReview(User $user, FormSubmission $submission): bool
    {
        // BR-REV-06 — dari pihak peneliti hanya ketua pengaju yang berdiskusi; ia juga yang mengirim ulang (BR-SM-04).
        return $submission->submitted_by === $user->id;
    }

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

    /** BR-SM-18 — mengeluarkan anggota; sumbunya sama dengan BR-SM-15: hanya `submitted_by`, selama belum terminal. */
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

    /** BR-REV-05, BR-REV-07 (ADR 0018) — Setujui dan Tolak oleh Operator, hanya atas pengajuan siap diputuskan. */
    public function approve(User $user, FormSubmission $submission, ?DecisionReadinessData $readiness = null): bool
    {
        return $user->can('submissions.view-all') && ($readiness ?? DecisionReadiness::run($submission))->ready;
    }

    public function reject(User $user, FormSubmission $submission, ?DecisionReadinessData $readiness = null): bool
    {
        return $this->approve($user, $submission, $readiness);
    }

    public function withdraw(User $user, FormSubmission $submission): bool
    {
        // BR-SM-11 — oleh Operator, bukan self-service; keputusan dibatalkan lewat penarikan, bukan keputusan susulan.
        return $user->can('submissions.view-all')
            && $submission->status instanceof Approved;
    }

    /** BR-IAM-10, BR-SM-09 — pengalihan kepemilikan adalah wewenang Operator. */
    public function transferOwnership(User $user, FormSubmission $submission): bool
    {
        return $user->can('submissions.view-all');
    }

    public function assignReviewers(User $user, FormSubmission $submission): bool
    {
        // BR-REV-01, BR-SM-05 — penugasan bertahap selama pengajuan terkirim dan belum diputuskan.
        return $user->can('reviewers.assign') && AwaitingDecision::holds($submission);
    }

    /** BR-MON-04, W129 — penugasan reviewer per siklus monev; laporan anak tidak pernah AwaitingDecision. */
    public function assignMonevReviewer(User $user, FormSubmission $parent): bool
    {
        return $user->can('reviewers.assign');
    }

    public function replaceReviewer(User $user, FormSubmission $submission): bool
    {
        // BR-REV-10 — penggantian adalah penugasan juga; terkunci pada sumbu yang sama.
        return $user->can('reviewers.assign') && AwaitingDecision::holds($submission);
    }

    /**
     * BR-FE-09, BR-FE-10 — perpanjangan akses wewenang Operator, sengaja bukan pemilik pengajuan:
     * kalau peneliti bisa memperpanjang batasnya sendiri, batas itu berhenti menjadi batas.
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
        // Staf = `outputs.manage` + `submissions.view-all` (W138): peran researcher juga
        // memegang `outputs.manage` untuk timnya sendiri, jadi izin itu saja bukan tanda staf.
        // Staf mengoreksi karena luaran memasok pelaporan LPPM yang ia pertanggungjawabkan.
        if ($user->can('outputs.manage') && $user->can('submissions.view-all')) {
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
