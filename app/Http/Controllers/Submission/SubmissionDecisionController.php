<?php

namespace App\Http\Controllers\Submission;

use App\Actions\FormSubmission\TransferSubmissionOwnership;
use App\Actions\Review\ApproveSubmission;
use App\Actions\Review\RejectSubmission;
use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Keputusan Operator atas pengajuan: menyetujui, menolak, dan memindahkan kepemilikan.
 * Keputusan sengaja bukan cabang endpoint status generik — bersyarat sendiri
 * (siap diputuskan, Tolak wajib beralasan), dan menumpang akan mengaburkan keduanya.
 */
class SubmissionDecisionController extends Controller
{
    public function approve(FormSubmission $submission): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null, 403);

        // BR-REV-05 — Setujui menunggu putaran selesai (ADR 0018); wewenangnya diperiksa Action.
        ApproveSubmission::run($submission, $user);

        return back()->with('success', 'Pengajuan disetujui.');
    }

    public function reject(Request $request, FormSubmission $submission): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null, 403);

        // BR-REV-07 — wewenang lebih dulu, lalu alasan wajib: keduanya diperiksa Action, berurutan.
        RejectSubmission::run($submission, $user, $request->string('reason')->toString());

        return back()->with('success', 'Pengajuan ditolak.');
    }

    public function transferOwnership(Request $request, FormSubmission $submission): RedirectResponse
    {
        $user = Auth::user();

        // BR-IAM-10 — pemindahan kepemilikan tindakan Operator, bukan pemilik.
        abort_unless($user !== null && $user->can('transferOwnership', $submission), 403);

        // W26 — alasan wajib; tercatat di entri audit Action-nya.
        $validated = $request->validate([
            'successor_id' => 'required|exists:users,id',
            'reason' => 'required|string|min:3|max:2000',
        ]);

        TransferSubmissionOwnership::run($submission, User::findOrFail($validated['successor_id']), $validated['reason']);

        return back()->with('success', 'Kepemilikan pengajuan dipindahkan.');
    }
}
