<?php

namespace App\Http\Controllers;

use App\Models\FormPhase;
use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodDetail;
use App\Models\SubmissionPeriodPhase;
use App\Models\SubmissionRule;
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
            'submissionPeriodDetails.submissionRule',
        ]);

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $submissionPeriods = $query->orderBy('created_at', 'desc')->paginate(10);

        // Add computed properties for each period
        $submissionPeriods->getCollection()->transform(function ($period) {
            $dates = $period->submissionDates->sortBy('datetime');
            $period->start_date = $dates->first()?->date;
            $period->end_date = $dates->last()?->date;
            $period->is_active = $this->isPeriodActive($period);
            $period->status = $this->getPeriodStatus($period);

            return $period;
        });

        return Inertia::render('SubmissionPeriods/Index', [
            'submissionPeriods' => $submissionPeriods,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        $formPhases = FormPhase::where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'description']);

        $submissionRules = SubmissionRule::orderBy('label')->get();

        $submissionDateLabels = SubmissionDateLabel::orderBy('name')->get(['id', 'name']);

        return Inertia::render('SubmissionPeriods/Create', [
            'formPhases' => $formPhases,
            'submissionRules' => $submissionRules,
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
            'submission_rule_ids' => 'nullable|array',
            'submission_rule_ids.*' => 'exists:submission_rules,id',
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

            // Create submission rule associations (optional)
            if ($request->submission_rule_ids) {
                foreach ($request->submission_rule_ids as $ruleId) {
                    SubmissionPeriodDetail::create([
                        'submission_period_id' => $submissionPeriod->id,
                        'submission_rule_id' => $ruleId,
                    ]);
                }
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
            'submissionPeriodDetails.submissionRule',
        ]);

        // Add computed properties
        $submissionPeriod->is_active = $this->isPeriodActive($submissionPeriod);
        $submissionPeriod->status = $this->getPeriodStatus($submissionPeriod);
        $submissionPeriod->days_remaining = $this->getDaysRemaining($submissionPeriod);

        return Inertia::render('SubmissionPeriods/Show', [
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
            'submissionPeriodDetails.submissionRule',
        ]);

        $formPhases = FormPhase::where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'description']);

        $submissionRules = SubmissionRule::orderBy('label')->get();

        $submissionDateLabels = SubmissionDateLabel::orderBy('name')->get(['id', 'name']);

        return Inertia::render('SubmissionPeriods/Edit', [
            'submissionPeriod' => $submissionPeriod,
            'formPhases' => $formPhases,
            'submissionRules' => $submissionRules,
            'submissionDateLabels' => $submissionDateLabels,
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
            'submission_rule_ids' => 'nullable|array',
            'submission_rule_ids.*' => 'exists:submission_rules,id',
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

            // Delete existing relations
            $submissionPeriod->submissionDates()->delete();
            $submissionPeriod->submissionPeriodPhases()->delete();
            $submissionPeriod->submissionPeriodDetails()->delete();

            // Recreate submission dates
            foreach ($request->submission_dates as $dateData) {
                SubmissionDate::create([
                    'submission_period_id' => $submissionPeriod->id,
                    'submission_date_label_id' => SubmissionDateLabel::where('name', $dateData['label'])->first()->id,
                    'datetime' => $dateData['date'],
                ]);
            }

            // Recreate form phase associations
            foreach ($request->form_phase_ids as $formPhaseId) {
                SubmissionPeriodPhase::create([
                    'submission_period_id' => $submissionPeriod->id,
                    'form_phase_id' => $formPhaseId,
                ]);
            }

            // Recreate submission rule associations (optional)
            if ($request->submission_rule_ids) {
                foreach ($request->submission_rule_ids as $ruleId) {
                    SubmissionPeriodDetail::create([
                        'submission_period_id' => $submissionPeriod->id,
                        'submission_rule_id' => $ruleId,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('admin.submission-periods.index')
                ->with('success', 'Periode pengiriman berhasil diperbarui.');
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
            $submissionPeriod->submissionPeriodDetails()->delete();
            $submissionPeriod->delete();

            DB::commit();

            return redirect()->route('admin.submission-periods.index')
                ->with('success', 'Submission period deleted successfully.');
        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal menghapus periode pengiriman: '.$e->getMessage()]);
        }
    }

    private function isPeriodActive(SubmissionPeriod $period): bool
    {
        $now = Carbon::now();
        $dates = $period->submissionDates->sortBy('datetime');

        if ($dates->isEmpty()) {
            return false;
        }

        $startDate = Carbon::parse($dates->first()->date);
        $endDate = Carbon::parse($dates->last()->date);

        return $now->between($startDate, $endDate);
    }

    private function getPeriodStatus(SubmissionPeriod $period): string
    {
        $now = Carbon::now();
        $dates = $period->submissionDates->sortBy('datetime');

        if ($dates->isEmpty()) {
            return 'no_dates';
        }

        $startDate = Carbon::parse($dates->first()->date);
        $endDate = Carbon::parse($dates->last()->date);

        if ($now->lt($startDate)) {
            return 'upcoming';
        } elseif ($now->between($startDate, $endDate)) {
            return 'active';
        } else {
            return 'expired';
        }
    }

    private function getDaysRemaining(SubmissionPeriod $period): ?int
    {
        $dates = $period->submissionDates->sortBy('datetime');

        if ($dates->isEmpty()) {
            return null;
        }

        $endDate = Carbon::parse($dates->last()->date);
        $now = Carbon::now();

        if ($now->gt($endDate)) {
            return 0;
        }

        return $now->diffInDays($endDate, false);
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
