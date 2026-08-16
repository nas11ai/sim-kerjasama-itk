# Coding Standards — SIMPAS v2

> Rujukan tunggal aturan kode. Dokumen ini berlaku untuk **manusia maupun agent**
> (Claude Code, `dsc`/DeepSeek). Agent membaca ringkasannya lewat [`AGENTS.md`](../AGENTS.md).
>
> Proses kerja (branch, commit, PR, review, rilis) ada di [`CONTRIBUTING.md`](../CONTRIBUTING.md).
> Aturan domain ada di [`docs/requirement-analysis/ddd/`](../docs/requirement-analysis/ddd/).

---

## Prinsip

**Aturan yang bisa ditegakkan mesin, ditegakkan mesin.** Kalau sebuah aturan hanya
hidup sebagai prosa, ia akan dilanggar. Yang tidak bisa ditegakkan mesin ditulis di
sini dengan alasannya, supaya reviewer punya pegangan.

**Penamaan kode** (class, field, event, enum, method) → **English**.
**Komentar & dokumentasi** → **Bahasa Indonesia**.

---

## Quality Gate

Semua perintah di bawah harus hijau sebelum PR dibuka. Ini gate yang sama persis
dengan yang dijalankan CI — tidak ada yang eksklusif CI.

```bash
# Backend
./vendor/bin/pint --test                                    # format
./vendor/bin/phpstan analyse                                # tipe — base tier
./vendor/bin/phpstan analyse -c phpstan-strict.neon         # tipe — strict tier
./vendor/bin/pest                                           # test

# Frontend
npm run lint          # ESLint
npm run format:check  # Prettier
npm run typecheck     # vue-tsc --noEmit
npm run test          # Vitest
npm run build         # Vite build
```

Shortcut: `composer run quality` (backend) dan `npm run quality` (frontend).

### Menjalankan test secara lokal

`phpunit.xml` menetapkan `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_PASSWORD=root`.
Nilai ini **kalah** oleh environment variable yang sudah ada (PHPUnit `<env>`
default-nya `force="false"`), dan itulah sebabnya CI bisa memakai password lain.
Kalau PostgreSQL lokalmu tidak memakai kredensial tersebut, timpa saat menjalankan:

```bash
DB_PORT=55432 DB_PASSWORD=postgres ./vendor/bin/pest
```

---

## Dua Tier PHPStan

| Tier       | Config                | Level | Cakupan                                   |
| ---------- | --------------------- | ----- | ----------------------------------------- |
| **Base**   | `phpstan.neon`        | 5     | seluruh `app/`                            |
| **Strict** | `phpstan-strict.neon` | 9     | seluruh `app/` **kecuali** direktori lama |

`phpstan-strict.neon` mendaftar direktori legacy yang **dikecualikan**, bukan
direktori yang ikut ketat. Artinya setiap folder baru — `app/Actions`,
`app/Policies`, `app/Events`, `app/Listeners`, `app/Notifications`, `app/Data` —
**otomatis level 9 sejak lahir**, tanpa perlu ada yang ingat mendaftarkannya.

`excludePaths` adalah utang teknis yang terlihat. Daftarnya **hanya boleh
menyusut**. Menambah entri baru berarti menurunkan standar dan wajib dibahas
eksplisit di PR.

Ukuran utang per 2026-08-16 pada level 9: `app/Http` 487, `app/Models` 173,
`app/Services` 32, `app/Exports` 2.

### Dilarang menyiasati PHPStan

Jangan memakai `@phpstan-ignore`, entri baseline, `assert()`, inline `@var`, cast,
atau melebarkan tipe parameter/return hanya agar error hilang. Error level 9
hampir selalu menunjuk bug asli — perbaiki penyebabnya.

Contoh nyata dari repo ini: `SubmitFormSubmission` memanggil
`$submission->status->transitionTo(...)` padahal `status` bisa `null`. Jawabannya
bukan menambah `assert()`, tapi menambahkan guard eksplisit.

---

## Backend

### Layering

```
FormRequest → DTO (spatie/laravel-data) → Action → Model/Event
                                            ↓
                                        Controller mengembalikan Inertia response
```

**Controller tipis.** Tugasnya hanya: terima request, validasi via FormRequest,
panggil Action, kembalikan response. Tidak ada business logic.

