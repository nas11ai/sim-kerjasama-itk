<?php

namespace App\Http\Controllers\Identity;

use App\Actions\Identity\DeactivateOrganization;
use App\Actions\Identity\DeleteOrganization;
use App\Actions\Identity\MoveUserToOrganization;
use App\Actions\Identity\ReactivateOrganization;
use App\Actions\Identity\SaveOrganization;
use App\Data\View\SystemConfig\OrganizationUnitData;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\MoveUserRequest;
use App\Http\Requests\Identity\OrganizationRequest;
use App\Models\Organization;
use App\Queries\Identity\OrganizationCensus;
use App\Queries\Identity\OrganizationMembers;
use App\Queries\Identity\OrganizationOptions;
use App\Queries\Identity\OrganizationTree;
use App\Queries\Identity\RevivableInvitations;
use App\Support\Identity\OrganizationTypeCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ADMIN-08-02 — struktur organisasi. Route dijaga `can:organizations.manage` (W105).
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
            // W120 — hanya dimuat lewat partial reload pencarian di dialog Pindahkan User.
            'memberCandidates' => Inertia::optional(fn (): array => $this->memberCandidates($request)),
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

    /** BR-IAM-22 — satu unit saja; turunannya tetap nonaktif. */
    public function reactivate(Organization $organization): RedirectResponse
    {
        $revived = ReactivateOrganization::run($organization);

        return back()->with(
            'success',
            $revived === 0
                ? 'Unit organisasi diaktifkan kembali.'
                : "Unit organisasi diaktifkan kembali. {$revived} undangan registrasi berlaku lagi."
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
    public function store(OrganizationRequest $request): RedirectResponse
    {
        SaveOrganization::run($this->actor($request), $request->toAttributes());

        return back()->with('success', 'Unit organisasi dibuat.');
    }

    /** BR-IAM-17 — mengganti nama, tipe, atau induk sebuah unit. */
    public function update(OrganizationRequest $request, Organization $organization): RedirectResponse
    {
        SaveOrganization::run($this->actor($request), $request->toAttributes(), $organization);

        return back()->with('success', 'Unit organisasi diperbarui.');
    }

    /** BR-IAM-18 — memindahkan user antar unit; prasyarat deaktivasi BR-IAM-14. */
    public function moveUser(MoveUserRequest $request, Organization $organization): RedirectResponse
    {
        MoveUserToOrganization::run($this->actor($request), $request->movedUser(), $organization);

        return back()->with('success', 'Pengguna dipindahkan.');
    }

    private function selectedUnit(string $id): ?OrganizationUnitData
    {
        $unit = $this->unitFromQuery($id);

        return $unit === null
            ? null
            : OrganizationUnitData::of($unit, OrganizationCensus::run($unit), RevivableInvitations::run($unit));
    }

    /** @return list<array{value: string, label: string, hint: string, organizationId: int}> */
    private function memberCandidates(Request $request): array
    {
        $unit = $this->unitFromQuery($request->string('unit')->value());

        return $unit === null ? [] : OrganizationMembers::run($unit, $request->string('member_search')->value());
    }

    private function unitFromQuery(string $id): ?Organization
    {
        return ctype_digit($id) ? Organization::query()->find($id) : null;
    }
}
