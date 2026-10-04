<?php

namespace App\Http\Controllers;

use App\Actions\Identity\AppointReviewer;
use App\Actions\Identity\ChangeReviewerTenure;
use App\Actions\Identity\DismissReviewer;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Queries\Review\ReviewerDirectory;
use App\Queries\Review\ReviewersWithinTenure;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReviewerController extends Controller
{
    public function index(Request $request)
    {
        $reviewers = ReviewerDirectory::run(
            $request->string('search')->toString(),
            $request->string('type')->toString(),
            $request->string('status')->toString(),
        );

        return Inertia::render('Reviewers/IndexPage', [
            'reviewers' => $reviewers,
            'reviewerTypes' => $this->reviewerTypes(),
            'filters' => $request->only(['search', 'type', 'status']),
        ]);
    }

    public function create()
    {
        // Get users who can be reviewers (Tenaga Kependidikan role)
        $users = User::whereHas('roles', function ($query) {
            $query->where('name', 'Tenaga Kependidikan');
        })
            // K2 — kandidat: belum punya masa tugas yang sedang berjalan.
            ->whereDoesntHave('reviewers', fn ($query) => $query
                ->whereIn('id', ReviewersWithinTenure::run()->select('id')))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Reviewers/CreatePage', [
            'users' => $users,
            'reviewerTypes' => $this->reviewerTypes(),
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

        $reviewer->is_active = $reviewer->isWithinTenure();

        // K5 — dari evaluation_status: selesai = beres; menunggu = penugasan aktif belum beres.
        $assignments = $reviewer->submissionReviewers();
        $reviewer->total_reviews = $reviewer->submissionReviewers->count();
        $reviewer->completed_reviews = (clone $assignments)
            ->whereIn('evaluation_status', SubmissionReviewer::SETTLED_EVALUATION)->count();
        $reviewer->pending_reviews = (clone $assignments)->unsettledEvaluation()->count();

        return Inertia::render('Reviewers/ShowPage', [
            'reviewer' => $reviewer,
        ]);
    }

    public function edit(Reviewer $reviewer)
    {
        $reviewer->load(['user:id,name,email']);

        return Inertia::render('Reviewers/EditPage', [
            'reviewer' => $reviewer,
            'reviewerTypes' => $this->reviewerTypes(),
        ]);
    }

    public function update(Request $request, Reviewer $reviewer)
    {
        $validated = $request->validate([
            'reviewer_type' => 'required|in:internal,external',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        // BR-IAM-11 — satu record per orang. AppointReviewer memilih record pertama, jadi
        // mengubah duplikat akan mengubah baris yang salah: tolak sampai dirapikan.
        if (Reviewer::where('user_id', $reviewer->user_id)->count() > 1) {
            return back()->withErrors([
                'user_id' => 'User ini punya lebih dari satu record reviewer; rapikan duplikatnya dulu.',
            ]);
        }

        // Satu transaksi: masa tugas dulu, penunjukan (yang memberi notifikasi) terakhir.
        DB::transaction(function () use ($reviewer, $validated): void {
            ChangeReviewerTenure::run($reviewer, $validated['start_date'], $validated['end_date'] ?? null);

            if ($reviewer->reviewer_type !== $validated['reviewer_type']) {
                AppointReviewer::run(User::findOrFail($reviewer->user_id), $validated['reviewer_type']);
            }
        });

        return redirect()->route('admin.reviewers.index')
            ->with('success', 'Reviewer berhasil diperbarui.');
    }

    public function destroy(Reviewer $reviewer)
    {
        // K4 — penolakan berupa ValidationException (galat sesi 'error').
        DismissReviewer::run($reviewer);

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

    /** @return list<array{id: string, name: string}> */
    private function reviewerTypes(): array
    {
        return array_map(
            static fn (string $type): array => ['id' => $type, 'name' => $type],
            [AppointReviewer::TYPE_INTERNAL, AppointReviewer::TYPE_EXTERNAL],
        );
    }
}
