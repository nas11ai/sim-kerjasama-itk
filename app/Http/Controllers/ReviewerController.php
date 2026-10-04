<?php

namespace App\Http\Controllers;

use App\Actions\Identity\AppointReviewer;
use App\Actions\Identity\ChangeReviewerTenure;
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

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                })->orWhere('reviewer_type', 'ilike', "%{$search}%");
            });
        }

        // Filter by type
        if ($request->type) {
            $query->where('reviewer_type', $request->type);
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

        // Get reviewer types for filter
        $reviewerTypes = [
            [
                'id' => 'internal',
                'name' => 'internal',
            ],
            [
                'id' => 'external',
                'name' => 'external',
            ],
        ];

        return Inertia::render('Reviewers/IndexPage', [
            'reviewers' => $reviewers,
            'reviewerTypes' => $reviewerTypes,
            'filters' => $request->only(['search', 'type', 'status']),
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

        $reviewerTypes = [
            [
                'id' => 'internal',
                'name' => 'internal',
            ],
            [
                'id' => 'external',
                'name' => 'external',
            ],
        ];

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

        // BR-IAM-11 — penunjukan lewat Action: role, idempoten per orang, BR-IAM-05,
        // notifikasi, audit. ValidationException-nya mengalir sebagai error sesi.
        AppointReviewer::run(
            User::findOrFail($validated['user_id']),
            $validated['reviewer_type'],
            $validated['start_date'],
            $validated['end_date'] ?? null,
        );

        return redirect()->route('admin.reviewers.index')
            ->with('success', 'Reviewer berhasil ditambahkan.');
    }

    public function show(Reviewer $reviewer)
    {
        $reviewer->load([
            'user:id,name,email',
            'submissionReviewers.formSubmission.form:id,title',
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

        return Inertia::render('Reviewers/ShowPage', [
            'reviewer' => $reviewer,
        ]);
    }

    public function edit(Reviewer $reviewer)
    {
        $reviewer->load(['user:id,name,email']);

        $reviewerTypes = [
            [
                'id' => 'internal',
                'name' => 'internal',
            ],
            [
                'id' => 'external',
                'name' => 'external',
            ],
        ];

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

        // BR-IAM-11 — hanya perubahan tipe yang menyentuh role; tanggal lewat Action masa tugas.
        if ($reviewer->reviewer_type !== $validated['reviewer_type']) {
            AppointReviewer::run(User::findOrFail($reviewer->user_id), $validated['reviewer_type']);
        }

        ChangeReviewerTenure::run($reviewer, $validated['start_date'], $validated['end_date'] ?? null);

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
                'error' => 'Tidak dapat menghapus reviewer yang masih memiliki review yang belum selesai.',
            ]);
        }

        $reviewer->delete();

        return redirect()->route('admin.reviewers.index')
            ->with('success', 'Reviewer berhasil dihapus.');
    }

    public function deactivate(Reviewer $reviewer)
    {
        // K2 — end_date kemarin: keluar masa tugas hari ini di bawah definisi inklusif.
        ChangeReviewerTenure::run(
            $reviewer,
            $reviewer->start_date?->toDateString() ?? Carbon::today()->toDateString(),
            Carbon::yesterday()->toDateString(),
        );

        return redirect()->route('admin.reviewers.index')
            ->with('success', 'Reviewer berhasil dinonaktifkan.');
    }

    public function activate(Reviewer $reviewer)
    {
        ChangeReviewerTenure::run(
            $reviewer,
            $reviewer->start_date?->toDateString() ?? Carbon::today()->toDateString(),
            null,
        );

        return redirect()->route('admin.reviewers.index')
            ->with('success', 'Reviewer berhasil diaktifkan kembali.');
    }
}
