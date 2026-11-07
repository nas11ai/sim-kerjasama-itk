<?php

namespace App\Http\Controllers\Scheme;

use App\Actions\Scheme\ActivateScheme;
use App\Actions\Scheme\DeactivateScheme;
use App\Actions\Scheme\SaveScheme;
use App\Data\View\Scheme\SchemeEditData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scheme\SchemeRequest;
use App\Models\Scheme;
use App\Queries\Scheme\SchemeDirectory;
use App\Queries\Scheme\SchemeFormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ADMIN-08-03/04 — pengelolaan skema. Route dijaga `can:schemes.manage` (W105).
 */
class SchemeController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'type' => $request->string('type')->value(),
            'status' => $request->string('status')->value(),
        ];

        return Inertia::render('Scheme/IndexPage', [
            'filters' => $filters,
            ...SchemeDirectory::run($filters),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Scheme/FormPage', ['scheme' => null, 'options' => SchemeFormOptions::run(null)]);
    }

    public function store(SchemeRequest $request): RedirectResponse
    {
        $scheme = SaveScheme::run($request->toInput());

        // W106 — skema baru langsung dibuka di halaman suntingnya.
        return redirect()->route('admin.schemes.edit', $scheme)->with('success', 'Skema dibuat.');
    }

    public function edit(Scheme $scheme): Response
    {
        return Inertia::render('Scheme/FormPage', [
            'scheme' => SchemeEditData::fromModel($scheme),
            'options' => SchemeFormOptions::run($scheme),
        ]);
    }

    public function update(SchemeRequest $request, Scheme $scheme): RedirectResponse
    {
        SaveScheme::run($request->toInput(), $scheme);

        return back()->with('success', 'Skema diperbarui.');
    }

    /** BR-SCH-01 — penonaktifan diblokir selama masih dipakai pengajuan aktif. */
    public function deactivate(Scheme $scheme): RedirectResponse
    {
        DeactivateScheme::run($scheme);

        return back()->with('success', 'Skema dinonaktifkan.');
    }

    public function activate(Scheme $scheme): RedirectResponse
    {
        ActivateScheme::run($scheme);

        return back()->with('success', 'Skema diaktifkan kembali.');
    }
}
