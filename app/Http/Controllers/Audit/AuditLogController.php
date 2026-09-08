<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\AuditEntry;
use App\Models\User;
use App\Support\Audit\AuditScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BR-RPT-04, BR-RPT-05 — layar jejak audit.
 *
 * Satu controller untuk dua pembaca. Operator dan peneliti melihat layar yang
 * sama dengan isi berbeda, karena yang membedakan mereka adalah **cakupan**,
 * bukan tampilan — dan dua controller yang menyaring dengan aturannya
 * masing-masing adalah dua aturan yang cepat atau lambat menyimpang.
 *
 * Tidak ada aksi tulis di sini: append-only (BR-RPT-04) berarti tidak ada
 * rute hapus maupun sunting untuk dibuat, bukan rute yang menolak saat
 * dipanggil.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $entries = AuditScope::forUser($user)
            ->with(['causer:id,name,email', 'subject'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuditEntry $entry) => [
                'id' => $entry->id,
                'description' => $entry->description,
                'subject_type' => class_basename($entry->subject_type ?? ''),
                'subject_id' => $entry->subject_id,
                'causer' => $entry->causer instanceof User
                    ? ['id' => $entry->causer->id, 'name' => $entry->causer->name]
                    : null,
                'properties' => $entry->properties,
                'created_at' => $entry->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Audit/IndexPage', [
            'entries' => $entries,
            // Menentukan judul dan penjelasan cakupan di layar. Peneliti yang
            // melihat daftar pendek tanpa keterangan akan mengira ada yang
            // hilang, bukan mengira daftarnya memang sebatas miliknya.
            'seesEverything' => $user->can(AuditScope::READ_ALL_PERMISSION),
        ]);
    }
}
