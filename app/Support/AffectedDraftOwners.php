<?php

namespace App\Support;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * BR-FE-13 — siapa yang terdampak kalau sebuah Form berubah.
 *
 * Yang dihitung adalah pemilik draft yang **sedang berjalan**: sudah dimulai,
 * belum dikirim, dan bukan laporan turunan (monev punya siklusnya sendiri).
 * Pengajuan yang sudah masuk tidak lagi diisi, jadi perubahan formulir tidak
 * mengubah apa pun baginya.
 *
 * Definisinya tinggal satu tempat karena dipakai dua arah: layar edit Form
 * memakainya untuk **memperingatkan sebelum** Admin menyimpan, dan
 * `NotifyFormChange` memakainya untuk **mengirim sesudah**. Dua definisi
 * terpisah akan menghasilkan angka peringatan yang berbeda dari jumlah orang
 * yang benar-benar dikirimi — dan yang salah baru ketahuan saat seseorang
 * mengeluh tidak dapat kabar.
 */
final class AffectedDraftOwners
{
    /** @return Collection<int, User> */
    public static function forForm(Form $form): Collection
    {
        return User::query()
            ->whereIn('id', FormSubmission::query()
                ->where('form_id', $form->id)
                ->where('is_submitted', false)
                ->whereNull('parent_submission_id')
                ->select('submitted_by'))
            ->get();
    }

    public static function countForForm(Form $form): int
    {
        return FormSubmission::query()
            ->where('form_id', $form->id)
            ->where('is_submitted', false)
            ->whereNull('parent_submission_id')
            ->distinct()
            ->count('submitted_by');
    }
}