**Action** (`lorisleiva/laravel-actions`) dipakai ketika logic mengubah state,
butuh transaction, dipanggil dari lebih dari satu tempat, atau perlu unit test
terpisah.

```php
$submission = SubmitFormSubmission::run($submission, $user);
```

**DTO** (`spatie/laravel-data`) dipakai ketika data diteruskan ke Action, dipakai
lintas layer, atau punya struktur nested.

### Exception

Jangan lempar `\Exception` generik. Pakai yang semantiknya benar supaya HTTP
status-nya ikut benar:

| Situasi                     | Exception                                          |
| --------------------------- | -------------------------------------------------- |
| User tidak berhak           | `Illuminate\Auth\Access\AuthorizationException` → 403 |
| Input tidak valid           | `Illuminate\Validation\ValidationException` → 422   |
| Data tidak ditemukan        | `ModelNotFoundException` → 404                      |
| Invariant domain dilanggar  | Exception domain khusus (lihat catatan di bawah)    |

Hierarki exception domain belum dirancang — akan ditetapkan di milestone M0.
Sampai saat itu, `RuntimeException` dengan pesan spesifik dapat diterima untuk
kondisi "seharusnya tidak mungkin terjadi".

### State machine

Status `FormSubmission` **hanya** boleh berubah lewat `transitionTo()`. Tidak ada
assignment langsung, tidak ada `update(['status' => ...])`.

```php
// ❌
$submission->update(['status' => Approved::class]);

// ✅
$submission->status->transitionTo(Approved::class);
```

Transisi yang sah didefinisikan di `App\States\Submission\SubmissionStatus::config()`.

### Submission-level access

Setiap query yang mengembalikan data submission **wajib** di-scope. Mengecek
`FormAccessControl` saja tidak cukup — itu access control level Form, bukan level
Submission (BR-SM-07).

```php
$submission = FormSubmission::findOrFail($id);
abort_unless($submission->isAccessibleBy($request->user()), 403);
```

User dengan permission `submissions.view-all` (Operator/Admin) bypass otomatis.

### Optimistic locking

Endpoint yang mengubah data submission wajib memeriksa `updated_at` untuk
mencegah concurrent edit (BR-FE-14, BR-SM-12):

```php
$submission = FormSubmission::lockForUpdate()->findOrFail($id);

if ($submission->updated_at->ne($request->date('last_updated_at'))) {
    abort(409, 'Data sudah diubah pengguna lain. Muat ulang halaman.');
}
```

### PostgreSQL

Database-nya PostgreSQL. Jangan menulis SQL berasumsi MySQL:

```php
// ❌ MySQL only
DB::raw('SUM(status = "pending") as pending')
DB::raw('YEAR(created_at) as year')
->where('column', 'LIKE', "%$q%")

// ✅ PostgreSQL
DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending")
DB::raw("EXTRACT(YEAR FROM created_at) as year")
->whereLike('column', "%$q%")
```

Kolom non-agregat di `SELECT` wajib ikut `GROUP BY`. Test query kompleks di
PostgreSQL, bukan SQLite.

---

## Frontend

Vue 3 `<script setup>` + TypeScript + Inertia. Struktur folder:

| Folder                       | Isi                                  |
| ---------------------------- | ------------------------------------ |
| `resources/js/Pages`         | Page-level component (Inertia)       |
| `resources/js/Components`    | Reusable component                   |
| `resources/js/Components/ui` | Primitive UI (shadcn/reka) — vendored |
| `resources/js/stores`        | Pinia store                          |
| `resources/js/composables`   | Composable                           |
| `resources/js/types`         | TypeScript interface/type            |

### Aturan yang ditegakkan ESLint

| Aturan                                | Nilai   | Catatan                                                      |
| ------------------------------------- | ------- | ------------------------------------------------------------ |
| `@typescript-eslint/no-explicit-any`  | `error` | Berlaku di semua folder, tanpa pengecualian                  |
| `@typescript-eslint/no-unused-vars`   | `error` | Awalan `_` sebagai escape hatch yang disengaja               |
| `no-console`                          | `error` | `console.error` & `console.warn` diizinkan; `log` tidak      |
| `eqeqeq`                              | `error` | mode `smart`                                                 |
| `vue/component-api-style`             | `error` | wajib `<script setup>`                                       |

