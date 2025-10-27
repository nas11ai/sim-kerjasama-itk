<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Reviewer;
use App\Models\ReviewerRole;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class ReviewerController extends Controller
{
    public function index(Request $request)
    {
        $query = Reviewer::with([
            'user:id,name,email',
            'reviewerRole:id,name'
        ]);

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })->orWhereHas('reviewerRole', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->has('role') && $request->role) {
            $query->where('reviewer_role_id', $request->role);
        }

        // Filter by status (active/inactive)
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

        // Add computed properties
        $reviewers->getCollection()->transform(function ($reviewer) {
            $now = Carbon::now();
            $reviewer->is_active = $reviewer->start_date <= $now &&
                ($reviewer->end_date === null || $reviewer->end_date >= $now);
            return $reviewer;
        });

        // Get reviewer roles for filter
        $reviewerRoles = ReviewerRole::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Reviewers/Index', [
            'reviewers' => $reviewers,
            'reviewerRoles' => $reviewerRoles,
            'filters' => $request->only(['search', 'role', 'status'])
        ]);
    }

    public function create()
    {
        // Get users who can be reviewers (Tenaga Kependidikan role)
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

        $reviewerRoles = ReviewerRole::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Reviewers/Create', [
            'users' => $users,
            'reviewerRoles' => $reviewerRoles
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reviewer_role_id' => 'required|exists:reviewer_roles,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date'
        ]);

        // Check if user already has active reviewer role
        $existingReviewer = Reviewer::where('user_id', $validated['user_id'])
            ->where('start_date', '<=', Carbon::now())
            ->where(function ($query) {
                $query->where('end_date', '>=', Carbon::now())
                    ->orWhereNull('end_date');
            })
            ->exists();

        if ($existingReviewer) {
            return back()->withErrors([
                'user_id' => 'User sudah memiliki peran reviewer yang aktif.'
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
            'reviewerRole:id,name',
            'submissionReviewers.formSubmission.form:id,title'
        ]);

        // Add computed properties
        $now = Carbon::now();
        $reviewer->is_active = $reviewer->start_date <= $now &&
            ($reviewer->end_date === null || $reviewer->end_date >= $now);

        // Get review statistics
        $reviewer->total_reviews = $reviewer->submissionReviewers->count();
        $reviewer->pending_reviews = $reviewer->submissionReviewers()
            // ->whereNull('reviewed_at')
            ->count();
        $reviewer->completed_reviews = $reviewer->submissionReviewers()
            // ->whereNotNull('reviewed_at')
            ->count();

        return Inertia::render('Reviewers/Show', [
            'reviewer' => $reviewer
        ]);
    }

    public function edit(Reviewer $reviewer)
    {
        $reviewer->load(['user:id,name,email', 'reviewerRole:id,name']);

        $reviewerRoles = ReviewerRole::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Reviewers/Edit', [
            'reviewer' => $reviewer,
            'reviewerRoles' => $reviewerRoles
        ]);
    }

    public function update(Request $request, Reviewer $reviewer)
    {
        $validated = $request->validate([
            'reviewer_role_id' => 'required|exists:reviewer_roles,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date'
        ]);

        $reviewer->update($validated);

        return redirect()->route('admin.reviewers.index')
            ->with('success', 'Reviewer berhasil diperbarui.');
    }

    public function destroy(Reviewer $reviewer)
    {
        // Check if reviewer has pending reviews
        $pendingReviews = $reviewer->submissionReviewers()
            ->count();

        if ($pendingReviews > 0) {
            return back()->withErrors([
                'error' => 'Tidak dapat menghapus reviewer yang masih memiliki review yang belum selesai.'
            ]);
        }

        $reviewer->delete();

        return redirect()->route('admin.reviewers.index')
            ->with('success', 'Reviewer berhasil dihapus.');
    }

    public function deactivate(Reviewer $reviewer)
    {
        // Set end_date to today to deactivate
        $reviewer->update([
            'end_date' => Carbon::now()->format('Y-m-d')
        ]);

        return redirect()->route('admin.reviewers.index')
            ->with('success', 'Reviewer berhasil dinonaktifkan.');
    }

    public function activate(Reviewer $reviewer)
    {
        // Remove end_date to activate
        $reviewer->update([
            'end_date' => null
        ]);

        return redirect()->route('admin.reviewers.index')
            ->with('success', 'Reviewer berhasil diaktifkan kembali.');
    }
}
