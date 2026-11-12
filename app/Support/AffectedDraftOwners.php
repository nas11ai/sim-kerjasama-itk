<?php

namespace App\Support;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * BR-FE-13 — siapa yang terdampak kalau sebuah Form berubah. Satu definisi
 * untuk dua arah: layar edit Form **memperingatkan sebelum** Admin menyimpan,
 * `NotifyFormChange` **mengirim sesudah** — dua definisi, dua angka berbeda.
 */
final class AffectedDraftOwners
{
    /** @return Collection<int, User> */
    public static function forForm(Form $form): Collection
    {
        // Yang dihitung pemilik draft yang **sedang berjalan**: sudah dimulai, belum dikirim —
        // termasuk draf laporan monev turunan (B15). Pengajuan yang sudah masuk tidak terdampak.
        return User::query()
            ->whereIn('id', FormSubmission::query()
                ->where('form_id', $form->id)
                ->where('is_submitted', false)
                ->select('submitted_by'))
            ->get();
    }

    public static function countForForm(Form $form): int
    {
        return FormSubmission::query()
            ->where('form_id', $form->id)
            ->where('is_submitted', false)
            ->distinct()
            ->count('submitted_by');
    }
}
