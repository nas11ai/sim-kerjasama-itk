<?php

namespace App\Http\Controllers\Config;

use App\Actions\Config\CountReferenceEntityUsage;
use App\Actions\Config\DeleteReferenceEntity;
use App\Actions\Config\SaveReferenceEntity;
use App\Actions\Config\ToggleReferenceEntity;
use App\Http\Controllers\Controller;
use App\Models\NotificationChannelSetting;
use App\Support\Config\ReferenceEntity;
use App\Support\Config\ReferenceEntityRegistry;
use App\Support\Notification\NotificationChannels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BR-SC-01, BR-SC-02, BR-SC-03 — layar Konfigurasi Umum.
 *
 * Jumlah pemakai ikut dikirim untuk setiap baris supaya layar bisa
 * menjelaskan sebelum tombol Hapus ditekan (BR-SC-01). Menghitungnya di sini
 * memang lebih mahal daripada menampilkan tombol lalu menolak, tapi penolakan
 * yang baru muncul setelah menekan membuat Admin mengira sistemnya rusak.
 */
class ReferenceEntityController extends Controller
{
    public function index(): Response
    {
        $this->authorizeManage();

        return Inertia::render('Config/ReferenceEntitiesPage', [
            'entities' => collect(ReferenceEntityRegistry::all())
                ->map(fn (ReferenceEntity $entity) => [
                    'key' => $entity->key,
                    'label' => $entity->label,
                    'used_by' => $entity->usedBy,
                    'editable' => $entity->editable,
                    'deactivatable' => $entity->deactivatable,
                    'records' => $this->records($entity),
                ])
                ->values(),
            // BR-NOT-06 — channel per tipe event tinggal di layar yang sama
            // dengan data acuan lain: keduanya konfigurasi yang disunting
            // tanpa deploy, dan memisahkannya hanya menambah layar yang harus
            // ditemukan Admin.
            'notificationChannels' => NotificationChannelSetting::query()
                ->orderBy('event_type')
                ->get()
                ->map(fn (NotificationChannelSetting $setting) => [
                    'event_type' => $setting->event_type,
                    'channels' => $setting->channels,
                ])
                ->all(),
            'channelOptions' => [
                ['value' => NotificationChannels::IN_APP, 'label' => 'In-app'],
                ['value' => NotificationChannels::EMAIL, 'label' => 'Email'],
            ],
        ]);
    }

    public function store(Request $request, string $entity): RedirectResponse
    {
        $this->authorizeManage();

        $reference = ReferenceEntityRegistry::get($entity);

        SaveReferenceEntity::run($reference, $this->validated($request, $reference));

        return back()->with('success', "{$reference->label} ditambahkan.");
    }

    public function update(Request $request, string $entity, int $id): RedirectResponse
    {
        $this->authorizeManage();

        $reference = ReferenceEntityRegistry::get($entity);
        $record = $this->find($reference, $id);

        SaveReferenceEntity::run($reference, $this->validated($request, $reference), $record);

        return back()->with('success', "{$reference->label} diperbarui.");
    }

    public function destroy(string $entity, int $id): RedirectResponse
    {
        $this->authorizeManage();

        $reference = ReferenceEntityRegistry::get($entity);

        DeleteReferenceEntity::run($reference, $this->find($reference, $id));

        return back()->with('success', "{$reference->label} dihapus.");
    }

    public function toggle(Request $request, string $entity, int $id): RedirectResponse
    {
        $this->authorizeManage();

        $reference = ReferenceEntityRegistry::get($entity);
        $active = $request->boolean('is_active');

        ToggleReferenceEntity::run($reference, $this->find($reference, $id), $active);

        return back()->with('success', $active ? "{$reference->label} diaktifkan." : "{$reference->label} dinonaktifkan.");
    }

    /**
     * @param  ReferenceEntity<covariant Model>  $entity
     * @return array<int, array<string, mixed>>
     */
    private function records(ReferenceEntity $entity): array
    {
        $model = $entity->model;

        return $model::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Model $record) => [
                'id' => $record->getKey(),
                'name' => $record->getAttribute('name'),
                'is_active' => $entity->deactivatable ? (bool) $record->getAttribute('is_active') : null,
                'usage_count' => CountReferenceEntityUsage::run($entity, $record),
            ])
            ->all();
    }

    /**
     * @param  ReferenceEntity<covariant Model>  $entity
     */
    private function find(ReferenceEntity $entity, int $id): Model
    {
        $model = $entity->model;

        return $model::query()->findOrFail($id);
    }

    /**
     * @param  ReferenceEntity<covariant Model>  $entity
     * @return array<string, mixed>
     */
    private function validated(Request $request, ReferenceEntity $entity): array
    {
        $table = (new $entity->model)->getTable();

        return $request->validate([
            'name' => ['required', 'string', 'max:191', 'unique:'.$table.',name'],
        ], [
            'name.required' => 'Nama harus diisi.',
            'name.unique' => 'Nama itu sudah dipakai.',
        ]);
    }

    private function authorizeManage(): void
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('users.manage'), 403);
    }
}
