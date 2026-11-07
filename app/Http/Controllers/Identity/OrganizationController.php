<?php

namespace App\Http\Controllers\Identity;

use App\Actions\Identity\DeactivateOrganization;
use App\Actions\Identity\DeleteOrganization;
use App\Actions\Identity\MoveUserToOrganization;
use App\Actions\Identity\SaveOrganization;
use App\Data\View\SystemConfig\OrganizationUnitData;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Queries\Identity\OrganizationCensus;
use App\Queries\Identity\OrganizationOptions;
use App\Queries\Identity\OrganizationTree;
use App\Support\Identity\OrganizationTypeCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ADMIN-08-02 — struktur organisasi. Route dijaga `can:users.manage` (W105, Given IAM-ORG-09).
 * Hapus (BR-IAM-08) dan nonaktifkan (BR-IAM-14) dua endpoint karena ambangnya berbeda.
 */
class OrganizationController extends Controller
{
    use ResolvesActor;

    public function index(Request $request): Response
    {
        return Inertia::render('SystemConfig/OrganizationPage', [
            'tree' => fn (): array => OrganizationTree::run(),
            'selected' => fn (): ?OrganizationUnitData => $this->selectedUnit($request->string('unit')->value()),
            'typeOptions' => OrganizationTypeCatalog::options(),
            'parentOptions' => fn (): array => OrganizationOptions::run(),
        ]);
    }

    /** BR-IAM-14 — ambangnya user **aktif**, bukan profil apa pun. */
    public function deactivate(Organization $organization): RedirectResponse
    {
        $terdampak = DeactivateOrganization::run($organization);

        return back()->with(
            'success',
            $terdampak === 0
                ? 'Unit organisasi dinonaktifkan.'
                : "Unit organisasi dinonaktifkan. {$terdampak} undangan registrasi ikut tidak berlaku."
        );
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $tercabut = DeleteOrganization::run($organization);

        return back()->with(
            'success',
            $tercabut === 0
                ? 'Unit organisasi dihapus.'
                : "Unit organisasi dihapus. {$tercabut} undangan registrasi ikut tercabut."
        );
    }

    /** BR-IAM-17 — membuat unit baru, akar maupun sub-unit. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'type' => 'required|string|max:64',
            'parent_id' => 'nullable|exists:organizations,id',
        ]);

        SaveOrganization::run($this->actor($request), $validated);

        return back()->with('success', 'Unit organisasi dibuat.');
    }

    /** BR-IAM-17 — mengganti nama, tipe, atau induk sebuah unit. */
    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:191',
            'type' => 'sometimes|string|max:64',
            'parent_id' => 'sometimes|nullable|exists:organizations,id',
        ]);

        SaveOrganization::run($this->actor($request), $validated, $organization);

        return back()->with('success', 'Unit organisasi diperbarui.');
    }

    /** BR-IAM-18 — memindahkan user antar unit; prasyarat deaktivasi BR-IAM-14. */
    public function moveUser(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        MoveUserToOrganization::run($this->actor($request), User::findOrFail($validated['user_id']), $organization);

        return back()->with('success', 'User dipindahkan.');
    }

    private function selectedUnit(string $id): ?OrganizationUnitData
    {
        $unit = ctype_digit($id) ? Organization::query()->find($id) : null;

        return $unit === null ? null : OrganizationUnitData::of($unit, OrganizationCensus::run($unit));
    }
}
