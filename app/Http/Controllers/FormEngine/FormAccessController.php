<?php

namespace App\Http\Controllers\FormEngine;

use App\Actions\Form\GrantFormAccess;
use App\Actions\Form\RevokeFormAccess;
use App\Data\View\FormEngine\FormHeaderData;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Form\FormAccessControlRequest;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Queries\Form\FormAccessRules;
use App\Queries\Identity\OrganizationOptions;
use App\Support\Identity\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tab Access Control Pembuat Formulir (ADMIN-08-07). Route dijaga `can:schemes.manage` (W88).
 */
class FormAccessController extends Controller
{
    use ResolvesActor;

    public function index(Form $form): Response
    {
        return Inertia::render('FormEngine/FormEditPage', [
            'tab' => 'access',
            'form' => FormHeaderData::fromModel($form),
            'rules' => FormAccessRules::run($form),
            'permissionOptions' => PermissionCatalog::options(FormAccessControl::ALLOWED_PERMISSIONS),
            'organizationOptions' => OrganizationOptions::run(),
        ]);
    }

    public function store(FormAccessControlRequest $request, Form $form): RedirectResponse
    {
        $granted = GrantFormAccess::run($request->toInput($form), $this->actor($request));

        return redirect()->route('admin.forms.access.index', $form)->with('success', count($granted).' aturan akses ditambahkan.');
    }

    // BR-FE-20 — penolakan aturan yang dipakai detail tahap datang dari Action.
    public function destroy(Request $request, Form $form, FormAccessControl $formAccessControl): RedirectResponse
    {
        RevokeFormAccess::run($formAccessControl, $this->actor($request));

        return redirect()->route('admin.forms.access.index', $form)->with('success', 'Aturan akses dihapus.');
    }
}
