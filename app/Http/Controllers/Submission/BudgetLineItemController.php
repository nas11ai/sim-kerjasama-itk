<?php

namespace App\Http\Controllers\Submission;

use App\Actions\Budget\SaveBudgetLineItem;
use App\Http\Controllers\Controller;
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

        // BR-SM-17 — satu-satunya prasyarat nyata antar langkah wizard:
        // anggaran butuh skema, karena batasnya berasal dari sana (BR-BUD-01).
        // Ditegakkan di sini, bukan di navigasi: yang tidak boleh terjadi
        // adalah anggaran tersimpan tanpa batas yang mengikatnya, bukan
        // peneliti membuka layarnya.
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
}
