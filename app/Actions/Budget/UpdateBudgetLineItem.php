<?php

namespace App\Actions\Budget;

use App\Models\BudgetComponent;
use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\BudgetLimitGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-BUD-10 — menyunting satu line item anggaran.
 *
 * Wewenang dan gerbang statusnya sama persis dengan penambahan (BR-BUD-09,
 * BR-BUD-02): yang boleh menambah angka adalah yang boleh memperbaikinya.
 * Memberi keduanya ambang berbeda hanya melahirkan pengajuan yang bisa diisi
 * tapi tidak bisa dibetulkan.
 *
 * @method static BudgetLineItem run(FormSubmission $submission, BudgetLineItem $item, User $user, array{budget_component_id?: int, item_name?: string, volume?: int, unit?: string, unit_price?: int} $attributes)
 */
class UpdateBudgetLineItem
{
    use AsAction;

    /**
     * @param  array{budget_component_id?: int, item_name?: string, volume?: int, unit?: string, unit_price?: int}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(
        FormSubmission $submission,
        BudgetLineItem $item,
        User $user,
        array $attributes,
    ): BudgetLineItem {
        $this->guardOwnership($submission, $item, $user);

        $volume = $attributes['volume'] ?? $item->volume;
        $unitPrice = $attributes['unit_price'] ?? $item->unit_price;

        $this->guardPositiveAmounts($volume, $unitPrice);

        $componentId = $attributes['budget_component_id'] ?? $item->budget_component_id;

        $this->guardComponentSelectable($componentId, $item);

        $total = $volume * $unitPrice;

        BudgetLimitGuard::assertWithinScheme($submission, $total, $item->id);

        $sebelum = $this->snapshot($item);

        $item->update([
            'budget_component_id' => $componentId,
            'item_name' => $attributes['item_name'] ?? $item->item_name,
            'volume' => $volume,
            'unit' => $attributes['unit'] ?? $item->unit,
            'unit_price' => $unitPrice,
            'total' => $total,
        ]);

        // BR-BUD-08 — nilai sebelum dan sesudah, bukan sekadar "diubah".
        // Anggaran menyangkut uang, dan angkanya yang jadi bahan dispute.
        AuditTrail::record(
            'Line item anggaran disunting',
            $item,
            $user,
            new AuditChange(
                old: $sebelum,
                new: $this->snapshot($item),
            ),
        );

        return $item->refresh();
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    private function guardOwnership(FormSubmission $submission, BudgetLineItem $item, User $user): void
    {
        if (!$user->can('update-budget', $submission)) {
            throw new AuthorizationException('Tidak berwenang menyunting anggaran pengajuan ini.');
        }

        // BR-SM-07 — item milik pengajuan lain tidak ikut tersentuh hanya karena
        // id-nya ditebak benar.
        if ($item->form_submission_id !== $submission->id) {
            throw ValidationException::withMessages([
                'id' => 'Item anggaran ini bukan milik pengajuan tersebut.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function guardPositiveAmounts(int $volume, int $unitPrice): void
    {
        // BR-BUD-04 — sama seperti pada penambahan.
        if ($volume <= 0 || $unitPrice <= 0) {
            throw ValidationException::withMessages([
                'volume' => 'Volume dan harga satuan harus lebih dari nol.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function guardComponentSelectable(int $componentId, BudgetLineItem $item): void
    {
        // BR-BUD-05 — komponen nonaktif tertutup untuk pilihan BARU. Item yang
        // sudah memakainya boleh disunting tanpa dipaksa berpindah komponen:
        // memaksa berpindah justru mengubah arti baris yang sudah disetujui.
        if ($componentId !== $item->budget_component_id) {
            $component = BudgetComponent::query()->findOrFail($componentId);

            if (!$component->is_active) {
                throw ValidationException::withMessages([
                    'budget_component_id' => 'Komponen anggaran ini sudah tidak aktif.',
                ]);
            }
        }
    }

    /**
     * @return array{item_name: string, volume: int, unit_price: int, total: int}
     */
    private function snapshot(BudgetLineItem $item): array
    {
        return [
            'item_name' => $item->item_name,
            'volume' => $item->volume,
            'unit_price' => $item->unit_price,
            'total' => $item->total,
        ];
    }
}
