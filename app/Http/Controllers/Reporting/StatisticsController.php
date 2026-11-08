<?php

namespace App\Http\Controllers\Reporting;

use App\Data\Reporting\StatisticsFilters;
use App\Enums\StatisticsTab;
use App\Http\Controllers\Controller;
use App\Queries\Reporting\MonevStatusCounts;
use App\Queries\Reporting\ReviewerCoverage;
use App\Queries\Reporting\ReviewerWorkload;
use App\Queries\Reporting\StageBacklog;
use App\Queries\Reporting\StatisticsFilterOptions;
use App\Queries\Reporting\StatisticsOverview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * OPERATOR-07-01 — Dashboard Statistik. Hanya props tab aktif yang dihitung (W156).
 */
class StatisticsController extends Controller
{
    public function show(Request $request, ?string $tab = null): Response
    {
        $active = StatisticsTab::tryFrom($tab ?? StatisticsTab::Overview->value);
        abort_if($active === null, 404);
        $filters = StatisticsFilters::fromRequest($request);
        // BR-SM-07 — daftar bernama dan drill-down ke daftar pengajuan hanya bagi view-all (W148).
        $viewSubmissions = $request->user()?->can('submissions.view-all') ?? false;

        return Inertia::render('Reporting/StatsPage', [
            'tab' => $active->value,
            'tabs' => $this->tabs($request),
            'filters' => $filters,
            'filterKeys' => $active->filterKeys(),
            ...StatisticsFilterOptions::run(),
            'can' => ['viewSubmissions' => $viewSubmissions],
            ...match ($active) {
                StatisticsTab::Overview => ['overview' => StatisticsOverview::run($filters, $viewSubmissions)],
                StatisticsTab::Now => ['now' => [
                    'backlog' => StageBacklog::run($filters, $viewSubmissions),
                    'monev' => MonevStatusCounts::run($filters, $viewSubmissions),
                    'coverage' => ReviewerCoverage::run($filters),
                    'workload' => ReviewerWorkload::run(),
                ]],
                // R1-2 (Task 6–7) mengisi tab lainnya.
                StatisticsTab::History, StatisticsTab::FollowUp => [],
            },
        ]);
    }

    /**
     * Berpindah tab membawa filter yang dipilih pengguna, bukan nilai default-nya.
     *
     * @return list<array{value: string, label: string, href: string}>
     */
    private function tabs(Request $request): array
    {
        $query = $request->only(['year', 'period', 'scheme']);

        return array_map(fn (StatisticsTab $tab): array => [
            'value' => $tab->value,
            'label' => $tab->label(),
            'href' => route('reporting.stats', [
                'tab' => $tab === StatisticsTab::Overview ? null : $tab->value,
                ...$query,
            ]),
        ], StatisticsTab::cases());
    }
}
