<?php

namespace App\Http\Controllers;

use App\Actions\Period\CreateSubmissionPeriod;
use App\Actions\Period\DeleteSubmissionPeriod;
use App\Actions\Period\ReviseSubmissionPeriod;
use App\Http\Requests\Period\SubmissionPeriodRequest;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Support\SubmissionPeriodGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SubmissionPeriodController extends Controller
{
    public function index(Request $request)
    {
        $query = SubmissionPeriod::with([
            'submissionDates',
            'submissionPeriodPhases.formPhase',
        ]);

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('name', 'ilike', "%{$search}%");
        }

        $submissionPeriods = $query->orderBy('created_at', 'desc')->paginate(10);

        // Add computed properties for each period
        $submissionPeriods->getCollection()->transform(function ($period) {
            $dates = $period->submissionDates->sortBy('datetime');
            $period->start_date = $dates->first()?->datetime;
            $period->end_date = $dates->last()?->datetime;
            $period->is_active = $this->isPeriodActive($period);
            $period->status = $this->getPeriodStatus($period);

            return $period;
        });

        return Inertia::render('SubmissionPeriods/IndexPage', [
            'submissionPeriods' => $submissionPeriods,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        $formPhases = FormPhase::where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'description']);

        $submissionDateLabels = SubmissionDateLabel::orderBy('name')->get(['id', 'name']);

        return Inertia::render('SubmissionPeriods/CreatePage', [
            'formPhases' => $formPhases,
            'submissionDateLabels' => $submissionDateLabels,
        ]);
    }

    public function store(SubmissionPeriodRequest $request)
    {
        CreateSubmissionPeriod::run($request->toInput(), $request->user());

        return redirect()->route('admin.submission-periods.index')
            ->with('success', 'Periode pengiriman berhasil dibuat.');
    }

    public function show(SubmissionPeriod $submissionPeriod)
    {
        $submissionPeriod->load([
            'submissionDates' => function ($query) {
                $query->orderBy('datetime');
            },
            'submissionPeriodPhases.formPhase',
        ]);

        // Add computed properties
        $submissionPeriod->is_active = $this->isPeriodActive($submissionPeriod);
        $submissionPeriod->status = $this->getPeriodStatus($submissionPeriod);
        $submissionPeriod->days_remaining = $this->getDaysRemaining($submissionPeriod);

        return Inertia::render('SubmissionPeriods/ShowPage', [
            'submissionPeriod' => $submissionPeriod,
        ]);
    }

    public function edit(SubmissionPeriod $submissionPeriod)
    {
        $submissionPeriod->load([
            'submissionDates' => function ($query) {
                $query->with('submissionDateLabel')
                    ->orderBy('datetime');
            },
            'submissionPeriodPhases.formPhase',
        ]);

        $formPhases = FormPhase::where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'description']);

        $submissionDateLabels = SubmissionDateLabel::orderBy('name')->get(['id', 'name']);

        return Inertia::render('SubmissionPeriods/EditPage', [
            'submissionPeriod' => $submissionPeriod,
            'formPhases' => $formPhases,
            'submissionDateLabels' => $submissionDateLabels,
            // BR-SCH-08 — Operator melihat jumlah draft berjalan sebelum menggeser
            // tanggal. Memundurkan tenggat mengabari mereka; memajukannya tidak,
            // karena tidak ada kesempatan baru yang diberikan.
            'runningDrafts' => FormSubmission::query()
                ->where('submission_period_id', $submissionPeriod->id)
                ->where('is_submitted', false)
                ->distinct()
                ->count('submitted_by'),
        ]);
    }

    public function update(SubmissionPeriodRequest $request, SubmissionPeriod $submissionPeriod)
    {
        // BR-SCH-08 — jumlah peneliti yang diberi tahu ikut dibawa ke layar;
        // perpanjangan yang diam praktis sama dengan tidak diberikan.
        $notified = ReviseSubmissionPeriod::run($submissionPeriod, $request->toInput(), $request->user());

        return redirect()->route('admin.submission-periods.index')
            ->with('success', 'Periode pengiriman berhasil diperbarui.')
            ->with('notifiedDraftOwners', $notified);
    }

    public function destroy(SubmissionPeriod $submissionPeriod)
    {
        $actor = Auth::user();

        abort_unless($actor !== null, 403);

        // BR-FE-21 — penjagaannya berjalan sebelum tanggal dan tahapnya
        // disentuh, bukan mengandalkan rollback transaksi.
        DeleteSubmissionPeriod::run($submissionPeriod, $actor);

        return redirect()->route('admin.submission-periods.index')
            ->with('success', 'Periode pengajuan berhasil dihapus.');
    }

    // BR-FE-01 — satu predikat dengan yang menggerbangi pembuatan pengajuan. Salinan
    // lama membaca tanggal paling awal sebagai tanggal mulai dan melupakan
    // is_force_closed: layar "Aktif" untuk periode yang menolak setiap pengajuan.
    private function isPeriodActive(SubmissionPeriod $period): bool
    {
        return SubmissionPeriodGate::isOpen($period);
    }

    private function getPeriodStatus(SubmissionPeriod $period): string
    {
        return SubmissionPeriodGate::displayStatus($period);
    }

    private function getDaysRemaining(SubmissionPeriod $period): ?int
    {
        return SubmissionPeriodGate::daysRemaining($period);
    }
}
