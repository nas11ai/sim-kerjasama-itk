<?php

namespace App\Http\Controllers\Reporting;

use App\Data\Reporting\StatisticsFilters;
use App\Enums\StatisticsTab;
use App\Http\Controllers\Controller;
use App\Queries\Reporting\StatisticsFilterOptions;
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

        return Inertia::render('Reporting/StatsPage', [
            'tab' => $active->value,
            'tabs' => $this->tabs($request),
            'filters' => StatisticsFilters::fromRequest($request),
            'filterKeys' => $active->filterKeys(),
            ...StatisticsFilterOptions::run(),
            // BR-SM-07 — daftar bernama pengajuan hanya bagi view-all (W148).
            'can' => ['viewSubmissions' => $request->user()?->can('submissions.view-all') ?? false],
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
