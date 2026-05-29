# Panduan Kontribusi SIMPAS v2

Terima kasih sudah ikut mengembangkan SIMPAS v2. Dokumen ini menjadi panduan kerja untuk developer agar setup lokal, alur issue, branch, commit, review, dan quality gate berjalan konsisten.

Panduan ini mengikuti konvensi tim: developer mengerjakan issue bertipe `[Task]`, sedangkan Epic dan Story dipakai sebagai konteks parent.

## Daftar Isi

- [Prinsip Utama](#prinsip-utama)
- [Bahasa Dokumentasi dan Kode](#bahasa-dokumentasi-dan-kode)
- [Prasyarat Lokal](#prasyarat-lokal)
- [Setup Environment](#setup-environment)
- [Strategi Branch](#strategi-branch)
- [Alur Mengerjakan Issue](#alur-mengerjakan-issue)
- [Membuat Issue atau Task Baru](#membuat-issue-atau-task-baru)
- [Konvensi Branch](#konvensi-branch)
- [Konvensi Commit Message](#konvensi-commit-message)
- [Cara Menulis State Transition](#cara-menulis-state-transition)
- [Konvensi Kode Backend](#konvensi-kode-backend)
- [Konvensi Kode Frontend](#konvensi-kode-frontend)
- [Panduan Testing](#panduan-testing)
- [Keamanan dan Secrets](#keamanan-dan-secrets)
- [Menjalankan Tools](#menjalankan-tools)
- [Definition of Done](#definition-of-done)
- [Menulis dan Submit Pull Request](#menulis-dan-submit-pull-request)
- [Checklist Sebelum PR](#checklist-sebelum-pr)
- [Review dan Merge](#review-dan-merge)
- [Rujukan Best Practice](#rujukan-best-practice)

---

## Prinsip Utama

- Kerjakan hanya issue bertipe `[Task]`.
- Baca Epic dan Story parent sebelum mulai. Jangan kerjakan Task jika parent-nya masih blocked atau butuh keputusan desain dari **@nas11ai**.
- Assign diri sendiri ke issue sebelum mulai.
- Buat branch dari `dev`. Jangan pernah buat branch dari `main` atau `staging`.
- Jangan push langsung ke `main`, `staging`, atau `dev`.
- Semua perubahan masuk lewat Pull Request.
- Setelah PR disetujui, author PR yang melakukan merge dengan opsi **Squash and merge**.
- Dokumentasi dan komunikasi tim boleh memakai bahasa Indonesia, tetapi identifier kode baru wajib memakai bahasa Inggris.

---

## Bahasa Dokumentasi dan Kode

Gunakan bahasa Indonesia untuk dokumen, komentar PR, dan penjelasan teknis.

Gunakan bahasa Inggris untuk semua identifier baru di kode:

- Nama class, trait, interface, enum, dan exception.
- Nama method, function, variable, property, constant, dan config key.
- Nama DTO, Action, Request, Resource, Policy, Job, Event, Listener, Notification, dan Command.
- Nama component Vue, composable, Pinia store, type/interface TypeScript, dan file frontend baru.
- Nama tabel, kolom, migration, factory, dan seeder baru.
  Contoh:

```text
FormSubmission          ✅
SubmitFormSubmission    ✅
SubmissionPeriod        ✅
PengajuanFormulir       ❌
KirimPengajuanFormulir  ❌
```

Istilah domain yang sudah telanjur ada di database atau legacy code boleh dipertahankan saat memperbaiki kode lama. Untuk kode baru, pilih nama yang konsisten dengan ubiquitous language di `docs/requirement-analysis/ddd/02_ubiquitous_language.md`.

---

## Prasyarat Lokal

- Git
- Docker dan Docker Compose
  Stack development berjalan penuh via Docker Compose:

| Service   | Image                 | Keterangan                       |
| --------- | --------------------- | -------------------------------- |
| app       | PHP 8.3 / Laravel 13  | Aplikasi utama + Vite HMR        |
| postgres  | postgres:18-alpine    | Database utama                   |
| dragonfly | dragonflydb/dragonfly | Redis-compatible cache dan queue |
| minio     | minio/minio           | S3-compatible local file storage |

Jika ingin menjalankan command tanpa Docker, siapkan juga PHP 8.3, Composer, dan Node.js secara lokal.

---

## Setup Environment

Clone repository:

```bash
git clone https://github.com/nas11ai/sim-kerjasama-itk.git
cd sim-kerjasama-itk
```

Siapkan file environment:

```bash
cp .env.example .env
```

Jalankan stack:

```bash
docker compose up -d --build
```

Generate application key jika `APP_KEY` masih kosong:

```bash
docker compose exec app php artisan key:generate
```

Jalankan migration dan seeder:

```bash
docker compose exec app php artisan migrate --seed
```

Akses:

| Service       | URL                   |
| ------------- | --------------------- |
| Laravel app   | http://localhost:8000 |
| Vite HMR      | http://localhost:5173 |
| MinIO console | http://localhost:9001 |

Port default berdasarkan `.env`. Jika ada konflik, sesuaikan di `.env`.

Jika menjalankan tanpa Docker:

```bash
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run dev
php artisan serve
```

---

## Strategi Branch

Repo ini menggunakan tiga branch permanen:

```
main        ← production. Hanya menerima merge dari staging.
staging     ← pre-production. Hanya menerima merge dari dev.
dev         ← integration. Semua feature branch di-merge ke sini.
```

Alur pengerjaan:

```
feature branch  →  dev  →  staging  →  main
```

- Developer membuat feature branch dari `dev`.
- Setelah PR ke `dev` disetujui dan CI hijau, merge ke `dev`.
- Saat rilis, `dev` di-merge ke `staging` untuk pengujian di staging environment.
- Setelah staging aman, `staging` di-merge ke `main` untuk production.
  **Jangan** buat branch dari `main` atau `staging`. **Jangan** push langsung ke ketiga branch permanen ini.

---

## Alur Mengerjakan Issue

1. Buka issue `[Task]` di GitHub.
2. Baca Epic dan Story parent untuk memahami konteks.
3. Pastikan task tidak blocked dan siap dikerjakan.
4. Assign issue ke diri sendiri.
5. Sinkronkan branch `dev`:
    ```bash
    git fetch origin
    git checkout dev
    git pull --ff-only origin dev
    ```
6. Buat branch baru dari `dev`:
    ```bash
    git checkout -b task/64-contributing-guide
    ```
7. Kerjakan perubahan dengan scope sekecil mungkin.
8. Jalankan quality gate yang relevan.
9. Buat PR ke `dev` dengan template yang tersedia.
10. Request review.
11. Merge hanya setelah approved dan CI hijau.

---

## Membuat Issue atau Task Baru

Jika menemukan bug atau kebutuhan teknis saat bekerja, buat atau usulkan issue terlebih dahulu — jangan langsung buat branch.

Langkah:

1. Cek apakah issue serupa sudah ada.
2. Jika perubahan kecil dan jelas, buat issue `[Task]`.
3. Jika perubahan besar atau belum jelas desainnya, diskusikan dulu dengan **@nas11ai** agar bisa dipecah menjadi Epic, Story, dan Task.
4. Isi issue dengan konteks, acceptance criteria, layer terdampak, dan Definition of Done.
5. Tambahkan label yang sesuai.
6. Jangan mulai implementasi sampai task jelas, tidak blocked, dan sudah di-assign.
   Template minimal:

```markdown
### Deskripsi

Jelaskan masalah atau kebutuhan yang ingin diselesaikan.

### Acceptance Criteria

- [ ] Kondisi yang harus terpenuhi

### Catatan Teknis

Constraint, referensi file, atau keputusan desain jika ada.

### Definition of Done

- [ ] Implementasi selesai
- [ ] Test ditulis atau alasan skip ditulis
- [ ] Quality gate relevan dijalankan
```

---

## Konvensi Branch

Karena pekerjaan berasal dari issue `[Task]`, format utama adalah:

```
task/<nomor-issue>-<slug-singkat>
```

Contoh:

```
task/64-contributing-guide
task/43-fix-like-to-ilike
task/97-setup-model-states
```

Jika tipe perubahan lebih tepat menggunakan prefix lain:

```
feature/<nama-fitur>
fix/<nama-bug>
chore/<nama-task>
docs/<nama-dokumen>
```

Contoh:

```
docs/contributing-guide
fix/postgresql-group-by-statcontroller
chore/setup-larastan-level-5
feature/budget-line-item-input
```

Aturan:

- Huruf kecil dan tanda hubung.
- Tidak ada spasi, underscore, atau singkatan yang tidak jelas.
- Selalu dibuat dari `dev`, bukan dari `main` atau `staging`.

---

## Konvensi Commit Message

Ikuti [Conventional Commits 1.0.0](https://www.conventionalcommits.org/en/v1.0.0/):

```
<type>(optional-scope): <deskripsi-singkat>
```

Aturan:

- Type wajib valid. Lihat tabel di bawah.
- Scope opsional — jika dipakai, huruf kecil dan bahasa Inggris.
- Deskripsi singkat dalam bahasa Inggris, ringkas, tanpa titik di akhir.
- Breaking change: gunakan `!` setelah type/scope.
  | Type | Kapan Dipakai |
  |------------|------------------------------------------------------------|
  | `feat` | Fitur atau kemampuan baru |
  | `fix` | Perbaikan bug |
  | `chore` | Tooling, dependency, konfigurasi, housekeeping |
  | `docs` | Perubahan dokumentasi |
  | `test` | Menambah atau memperbaiki test |
  | `refactor` | Mengubah struktur kode tanpa mengubah perilaku |
  | `ci` | Perubahan pipeline CI |
  | `style` | Formatting tanpa perubahan logic |
  | `perf` | Perubahan untuk performa |
  | `build` | Perubahan build system atau packaging |

Contoh:

```
feat: add budget line item input
fix: resolve GROUP BY error on PostgreSQL
chore: upgrade Laravel to v13
docs: update DDD scheme bounded context
test: add Pest test for SubmitFormSubmission
ci: add dragonfly and minio service to workflow
refactor: decompose SubmissionViewController into actions
```

Dengan scope:

```
fix(statistics): resolve PostgreSQL group by error
docs(contributing): document branch and commit convention
test(submission): add state transition unit tests
```

Breaking change:

```
feat(schema)!: replace submission rules with scheme rules JSONB
```

Yang harus dihindari:

```
update file        ❌
fixing             ❌
perbaiki bug       ❌
menambah fitur     ❌
wip                ❌
```

Commit sebaiknya kecil, mudah direview, dan tidak mencampur perubahan yang tidak terkait.

---

## Cara Menulis State Transition

SIMPAS v2 menggunakan `spatie/laravel-model-states` untuk state machine `FormSubmission`.

**Jangan ubah status submission secara langsung:**

```php
// ❌ Jangan lakukan ini
$submission->update(['status' => 'approved']);
$submission->status = 'approved';
```

**Gunakan `transitionTo()` melalui state machine:**

```php
// ✅ Cara yang benar
$submission->status->transitionTo(Approved::class);
```

**Semua valid transitions terdefinisi di `SubmissionStatus::config()`:**

```
Draft          → Submitted
Submitted      → UnderReview
UnderReview    → NeedsRevision | Approved | Rejected
NeedsRevision  → Resubmitted
Resubmitted    → UnderReview
Approved       → Withdrawn
```

**Transition yang tidak valid akan throw `TransitionNotAllowed`** — jangan catch exception ini tanpa alasan yang jelas.

**Saat menambah state baru:**

1. Buat class baru di `app/States/Submission/` yang extend `SubmissionStatus`
2. Daftarkan transition yang valid di `SubmissionStatus::config()`
3. Tulis Pest unit test untuk membuktikan transition valid dan invalid
4. Update ubiquitous language di DDD jika state baru punya makna domain
   **Jangan tambahkan percabangan `if ($submission->status === 'approved')` secara raw.** Gunakan `$submission->status->equals(Approved::class)` atau `$submission->status instanceof Approved`.

---

## Konvensi Kode Backend

### Controller

Controller harus tipis. Tugasnya: terima request, validasi via FormRequest, panggil Action, kembalikan response. Hindari business logic di controller.

### FormRequest dan DTO

Gunakan FormRequest untuk validasi input HTTP.

Gunakan DTO (`spatie/laravel-data`) ketika data diteruskan ke Action, dipakai lintas layer, atau punya struktur nested.

Pola untuk endpoint mutasi:

```
FormRequest → DTO → Action → response
```

### Action

Gunakan Action untuk business use case yang jelas: submit proposal, assign reviewer, close period, update status.

```php
$submission = SubmitFormSubmission::run($submission, $user);
```

Action dipakai ketika logic mengubah state, butuh transaction, dipanggil dari lebih dari satu tempat, atau perlu unit test terpisah.

### Database dan PostgreSQL

SIMPAS v2 memakai PostgreSQL. Hindari asumsi MySQL:

```php
// ❌ MySQL only
DB::raw('SUM(status = "pending") as pending')
DB::raw('YEAR(created_at) as year')
->where('column', 'LIKE', "%$q%")

// ✅ PostgreSQL compatible
DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending")
DB::raw("EXTRACT(YEAR FROM created_at) as year")
->whereRaw('column ILIKE ?', ["%$q%"])
// atau: ->whereLike('column', "%$q%")  (Laravel 11+)
```

Pastikan kolom non-aggregate di `SELECT` ikut masuk `GROUP BY`. Test query kompleks di PostgreSQL, bukan SQLite.

### Submission-Level Access

Semua query yang return data submission **wajib** scope via `isAccessibleBy()`:

```php
// ❌ Tidak cukup hanya cek FormAccessControl
$submission = FormSubmission::find($id);

// ✅ Selalu cek submission-level access
$submission = FormSubmission::find($id);
abort_unless($submission->isAccessibleBy(auth()->user()), 403);
```

Pengecualian: user dengan permission `submissions.view-all` (Operator/Admin) bypass scope ini secara otomatis.

### Optimistic Locking

Endpoint yang menerima update data submission harus menyertakan `updated_at` check untuk mencegah concurrent edit:

```php
$submission = FormSubmission::lockForUpdate()->find($id);

if ($submission->updated_at->ne($request->last_updated_at)) {
    return response()->json([
        'message' => 'Data sudah diubah oleh pengguna lain. Muat ulang halaman.'
    ], 409);
}
```

---

## Konvensi Kode Frontend

- Gunakan Vue 3 dengan `<script setup>`.
- Gunakan TypeScript untuk semua tipe data.
- Simpan state global di Pinia store (`useXxxStore`).
- Hindari `console.log` tertinggal.
- Jalankan ESLint dan Prettier sebelum PR.
- Hindari `any` — jika terpaksa, tulis alasan di komentar.

### Struktur

| Folder                       | Isi                                  |
| ---------------------------- | ------------------------------------ |
| `resources/js/Pages`         | Page-level component (Inertia)       |
| `resources/js/Components`    | Reusable component                   |
| `resources/js/Components/ui` | Primitive UI component (shadcn/reka) |
| `resources/js/stores`        | Pinia stores                         |
| `resources/js/composables`   | Composable (logic lintas component)  |
| `resources/js/types`         | TypeScript interfaces/types          |

### Props, Emits, dan Tipe Data

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

Jangan lakukan transformasi data besar di template — siapkan di computed atau composable.

### Authorization di Frontend

Frontend boleh menyembunyikan UI berdasarkan permission, tetapi **backend tetap wajib memvalidasi**. Jangan andalkan frontend sebagai satu-satunya penjaga otorisasi.

---

## Panduan Testing

### Wajib Menulis Test Jika Menyentuh

- Fitur baru atau bug fix.
- Query database, migration, atau compatibility PostgreSQL.
- Business logic di Action, model method, state machine, atau policy.
- State transition (valid dan invalid).
- Temporal Field Binding (`isRequiredFor()`).
- Frontend store, composable, atau component dengan logic non-trivial.

### Boleh Skip dengan Alasan

- Perubahan dokumentasi saja.
- Perubahan formatting tanpa logic.
- Perubahan UI minor tanpa perubahan behavior.
- Chore tooling/config sederhana.
  Jika skip, tulis alasan di body PR. Jangan centang checklist test jika test tidak ditulis.

### Larangan Anti-Pattern

**Jangan** tambahkan percabangan khusus testing di production code:

```php
// ❌ Jangan lakukan ini
if (app()->environment('testing')) {
    // bypass behavior
}
```

Jika test sulit ditulis, perbaiki desain kode — gunakan factory, mock dependency yang tepat, atau pindahkan logic ke Action/service.

### Command

```bash
# Backend
./vendor/bin/pest
./vendor/bin/pest --filter=SubmissionTest

# Frontend
npm run test
npm run test:coverage
npm run test:e2e
```

Dengan Docker:

```bash
docker compose exec app ./vendor/bin/pest
docker compose exec app npm run test
```

---

## Keamanan dan Secrets

- Jangan commit `.env`, credential, token, private key, atau dump database.
- Gunakan `.env.example` hanya sebagai referensi nama variable.
- Jika secret tidak sengaja ter-commit, beri tahu maintainer segera — jangan hanya hapus di commit berikutnya; secret harus dirotasi.
- Jangan paste credential di issue, PR, atau komentar review.
- Pastikan screenshot tidak menampilkan token, password, atau data sensitif.

---

## Menjalankan Tools

### Backend

```bash
# Formatting check
composer lint

# Auto-fix formatting
composer lint:fix

# Static analysis
./vendor/bin/phpstan analyse --memory-limit=512M

# Test
./vendor/bin/pest

# Dengan Docker
docker compose exec app composer lint
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=512M
docker compose exec app ./vendor/bin/pest
```

### Frontend

```bash
# ESLint
npm run lint

# Prettier check
npm run format:check

# Auto-format
npm run format

# Unit test
npm run test

# Coverage
npm run test:coverage

# E2E test
npm run test:e2e

# Build
npm run build

# Dengan Docker
docker compose exec app npm run lint
docker compose exec app npm run test
```

---

## Definition of Done

Task dianggap selesai jika semua kriteria yang relevan terpenuhi:

- Scope sesuai issue `[Task]` — tidak melebar.
- Acceptance criteria di issue terpenuhi.
- Implementasi mengikuti konvensi di dokumen ini.
- Business logic ada di layer yang sesuai (Action, bukan Controller).
- Test ditulis, atau alasan skip ditulis jelas di PR.
- Quality gate relevan sudah dijalankan dan hijau.
- Tidak ada `console.log`, `dd()`, atau `dump()` tertinggal.
- Migration, seeder, atau query sudah dicek di PostgreSQL jika menyentuh database.
- Evidence hasil test disiapkan untuk PR.
- PR menutup issue dengan `Closes #<nomor>`.

---

## Menulis dan Submit Pull Request

**Target PR: selalu ke `dev`**, kecuali saat rilis dari `dev` ke `staging`, atau dari `staging` ke `main`.

Sebelum membuat PR:

- Pastikan CI sudah hijau secara lokal (lint, test).
- Tidak ada file temporary, artifact lokal, credential, atau debug statement tertinggal.
  Saat membuat PR:

- Gunakan `.github/PULL_REQUEST_TEMPLATE.md`.
- Isi `Closes #<nomor>` di bagian Issue.
- Jelaskan apa yang berubah dan kenapa — bukan bagaimana (itu sudah ada di kode).
- Tulis cara test yang konkret.
- Lampirkan evidence: screenshot terminal, output test, atau screenshot UI bila relevan.
- Jika test tidak ditulis, tulis alasan eksplisit.
  Contoh PR title:

```
[Docs] Add contributing guide
[Bug] Fix PostgreSQL group by statistics query
[Chore] Setup Laravel Telescope
[Feature] Add budget line item input
```

---

## Checklist Sebelum PR

- [ ] Issue `[Task]` sudah di-assign ke author
- [ ] Epic dan Story parent sudah dibaca
- [ ] Branch dibuat dari `dev` yang sudah di-sync
- [ ] Scope perubahan sesuai issue — tidak melebar
- [ ] Pint dijalankan jika menyentuh PHP
- [ ] Larastan dijalankan jika menyentuh backend
- [ ] Pest dijalankan jika menyentuh backend atau query
- [ ] ESLint dan Prettier dijalankan jika menyentuh frontend
- [ ] Vitest dijalankan jika menyentuh frontend yang punya test
- [ ] Migration dicek di PostgreSQL jika menyentuh database
- [ ] State transition menggunakan `transitionTo()`, bukan assignment langsung
- [ ] `isAccessibleBy()` dipanggil di semua query yang return data submission
- [ ] PR memakai template repo dan target branch adalah `dev`
- [ ] Evidence test disiapkan

---

## Review dan Merge

- Minimal satu reviewer harus approve sebelum merge.
- Semua komentar review harus diselesaikan sebelum merge.
- CI harus hijau.
- Author PR yang melakukan merge.
- Gunakan **Squash and merge**.
- Jangan merge langsung ke `main`, `staging`, atau `dev` tanpa PR.
  **Alur rilis:**

```
dev → staging     PR dibuat oleh tech lead, setelah semua task sprint selesai
staging → main    PR dibuat oleh tech lead, setelah staging diverifikasi
```

---

## Rujukan Best Practice

- [GitHub: Setting guidelines for contributors](https://docs.github.com/en/communities/setting-up-your-project-for-healthy-contributions/setting-guidelines-for-repository-contributors)
- [Conventional Commits 1.0.0](https://www.conventionalcommits.org/en/v1.0.0/)
- [spatie/laravel-model-states](https://spatie.be/docs/laravel-model-states)
- [DDD Ubiquitous Language](docs/requirement-analysis/ddd/02_ubiquitous_language.md)
- [DDD Domain Map](docs/requirement-analysis/ddd/01_domain_map.md)
