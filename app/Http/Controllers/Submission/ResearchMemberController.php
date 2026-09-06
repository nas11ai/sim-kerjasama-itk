<?php

namespace App\Http\Controllers\Submission;

use App\Actions\FormSubmission\AddResearchMember;
use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** BR-SM-08 — anggota penelitian, dijaga guard konflik kepentingan. */
class ResearchMemberController extends Controller
{
    public function store(Request $request, FormSubmission $submission): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('update', $submission), 403);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:'.implode(',', [
                ResearchMember::ROLE_CO_INVESTIGATOR,
                ResearchMember::ROLE_MEMBER,
            ]),
        ]);

        AddResearchMember::run($submission, User::findOrFail($validated['user_id']), $validated['role']);

        return back()->with('success', 'Anggota penelitian ditambahkan.');
    }
}
