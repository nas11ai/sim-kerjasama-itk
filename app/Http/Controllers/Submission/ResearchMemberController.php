<?php

namespace App\Http\Controllers\Submission;

use App\Actions\FormSubmission\AddResearchMember;
use App\Actions\FormSubmission\AddStudentMember;
use App\Actions\FormSubmission\RemoveResearchMember;
use App\Actions\FormSubmission\RemoveStudentMember;
use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\StudentMember;
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

    /**
     * BR-SM-18 — mengeluarkan anggota. Tanpa ini batas anggota skema jadi
     * jalan buntu: kuota penuh, dan tidak ada cara menurunkannya.
     */
    public function destroy(FormSubmission $submission, ResearchMember $member): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('manageResearchMembers', $submission), 403);

        RemoveResearchMember::run($submission, $member, $user);

        return back()->with('success', 'Anggota penelitian dikeluarkan.');
    }

    /**
     * BR-SM-20 — anggota mahasiswa: data, bukan akun.
     */
    public function storeStudent(Request $request, FormSubmission $submission): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('manageResearchMembers', $submission), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'student_id' => 'required|string|max:64',
            'study_program' => 'nullable|string|max:255',
        ]);

        AddStudentMember::run($submission, $user, $validated);

        return back()->with('success', 'Anggota mahasiswa ditambahkan.');
    }

    public function destroyStudent(FormSubmission $submission, StudentMember $student): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('manageResearchMembers', $submission), 403);

        RemoveStudentMember::run($submission, $student, $user);

        return back()->with('success', 'Anggota mahasiswa dikeluarkan.');
    }
}
