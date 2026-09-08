<?php

namespace App\Actions\Budget;

use App\Models\BudgetComponent;
use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class SaveBudgetLineItem
{
    use AsAction;

    /**
     * @param  array{budget_component_id: int, item_name: string, volume: int, unit: string, unit_price: int}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(FormSubmission $submission, User $user, array $attributes): BudgetLineItem
    {
        // BR-BUD-02, BR-BUD-03, BR-BUD-09 — sumbu siapa dan keadaan dijaga Policy,
        // dipanggil di sini supaya penguncian tidak bocor lewat jalur non-HTTP.
        if (!$user->can('update-budget', $submission)) {
            throw new AuthorizationException('Tidak berwenang menyunting anggaran pengajuan ini.');
        }

        // BR-BUD-04 — volume dan harga satuan wajib positif.
        if ($attributes['volume'] <= 0 || $attributes['unit_price'] <= 0) {
            throw ValidationException::withMessages([
                'volume' => 'Volume dan harga satuan harus lebih dari nol.',
            ]);
        }

        $component = BudgetComponent::query()->findOrFail($attributes['budget_component_id']);

        // BR-BUD-05 — komponen non-aktif tidak bisa dipilih untuk item BARU. Item lama
        // yang terlanjur memakainya sengaja tidak disentuh.
        if (!$component->is_active) {
            throw ValidationException::withMessages([
                'budget_component_id' => 'Komponen anggaran ini sudah tidak aktif.',
            ]);
        }

        $total = $attributes['volume'] * $attributes['unit_price'];

        $this->guardSchemeLimit($submission, $total);

        $tercatat = BudgetLineItem::create([
            'form_submission_id' => $submission->id,
            'budget_component_id' => $component->id,
            'item_name' => $attributes['item_name'],
            'volume' => $attributes['volume'],
            'unit' => $attributes['unit'],
            'unit_price' => $attributes['unit_price'],
            'total' => $total,
        ]);

        // BR-RPT-09 — satu Action, satu entri audit, lengkap dengan nilai
        // lama dan barunya. Anggaran menyangkut uang: jejak yang hanya
        // menyebut "diubah" tanpa angka tidak bisa dipertanggungjawabkan
        // kepada siapa pun.
        AuditTrail::record(
            'Line item anggaran disimpan',
            $tercatat,
            $user,
            null,
            [
                'item_name' => $tercatat->item_name,
                'volume' => $tercatat->volume,
                'unit_price' => $tercatat->unit_price,
                'total' => $tercatat->total,
            ],
        );

        return $tercatat;
    }

    /**
     * BR-BUD-01 (cermin BR-SM-06) — grand total tidak boleh melebihi scheme.max_budget.
     * Submission tanpa skema tidak punya batas (BR-SCH-04).
     *
     * @throws ValidationException
     */
    private function guardSchemeLimit(FormSubmission $submission, int $incomingTotal): void
    {
        $scheme = $submission->scheme;

        if ($scheme === null) {
            return;
        }

        $grandTotal = (int) $submission->budgetLineItems()->sum('total') + $incomingTotal;

        if ($grandTotal > $scheme->max_budget) {
            throw ValidationException::withMessages([
                'total' => 'Total anggaran melebihi batas maksimum skema.',
            ]);
        }
    }
}
