<?php

namespace App\Http\Controllers;

use App\Actions\FormSubmission\WithdrawSubmission;
use App\Models\FormSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Penarikan pengajuan (BR-SM-11) — wireframe OPERATOR-02-06.
 *
 * Controller tersendiri, bukan cabang baru di endpoint status generik:
 * penarikan punya syaratnya sendiri (hanya Operator, hanya dari APPROVED,
 * tidak dapat dibatalkan) dan menumpangkannya pada endpoint yang menerima
 * status apa pun akan mengaburkan ketiganya.
 */
class SubmissionWithdrawalController extends Controller
{
    public function __invoke(FormSubmission $submission): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('withdraw', $submission), 403);

        WithdrawSubmission::run($submission, $user);

        return back()->with('success', 'Pengajuan berhasil ditarik.');
    }
}
