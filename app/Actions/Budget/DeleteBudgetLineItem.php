<?php

namespace App\Actions\Budget;

use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-BUD-10 — menghapus satu line item anggaran.
 *
 * Soft delete, bukan hard delete. Nilai yang pernah masuk rencana anggaran
 * adalah bahan dispute antara reviewer dan researcher (BR-BUD-08): baris yang
 * hilang tanpa jejak membuat pertanyaan "kenapa angkanya turun" tidak
 * terjawab, dan pertanyaan itu selalu datang belakangan saat audit.
 *
 * Tanpa jalur ini BR-BUD-01 jadi jalan buntu — peneliti yang terlanjur
 * melewati batas skema tidak punya cara menurunkan totalnya.
 *
 * @method static void run(FormSubmission $submission, BudgetLineItem $item, User $user)
 */
class DeleteBudgetLineItem
{
    use AsAction;

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(FormSubmission $submission, BudgetLineItem $item, User $user): void
    {
        if (!$user->can('update-budget', $submission)) {
            throw new AuthorizationException('Tidak berwenang menyunting anggaran pengajuan ini.');
        }

        if ($item->form_submission_id !== $submission->id) {
            throw ValidationException::withMessages([
                'id' => 'Item anggaran ini bukan milik pengajuan tersebut.',
            ]);
        }

        // Audit ditulis sebelum barisnya hilang dari pandangan: entri "sesuatu
        // dihapus" tanpa menyebut angkanya tidak menolong siapa pun.
        AuditTrail::record(
            'Line item anggaran dihapus',
            $item,
            $user,
            [
                'item_name' => $item->item_name,
                'volume' => $item->volume,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
            ],
            null,
        );

        $item->delete();
    }
}
