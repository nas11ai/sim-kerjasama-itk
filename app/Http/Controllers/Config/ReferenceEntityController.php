<?php

namespace App\Http\Controllers\Config;

use App\Actions\Config\DeleteReferenceEntity;
use App\Actions\Config\SaveReferenceEntity;
use App\Actions\Config\ToggleReferenceEntity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Config\ReferenceEntityRequest;
use App\Queries\Config\ReferenceCategories;
use App\Queries\Notification\NotificationChannelDeclarations;
use App\Support\Config\ReferenceEntity;
use App\Support\Config\ReferenceEntityRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BR-SC-01, BR-SC-02, BR-SC-03 — layar Konfigurasi Umum (ADMIN-08-10). Route dijaga
 * `can:schemes.manage` (W105). Jumlah pemakai dikirim tiap baris agar layar menjelaskan
 * sebelum Hapus ditekan (BR-SC-01).
 */
class ReferenceEntityController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SystemConfig/GeneralPage', [
            'categories' => ReferenceCategories::run(),
            // BR-NOT-06 — channel per tipe event tinggal di layar konfigurasi yang sama (W112: tab Lainnya).
            'notificationChannels' => NotificationChannelDeclarations::run(),
            'channelOptions' => NotificationChannelDeclarations::channelOptions(),
        ]);
    }

    public function store(ReferenceEntityRequest $request, string $entity): RedirectResponse
    {
        $reference = ReferenceEntityRegistry::get($entity);

        SaveReferenceEntity::run($reference, $request->toInput());

        return back()->with('success', "{$reference->label} ditambahkan.");
    }

    public function update(ReferenceEntityRequest $request, string $entity, int $id): RedirectResponse
    {
        $reference = ReferenceEntityRegistry::get($entity);
        $record = $this->find($reference, $id);

        SaveReferenceEntity::run($reference, $request->toInput(), $record);

        return back()->with('success', "{$reference->label} diperbarui.");
    }

    public function destroy(string $entity, int $id): RedirectResponse
    {
        $reference = ReferenceEntityRegistry::get($entity);

        DeleteReferenceEntity::run($reference, $this->find($reference, $id));

        return back()->with('success', "{$reference->label} dihapus.");
    }

    public function toggle(Request $request, string $entity, int $id): RedirectResponse
    {
        $reference = ReferenceEntityRegistry::get($entity);
        $active = $request->boolean('is_active');

        ToggleReferenceEntity::run($reference, $this->find($reference, $id), $active);

        return back()->with('success', $active ? "{$reference->label} diaktifkan." : "{$reference->label} dinonaktifkan.");
    }

    /**
     * @param  ReferenceEntity<covariant Model>  $entity
     */
    private function find(ReferenceEntity $entity, int $id): Model
    {
        $model = $entity->model;

        return $model::query()->findOrFail($id);
    }
}