Rule bawaan `no-unused-vars` **dimatikan** dan diganti versi TypeScript-aware.
Versi bawaan tidak memahami posisi tipe TS maupun makro `<script setup>`, dan
menghasilkan false positive.

### Props & emits

Selalu bertipe. Jangan melakukan transformasi data besar di template — siapkan di
`computed` atau composable.

```vue
<script setup lang="ts">
interface UserOption {
    id: number
    name: string
}

defineProps<{
    users: UserOption[]
    selectedUserId?: number
}>()

const emit = defineEmits<{
    select: [userId: number]
}>()
</script>
```

### Authorization

Frontend boleh **menyembunyikan** UI berdasarkan permission, tapi backend tetap
wajib memvalidasi. Frontend bukan penjaga otorisasi.

### Catatan `noUnusedLocals`

Flag TypeScript `noUnusedLocals` sengaja **tidak** diaktifkan: `vue-tsc` tidak
mengenali template ref berbentuk string (`ref="passwordInput"`) sebagai pemakaian,
sehingga menghasilkan false positive. ESLint sudah menangani unused local dengan
benar. `noUnusedParameters` tetap aktif karena justru menangkap kasus yang
dilewatkan ESLint (parameter di tengah signature).

---

## Testing

### Wajib menulis test jika menyentuh

- Fitur baru atau bug fix
- Query database, migration, atau kompatibilitas PostgreSQL
- Business logic di Action, model method, state machine, atau Policy
- State transition — jalur sah **dan** jalur terlarang
- Temporal Field Binding (`isRequiredFor()`)
- Store, composable, atau component frontend dengan logic non-trivial

### Boleh skip dengan alasan tertulis di PR

Perubahan dokumentasi, formatting tanpa logic, UI minor tanpa perubahan behavior,
chore tooling sederhana.

### Anti-pattern

Jangan menambah percabangan khusus testing di production code:

```php
// ❌ Dilarang
if (app()->environment('testing')) {
    // bypass
}
```

Kalau test sulit ditulis, itu sinyal desainnya perlu diperbaiki — pakai factory,
mock dependency yang tepat, atau pindahkan logic ke Action.

---

## Environment

Satu versi PHP untuk semua environment: **8.4**.

| Environment | Sumber                     |
| ----------- | -------------------------- |
| CI          | `.github/workflows/ci.yml` |
| Docker      | `Dockerfile`               |
| `composer.json` | `"php": "^8.4"`        |

**PHP 8.5 belum bisa dipakai**: `phpoffice/phpspreadsheet` 1.30.5 (transitif dari
`maatwebsite/excel`) membatasi `<8.5.0`, sehingga `composer install` gagal.

Kalau PHP host-mu bukan 8.4, jalankan tooling lewat container:

```bash
docker run --rm --network host -v "$PWD":/app -w /app \
  -u "$(id -u):$(id -g)" php:8.4-cli ./vendor/bin/pest
```

---

## Utang yang Diketahui

Daftar ini sengaja eksplisit supaya tidak terlupakan. Jangan menambah entri tanpa
mendiskusikannya.

| Utang                                                                         | Rencana |
| ----------------------------------------------------------------------------- | ------- |
| `app/Http` (487), `app/Models` (173), `app/Services` (32) belum lolos level 9  | Dicicil saat file disentuh (strangler) |
| 21 advisory keamanan di `dompdf`, `guzzlehttp/guzzle`, `league/commonmark`, `phpoffice/phpspreadsheet` — 8 di antaranya *high* | Perlu upgrade terpisah; `composer audit` sudah jalan di CI sebagai step non-blocking |
| 9 pemakaian `confirm()` native — rule `no-alert` belum bisa diaktifkan          | Ganti dengan komponen dialog saat halaman terkait dikerjakan |
| `app/ReviewSummaryStatus.php` berada di root `app/`, seharusnya `app/Enums/`   | M0 |
| `Faculty` & `StudyProgram` sudah digantikan `Organization` tree tapi masih ada | Dihapus di M3 |
| Enum `SubmissionStatus` frontend (5 nilai) tidak cocok dengan state backend (8 nilai) | Diselaraskan saat kontrak Inertia props disusun |
| `declare(strict_types=1)` belum dipakai                                        | Menyentuh 546 file dan mengubah perilaku runtime — butuh PR terpisah yang disengaja |
