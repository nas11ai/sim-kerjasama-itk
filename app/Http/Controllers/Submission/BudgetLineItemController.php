<?php

namespace App\Http\Controllers\Submission;

use App\Actions\Budget\DeleteBudgetLineItem;
use App\Actions\Budget\SaveBudgetLineItem;
use App\Actions\Budget\UpdateBudgetLineItem;
use App\Http\Controllers\Controller;
use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use App\Support\WizardStepGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * BR-BUD-01/04/09 — line item anggaran.
 *
 * Batas totalnya berasal dari skema, dan Action yang menegakkannya —
 * controller hanya memvalidasi bentuk, bukan aturan.
 */
class BudgetLineItemController extends Controller
{
    public function store(Request $request, FormSubmission $submission): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('update-budget', $submission), 403);

        // BR-SM-17 — satu-satunya prasyarat nyata antar langkah wizard: anggaran butuh
        // skema (batasnya dari sana, BR-BUD-01). Ditegakkan di sini, bukan navigasi:
        // yang tak boleh terjadi anggaran tanpa batas, bukan peneliti buka layarnya.
        $blocked = WizardStepGate::blockedReason($submission, WizardStepGate::STEP_BUDGET);

        if ($blocked !== null) {
            throw ValidationException::withMessages(['scheme_id' => $blocked]);
        }

        $validated = $request->validate([
            'budget_component_id' => 'required|exists:budget_components,id',
            'item_name' => 'required|string|max:255',
            'volume' => 'required|integer|min:1',
            'unit' => 'required|string|max:50',
            'unit_price' => 'required|integer|min:1',
        ]);

        SaveBudgetLineItem::run($submission, $user, $validated);

        return back()->with('success', 'Item anggaran tersimpan.');
    }

    /**
     * BR-BUD-10 — menyunting item yang sudah ada.
     *
     * Tanpa WizardStepGate: prasyarat skema (BR-SM-17) sudah menjaganya saat
     * lahir — gerbang yang sama di sini justru mengunci koreksi saat skema dilepas.
     */
    public function update(
        Request $request,
        FormSubmission $submission,
        BudgetLineItem $budgetItem,
    ): RedirectResponse {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('update-budget', $submission), 403);

        $validated = $request->validate([
            'budget_component_id' => 'sometimes|integer|exists:budget_components,id',
            'item_name' => 'sometimes|string|max:255',
            'volume' => 'sometimes|integer',
            'unit' => 'sometimes|string|max:50',
            'unit_price' => 'sometimes|integer',
        ]);

        UpdateBudgetLineItem::run($submission, $budgetItem, $user, $validated);

        return back()->with('success', 'Item anggaran diperbarui.');
    }

    /**
     * BR-BUD-10 — menghapus item. Soft delete; barisnya tetap jadi jejak.
     */
    public function destroy(
        FormSubmission $submission,
        BudgetLineItem $budgetItem,
    ): RedirectResponse {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('update-budget', $submission), 403);

        DeleteBudgetLineItem::run($submission, $budgetItem, $user);

        return back()->with('success', 'Item anggaran dihapus.');
    }
}
