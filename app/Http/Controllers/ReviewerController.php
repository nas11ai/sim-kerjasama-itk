<?php

namespace App\Http\Controllers;

use App\Models\Reviewer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewerController extends Controller
{
    public function index(Request $request)
    {
        $query = Reviewer::with([
            'user:id,name,email',
        ]);

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            })->orWhere('reviewer_type', 'ilike', "%{$search}%");
        }

        if ($request->has('role') && $request->role) {
            $query->where('reviewer_type', $request->role);
        }

        if ($request->has('status') && $request->status !== '') {
            $now = Carbon::now();
            if ($request->status === 'active') {
                $query->where(function ($q) use ($now) {
                    $q->where('start_date', '<=', $now)
                        ->where(function ($subQ) use ($now) {
                            $subQ->where('end_date', '>=', $now)
                                ->orWhereNull('end_date');
                        });
                });
            } else {
                $query->where('end_date', '<', $now);
            }
        }

        $reviewers = $query->orderBy('created_at', 'desc')->paginate(10);

        $reviewers->getCollection()->transform(function ($reviewer) {
            $now = Carbon::now();
            $reviewer->is_active = $reviewer->start_date <= $now &&
                ($reviewer->end_date === null || $reviewer->end_date >= $now);

            return $reviewer;
        });

        $reviewerTypes = ['internal', 'external'];

        return Inertia::render('Reviewers/IndexPage', [
            'reviewers' => $reviewers,
            'reviewerTypes' => $reviewerTypes,
            'filters' => $request->only(['search', 'role', 'status']),
        ]);
    }

    public function create()
    {
        $users = User::whereHas('roles', function ($query) {
            $query->where('name', 'Tenaga Kependidikan');
        })
            ->whereDoesntHave('reviewers', function ($query) {
                $now = Carbon::now();
                $query->where('start_date', '<=', $now)
                    ->where(function ($q) use ($now) {
                        $q->where('end_date', '>=', $now)
                            ->orWhereNull('end_date');
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $reviewerTypes = ['internal', 'external'];

        return Inertia::render('Reviewers/CreatePage', [
            'users' => $users,
            'reviewerTypes' => $reviewerTypes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reviewer_type' => 'required|in:internal,external',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        $existingReviewer = Reviewer::where('user_id', $validated['user_id'])
            ->where('start_date', '<=', Carbon::now())
            ->where(function ($query) {
                $query->where('end_date', '>=', Carbon::now())
                    ->orWhereNull('end_date');
            })
            ->exists();

        if ($existingReviewer) {
            return back()->withErrors([
                'user_id' => 'User sudah memiliki role reviewer yang aktif.',
            ]);
        }

        Reviewer::create($validated);

        return redirect()->route('admin.reviewers.index')
            ->with('success', 'Reviewer berhasil ditambahkan.');
    }

    public function show(Reviewer $reviewer)
    {
        $reviewer->load([
            'user:id,name,email',
            'submissionReviewers.formSubmission.form:id,title',
        ]);

        $now = Carbon::now();
        $reviewer->is_active = $reviewer->start_date <= $now &&
            ($reviewer->end_date === null || $reviewer->end_date >= $now);

        $reviewer->total_reviews = $reviewer->submissionReviewers->count();
        $reviewer->pending_reviews = $reviewer->submissionReviewers()
            ->count();
        $reviewer->completed_reviews = $reviewer->submissionReviewers()
            ->count();

        return Inertia::render('Reviewers/ShowPage', [
            'reviewer' => $reviewer,
        ]);
    }

    public function edit(Reviewer $reviewer)
    {
        $reviewer->load(['user:id,name,email']);

        $reviewerTypes = ['internal', 'external'];

        return Inertia::render('Reviewers/EditPage', [
            'reviewer' => $reviewer,
            'reviewerTypes' => $reviewerTypes,
        ]);
    }

    public function update(Request $request, Reviewer $reviewer)
    {
        $validated = $request->validate([
            'reviewer_type' => 'required|in:internal,external',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        $reviewer->update($validated);

        return redirect()->route('admin.reviewers.index')
            ->with('success', 'Reviewer berhasil diperbarui.');
    }

    public function destroy(Reviewer $reviewer)
    {
        $pendingReviews = $reviewer->submissionReviewers()
            ->count();

        if ($pendingReviews > 0) {
            return back()->withErrors([
                'error' => 'Tidak dapat menghapus reviewer yang masih memiliki review yang belum selesai.',
            ]);
        }

        $reviewer->delete();

        return redirect()->route('admin.reviewers.index')
            ->with('success', 'Reviewer berhasil dihapus.');
    }

    public function deactivate(Reviewer $reviewer)
    {
        $reviewer->update([
            'end_date' => Carbon::now()->format('Y-m-d'),
        ]);

        return redirect()->route('admin.reviewers.index')
            ->with('success', 'Reviewer berhasil dinonaktifkan.');
    }

    public function activate(Reviewer $reviewer)
    {
        $reviewer->update([
            'end_date' => null,
        ]);

        return redirect()->route('admin.reviewers.index')
            ->with('success', 'Reviewer berhasil diaktifkan kembali.');
    }
}
