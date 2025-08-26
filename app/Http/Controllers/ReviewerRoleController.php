<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ReviewerRole;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewerRoleController extends Controller
{
    public function index(Request $request)
    {
        $query = ReviewerRole::withCount('reviewers');

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('is_active', $request->status === 'active');
        }

        $reviewerRoles = $query->orderBy('created_at', 'desc')->paginate(10);

        return Inertia::render('ReviewerRoles/Index', [
            'reviewerRoles' => $reviewerRoles,
            'filters' => $request->only(['search', 'status'])
        ]);
    }

    public function create()
    {
        return Inertia::render('ReviewerRoles/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:reviewer_roles,name',
            'is_active' => 'boolean'
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        ReviewerRole::create($validated);

        return redirect()->route('admin.reviewer-roles.index')
            ->with('success', 'Reviewer role berhasil ditambahkan.');
    }

    public function show(ReviewerRole $reviewerRole)
    {
        $reviewerRole->load([
            'reviewers.user:id,name,email',
            'reviewers' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }
        ]);

        return Inertia::render('ReviewerRoles/Show', [
            'reviewerRole' => $reviewerRole
        ]);
    }

    public function edit(ReviewerRole $reviewerRole)
    {
        return Inertia::render('ReviewerRoles/Edit', [
            'reviewerRole' => $reviewerRole
        ]);
    }

    public function update(Request $request, ReviewerRole $reviewerRole)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:reviewer_roles,name,' . $reviewerRole->id,
            'is_active' => 'boolean'
        ]);

        $reviewerRole->update($validated);

        return redirect()->route('admin.reviewer-roles.index')
            ->with('success', 'Reviewer role berhasil diperbarui.');
    }

    public function destroy(ReviewerRole $reviewerRole)
    {
        // Check if role has active reviewers
        $activeReviewers = $reviewerRole->reviewers()
            ->where('start_date', '<=', now())
            ->where(function ($query) {
                $query->where('end_date', '>=', now())
                    ->orWhereNull('end_date');
            })
            ->count();

        if ($activeReviewers > 0) {
            return back()->withErrors([
                'error' => 'Tidak dapat menghapus reviewer role yang masih memiliki reviewer aktif.'
            ]);
        }

        $reviewerRole->delete();

        return redirect()->route('admin.reviewer-roles.index')
            ->with('success', 'Reviewer role berhasil dihapus.');
    }

    public function toggleStatus(ReviewerRole $reviewerRole)
    {
        $reviewerRole->update([
            'is_active' => !$reviewerRole->is_active
        ]);

        $status = $reviewerRole->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('admin.reviewer-roles.index')
            ->with('success', "Reviewer role berhasil {$status}.");
    }
}
