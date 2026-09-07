<?php

namespace App\Actions\Form;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Notifications\FormChangedNotification;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-13 — perubahan Form yang menyentuh draft berjalan tidak boleh senyap.
 *
 * Draft yang sedang diisi peneliti bisa saja kehilangan field, atau mendapat
 * field wajib baru. Temporal Field Binding (BR-FE-11) menjaga agar draft lama
 * tidak tiba-tiba tertolak karena field yang lahir belakangan — tapi peneliti
 * tetap perlu tahu bahwa formulirnya berubah, karena yang ia lihat di layar
 * bisa berbeda dari yang ia tinggalkan.
 *
 * Pemberitahuannya **ditawarkan**, bukan otomatis: tidak setiap perubahan
 * layak mengganggu 12 orang, dan yang tahu layak atau tidak adalah Admin yang
 * melakukannya. Yang tidak boleh adalah Admin tidak diberi tahu bahwa ada 12
 * orang yang terdampak.
 *
 * @method static int run(Form $form, bool $notify)
 */
class NotifyFormChange
{
    use AsAction;

    /**
     * @return int jumlah draft berjalan yang terdampak
     */
    public function handle(Form $form, bool $notify): int
    {
        $owners = User::query()
            ->whereIn('id', FormSubmission::query()
                ->where('form_id', $form->id)
                ->where('is_submitted', false)
                ->whereNull('parent_submission_id')
                ->select('submitted_by'))
            ->get();

        if ($owners->isEmpty()) {
            return 0;
        }

        if ($notify) {
            Notification::send($owners, new FormChangedNotification($form));
        }

        return $owners->count();
    }
}
