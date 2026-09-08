<?php

namespace App\Actions\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Form;
use App\Notifications\FormChangedNotification;
use App\Support\AffectedDraftOwners;
use App\Support\Notification\Deliver;
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
 *
 * **Tidak menulis audit (BR-RPT-09).** Seluruh kerjanya mengirim pemberitahuan atas perubahan Form yang sudah dicatat di jalur penyuntingannya. Mencatatnya lagi di sini akan memunculkan dua baris untuk satu tindakan.
 *
 * @method static int run(Form $form, bool $notify)
 */
class NotifyFormChange implements ChangesNoState
{
    use AsAction;

    /**
     * @return int jumlah draft berjalan yang terdampak
     */
    public function handle(Form $form, bool $notify): int
    {
        $owners = AffectedDraftOwners::forForm($form);

        if ($owners->isEmpty()) {
            return 0;
        }

        if ($notify) {
            Deliver::to($owners, new FormChangedNotification($form));
        }

        return $owners->count();
    }
}
