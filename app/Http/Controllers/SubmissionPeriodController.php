<?php

namespace App\Http\Controllers;

use App\Actions\Period\ExtendSubmissionDates;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Support\SubmissionPeriodGate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'submission_dates' => 'required|array|min:1',
            'submission_dates.*.label' => 'required|string|max:255|exists:submission_date_labels,name',
            'submission_dates.*.date' => 'required|date',
            'form_phase_ids' => 'required|array|min:1',
            'form_phase_ids.*' => 'exists:form_phases,id',
        ]);

        // Validate date order
        $dates = collect($request->submission_dates)->sortBy('date');
        if ($dates->count() > 1) {
            $firstDate = Carbon::parse($dates->first()['date']);
            $lastDate = Carbon::parse($dates->last()['date']);

            if ($firstDate->gte($lastDate)) {
                return back()->withErrors(['submission_dates' => 'Tanggal berakhir harus setelah tanggal mulai.']);
            }
        }

        try {
            DB::beginTransaction();

            // Create submission period
            $submissionPeriod = SubmissionPeriod::create([
                'name' => $request->name,
            ]);

            // Create submission dates
            foreach ($request->submission_dates as $dateData) {
                SubmissionDate::create([
                    'submission_period_id' => $submissionPeriod->id,
                    'submission_date_label_id' => SubmissionDateLabel::where('name', $dateData['label'])->first()->id,
                    'datetime' => $dateData['date'],
                ]);
            }

            // Create form phase associations
            foreach ($request->form_phase_ids as $formPhaseId) {
                SubmissionPeriodPhase::create([
                    'submission_period_id' => $submissionPeriod->id,
                    'form_phase_id' => $formPhaseId,
                ]);
            }

            DB::commit();

            return redirect()->route('admin.submission-periods.index')
                ->with('success', 'Periode pengiriman berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal membuat periode pengiriman: '.$e->getMessage()]);
        }
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
            // BR-SCH-08 — Operator melihat berapa draft berjalan di periode ini
            // sebelum menggeser tanggalnya. Memundurkan tenggat akan mengabari
            // mereka; memajukannya tidak, karena tidak ada kesempatan baru yang
            // diberikan.
            'runningDrafts' => FormSubmission::query()
                ->where('submission_period_id', $submissionPeriod->id)
                ->where('is_submitted', false)
                ->distinct()
                ->count('submitted_by'),
        ]);
    }

    public function update(Request $request, SubmissionPeriod $submissionPeriod)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'submission_dates' => 'required|array|min:1',
            'submission_dates.*.label' => 'required|string|max:255|exists:submission_date_labels,name',
            'submission_dates.*.date' => 'required|date',
            'form_phase_ids' => 'required|array|min:1',
            'form_phase_ids.*' => 'exists:form_phases,id',
        ]);

        // Validate date order
        $dates = collect($request->submission_dates)->sortBy('date');
        if ($dates->count() > 1) {
            $firstDate = Carbon::parse($dates->first()['date']);
            $lastDate = Carbon::parse($dates->last()['date']);

            if ($firstDate->gte($lastDate)) {
                return back()->withErrors(['submission_dates' => 'Tanggal berakhir harus setelah tanggal mulai.']);
            }
        }

        try {
            DB::beginTransaction();

            // Update submission period
            $submissionPeriod->update([
                'name' => $request->name,
            ]);

            $submissionPeriod->submissionPeriodPhases()->delete();

            // BR-SCH-08 — pembaruan tanggal beserta pemberitahuannya ada di
            // Action: yang memicu notifikasi adalah batas yang **mundur**, dan
            // membedakan itu menuntut membandingkan nilai lama dengan baru.
            // BR-SCH-08 — jumlah peneliti yang diberi tahu dikembalikan Action
            // dan ikut dibawa ke layar. Sebelumnya nilainya dibuang, jadi
            // Operator memperpanjang tenggat tanpa pernah tahu apakah ada yang
            // dikabari — dan perpanjangan yang diam praktis sama dengan tidak
            // diberikan.
            $diberitahu = ExtendSubmissionDates::run($submissionPeriod, $request->submission_dates);

            // Recreate form phase associations
            foreach ($request->form_phase_ids as $formPhaseId) {
                SubmissionPeriodPhase::create([
                    'submission_period_id' => $submissionPeriod->id,
                    'form_phase_id' => $formPhaseId,
                ]);
            }

            DB::commit();

            return redirect()->route('admin.submission-periods.index')
                ->with('success', 'Periode pengiriman berhasil diperbarui.')
                ->with('notifiedDraftOwners', $diberitahu);
        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal memperbarui periode pengiriman: '.$e->getMessage()]);
        }
    }

    public function destroy(SubmissionPeriod $submissionPeriod)
    {
        try {
            DB::beginTransaction();

            // Delete all related records
            $submissionPeriod->submissionDates()->delete();
            $submissionPeriod->submissionPeriodPhases()->delete();
            $submissionPeriod->delete();

            DB::commit();

            return redirect()->route('admin.submission-periods.index')
                ->with('success', 'Submission period deleted successfully.');
        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal menghapus periode pengiriman: '.$e->getMessage()]);
        }
    }

    // BR-FE-01 — satu predikat dengan yang menggerbangi pembuatan pengajuan.
    // Salinan lama di sini membaca tanggal paling awal sebagai tanggal mulai
    // dan melupakan is_force_closed, sehingga layar menampilkan "Aktif" untuk
    // periode yang menolak setiap pengajuan.
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

    public function storeLabel(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:submission_date_labels,name',
        ]);

        $label = SubmissionDateLabel::create([
            'name' => $request->name,
        ]);

        // Kembalikan JSON, bukan redirect
        return response()->json($label);
    }
}
