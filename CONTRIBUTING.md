# Panduan Kontribusi SIMPAS v2

Terima kasih sudah ikut mengembangkan SIMPAS v2. Dokumen ini menjadi panduan kerja untuk developer agar setup lokal, alur issue, branch, commit, review, dan quality gate berjalan konsisten.

Panduan ini mengikuti scope Task #64 dan aturan kerja tim: developer mengerjakan issue bertipe `[Task]`, sedangkan Epic dan Story dipakai sebagai konteks parent.

## Daftar Isi

- [Prinsip Utama](#prinsip-utama)
- [Bahasa Dokumentasi dan Kode](#bahasa-dokumentasi-dan-kode)
- [Prasyarat Lokal](#prasyarat-lokal)
- [Setup Environment](#setup-environment)
- [Alur Mengerjakan Issue](#alur-mengerjakan-issue)
- [Membuat Issue atau Task Baru](#membuat-issue-atau-task-baru)
- [Konvensi Branch](#konvensi-branch)
- [Konvensi Commit Message](#konvensi-commit-message)
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

## Prinsip Utama

- Kerjakan hanya issue bertipe `[Task]`.
- Cek Epic dan Story parent sebelum mulai. Jangan mengerjakan Task jika parent-nya masih blocked, belum siap, atau masih butuh keputusan desain dari **@nas11ai**.
- Assign diri sendiri ke issue sebelum mulai.
- Buat branch dari branch dasar yang disepakati. Saat dokumen ini ditulis, repo memakai `main` sebagai branch utama dan tidak ada branch `develop`.
- Jangan push langsung ke `main`.
- Jangan push branch tanpa persetujuan eksplisit dari pemilik task.
- Semua perubahan masuk lewat Pull Request.
- Setelah PR disetujui, author PR yang melakukan merge dengan opsi **Squash and merge**.
- Dokumentasi dan komunikasi tim boleh memakai bahasa Indonesia, tetapi istilah kode dan identifier baru wajib memakai bahasa Inggris.

## Bahasa Dokumentasi dan Kode

Gunakan bahasa Indonesia untuk dokumen, komentar PR, dan penjelasan teknis agar mudah dipahami semua anggota tim.

Untuk kode, gunakan bahasa Inggris pada identifier baru:

- Nama class, trait, interface, enum, dan exception.
- Nama method, function, variable, property, constant, dan config key.
- Nama DTO, Action, Request, Resource, Policy, Job, Event, Listener, Notification, dan Command.
- Nama component Vue, composable, Pinia store, type/interface TypeScript, dan file frontend baru.
- Nama tabel, kolom, migration, factory, dan seeder baru, kecuali sudah ada keputusan desain lain.

Contoh:

```text
FormSubmission          # benar
SubmitFormSubmission    # benar
SubmissionPeriod        # benar
PengajuanFormulir       # hindari
KirimPengajuanFormulir  # hindari
```

Istilah domain yang sudah telanjur ada di database atau legacy code boleh dipertahankan saat memperbaiki kode lama. Untuk kode baru, pilih nama Inggris yang konsisten dengan ubiquitous language di dokumentasi DDD.

## Prasyarat Lokal

Minimal siapkan:

- Git
- Docker dan Docker Compose
- PHP 8.3 jika menjalankan command tanpa Docker
- Composer jika menjalankan command tanpa Docker
- Node.js dan npm jika menjalankan command frontend tanpa Docker

Stack development utama menggunakan Docker Compose:

- Laravel 13 / PHP 8.3
- PostgreSQL 18
- Dragonfly sebagai Redis-compatible cache dan queue
- MinIO sebagai S3-compatible local file storage
- Vite untuk frontend development

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

Untuk PowerShell:

```powershell
Copy-Item .env.example .env
```

Jalankan stack:

```bash
docker compose up -d --build
```

Generate application key jika `.env` dibuat manual dan `APP_KEY` masih kosong:

```bash
docker compose exec app php artisan key:generate
```

Jalankan migration dan seeder:

```bash
docker compose exec app php artisan migrate --seed
```

Akses aplikasi:

- Laravel app: `http://localhost:8000`
- Vite HMR: `http://localhost:5173`
- MinIO console: `http://localhost:9001`

Port di atas adalah default saat dokumen ini ditulis. Jika ada perubahan atau port sudah dipakai service lain, cek `docker-compose.yml` dan `.env`.

Jika setup tanpa Docker, gunakan command berikut setelah dependency terpasang:

```bash
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run dev
php artisan serve
```

## Alur Mengerjakan Issue

1. Buka issue `[Task]` di GitHub.
2. Baca Epic dan Story parent untuk memahami konteks.
3. Pastikan task boleh dikerjakan dan tidak sedang blocked.
4. Assign issue ke diri sendiri.
5. Sinkronkan branch dasar.
6. Buat branch baru.
7. Kerjakan perubahan dengan scope sekecil mungkin.
8. Jalankan quality gate yang relevan.
9. Buat PR dengan template yang tersedia.
10. Request review.
11. Merge hanya setelah review approved dan check hijau.

Contoh sinkronisasi branch dasar:

```bash
git fetch origin
git checkout main
git pull --ff-only origin main
```

Contoh membuat branch:

```bash
git checkout -b task/64-contributing-guide
```

## Membuat Issue atau Task Baru

Jika menemukan bug, kebutuhan teknis, atau ide fitur saat bekerja, jangan langsung membuat branch. Buat atau usulkan issue terlebih dahulu agar scope dan prioritasnya jelas.

Langkah yang disarankan:

1. Cek apakah issue serupa sudah ada.
2. Jika perubahan kecil dan jelas, buat issue bertipe `[Task]`.
3. Jika perubahan besar, lintas bounded context, atau belum jelas desainnya, diskusikan dulu dengan Kak Nasai agar bisa dipecah menjadi Epic, Story, dan Task.
4. Isi issue dengan konteks, alasan, acceptance criteria, layer terdampak, dependency, dan Definition of Done.
5. Tambahkan label yang sesuai, misalnya `type: bug`, `type: chore`, `type: docs`, `layer: backend`, `layer: frontend`, atau `layer: infrastructure`.
6. Jangan mulai implementasi sampai task jelas, tidak blocked, dan sudah di-assign.

Contoh judul issue:

```text
[Task] Add deleted_at to form_fields
[Task] Fix PostgreSQL GROUP BY in StatController
[Task] Document local development workflow
```

Template minimal isi issue:

```markdown
### Deskripsi

Jelaskan masalah atau kebutuhan yang ingin diselesaikan.

### Acceptance Criteria

- [ ] Kondisi yang harus terpenuhi
- [ ] Perilaku yang harus bisa diverifikasi

### Catatan Teknis

Tambahkan constraint, referensi file, atau keputusan desain jika ada.

### Definition of Done

- [ ] Implementasi selesai
- [ ] Test atau alasan skip test ditulis
- [ ] Quality gate relevan dijalankan
```

## Konvensi Branch

Karena pekerjaan wajib berasal dari issue `[Task]`, format utama yang disarankan adalah:

```text
task/<nomor-issue>-<slug-singkat>
```

Contoh:

```text
task/64-contributing-guide
task/43-fix-like-to-ilike
task/97-setup-model-states
```

Jika maintainer meminta format berdasarkan tipe perubahan, gunakan prefix berikut:

```text
feature/<nama-fitur>
fix/<nama-bug>
chore/<nama-task>
docs/<nama-dokumen>
```

Contoh:

```text
docs/contributing-guide
fix/postgresql-group-by-statcontroller
chore/setup-larastan-level-5
feature/budget-line-item-input
```

Gunakan huruf kecil dan tanda hubung. Hindari spasi, underscore, nama terlalu panjang, atau singkatan yang tidak jelas.

## Konvensi Commit Message

Commit Git wajib mengikuti Conventional Commits 1.0.0:

```text
<type>(optional-scope): <deskripsi-singkat>
```

Aturan wajib:

- Commit message harus diawali type yang valid, misalnya `feat`, `fix`, `chore`, `docs`, atau `test`.
- Scope bersifat opsional, tetapi jika dipakai tulis dengan huruf kecil dan bahasa Inggris.
- Deskripsi singkat sebaiknya memakai bahasa Inggris, ringkas, dan menjelaskan perubahan.
- Jangan akhiri subject commit dengan titik.
- Untuk breaking change, gunakan tanda `!` setelah type/scope atau footer `BREAKING CHANGE:`.

Tipe yang digunakan di SIMPAS v2:

| Type | Kapan Dipakai |
|------|---------------|
| `feat` | Menambah fitur atau kemampuan baru |
| `fix` | Memperbaiki bug |
| `chore` | Perubahan tooling, dependency, konfigurasi, atau housekeeping |
| `docs` | Perubahan dokumentasi |
| `test` | Menambah atau memperbaiki test |
| `refactor` | Mengubah struktur kode tanpa mengubah perilaku |
| `ci` | Perubahan pipeline CI |
| `style` | Formatting tanpa perubahan logic |
| `perf` | Perubahan untuk performa |
| `build` | Perubahan build system atau packaging |

Contoh:

```text
feat: add budget line item input
fix: resolve GROUP BY error on PostgreSQL
chore: upgrade Laravel to v13
docs: update DDD scheme bounded context
test: add Pest test for SubmitFormSubmission
ci: add Laravel quality checks workflow
```

Gunakan scope jika membantu:

```text
fix(statistics): resolve PostgreSQL group by error
docs(contributing): document branch convention
test(submission): add submit form submission action test
```

Untuk breaking change, gunakan `!` atau footer `BREAKING CHANGE:`:

```text
feat(schema)!: replace submission rules with scheme rules
```

Contoh yang harus dihindari:

```text
update file
fixing
perbaiki bug
menambah fitur pengajuan
```

Commit sebaiknya kecil, mudah direview, dan tidak mencampur perubahan yang tidak terkait.

Rujukan resmi: https://www.conventionalcommits.org/en/v1.0.0/

## Konvensi Kode Backend

### Controller

Controller harus tipis. Controller bertugas menerima request, memanggil validation/FormRequest, memanggil Action atau query yang relevan, lalu mengembalikan response.

Hindari business logic panjang di controller, terutama logic yang:

- Mengubah state domain.
- Membutuhkan transaction.
- Dipakai ulang oleh controller, command, job, atau listener.
- Punya branching dan validasi domain yang signifikan.
- Perlu diuji unit secara terpisah.

### FormRequest dan DTO

Gunakan FormRequest untuk validasi input HTTP.

Gunakan DTO ketika data:

- Diteruskan dari controller ke Action/service.
- Dipakai lintas layer.
- Memiliki struktur nested atau transformasi tipe.
- Perlu kontrak yang eksplisit dan mudah dites.
- Dipakai ulang oleh lebih dari satu entry point.

Jika `spatie/laravel-data` sudah tersedia pada task terkait, DTO baru sebaiknya memakai Laravel Data. Jika belum tersedia, tetap pisahkan validasi di FormRequest dan bentuk payload yang jelas sebelum masuk ke Action.

Untuk endpoint mutasi baru, pola yang disarankan:

```text
FormRequest -> DTO/data payload -> Action -> response
```

Untuk endpoint read-only sederhana, controller boleh langsung memakai query yang ringkas selama tidak ada business logic domain yang kompleks.

### Action

Gunakan Action untuk business use case yang jelas, misalnya submit form, assign reviewer, close period, atau update status submission.

Action dipakai ketika logic:

- Mengubah state model.
- Mengandung authorization/domain rule.
- Perlu transaction.
- Akan dipanggil dari lebih dari satu tempat.
- Perlu unit test terpisah.

Contoh pola:

```php
$submission = SubmitFormSubmission::run($submission, $user);
```

### Database dan PostgreSQL

SIMPAS v2 memakai PostgreSQL. Hindari asumsi MySQL.

- Gunakan `ILIKE` untuk pencarian case-insensitive di PostgreSQL.
- Hindari `YEAR(created_at)`; gunakan `EXTRACT(YEAR FROM created_at)`.
- Hindari `SUM(condition)`; gunakan `SUM(CASE WHEN ... THEN 1 ELSE 0 END)`.
- Pastikan kolom non-aggregate di `SELECT` ikut masuk `GROUP BY`.
- Test query kompleks di PostgreSQL, bukan hanya SQLite.

## Konvensi Kode Frontend

- Gunakan Vue 3 dengan `<script setup>`.
- Gunakan TypeScript untuk tipe data yang lewat antar component.
- Simpan reusable logic sebagai composable atau store bila state dipakai lintas component.
- Gunakan Pinia untuk state global.
- Hindari `console.log` tertinggal.
- Jalankan ESLint dan Prettier sebelum PR.
- Komponen UI sebaiknya kecil, fokus, dan tidak memuat business rule yang seharusnya ada di backend.

### Struktur Component

- Simpan page-level component di `resources/js/Pages`.
- Simpan reusable component di `resources/js/Components`.
- Simpan primitive UI component di `resources/js/Components/ui`.
- Simpan logic lintas component sebagai composable di lokasi yang disepakati tim.
- Simpan state global di `resources/js/stores`.
- Gunakan nama file component dengan PascalCase, misalnya `UserTableList.vue` atau `BudgetPlanInput.vue`.

### Props, Emits, dan Tipe Data

- Definisikan props dan emits secara eksplisit.
- Gunakan interface/type TypeScript untuk payload yang kompleks.
- Hindari `any`; jika terpaksa, tulis alasan di komentar singkat atau rapikan pada task berikutnya.
- Jangan melakukan transformasi data besar di template. Siapkan data di computed atau composable.

Contoh:

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

### Inertia Props dan Page

- Page Inertia harus menerima props yang jelas dan typed jika memungkinkan.
- Nama props memakai bahasa Inggris dan konsisten dengan backend resource/DTO.
- Jangan mengandalkan shape data implisit dari controller. Jika data sering dipakai ulang, buat type di `resources/js/types`.
- Hindari melakukan authorization rule di frontend sebagai sumber kebenaran. Frontend boleh menyembunyikan UI, tetapi backend tetap wajib memvalidasi permission.

### Penamaan File dan Folder

- Gunakan PascalCase untuk Vue component.
- Gunakan camelCase untuk composable, function, variable, dan file utilitas.
- Gunakan `useXxxStore` untuk Pinia store, misalnya `useNotificationStore`.
- Gunakan nama domain dalam bahasa Inggris, misalnya `Submission`, `Review`, `Budget`, `Scheme`, atau `ResearchOutput`.

## Panduan Testing

Test bukan formalitas PR; test dipakai untuk menjaga perilaku domain dan mencegah regresi.

### Wajib atau Sangat Disarankan Menulis Test

Tulis atau update test jika perubahan menyentuh:

- Fitur baru.
- Bug fix.
- Query database, migration, atau compatibility PostgreSQL.
- Business logic di Action, service, model method, state machine, policy, atau authorization.
- Flow yang pernah rusak atau rawan regresi.
- Validasi FormRequest atau transformasi DTO yang kompleks.
- Frontend store, composable, atau component dengan logic non-trivial.

### Boleh Skip Test dengan Alasan

Test boleh di-skip untuk:

- Perubahan dokumentasi saja.
- Perubahan formatting tanpa logic.
- Perubahan UI minor yang tidak mengubah behavior.
- Chore tooling/config sederhana.
- Perubahan repetitif yang risikonya rendah, misalnya rename kecil atau update teks.

Jika test di-skip, tulis alasannya di body PR. Jangan centang checklist test seolah-olah test sudah dibuat.

### Larangan Testing Anti-Pattern

Jangan menambahkan percabangan khusus testing di production code untuk membuat test hijau, misalnya:

```php
if (app()->environment('testing')) {
    // bypass behavior
}
```

Jika test sulit ditulis, perbaiki desain kode, gunakan factory, mock dependency yang tepat, atau pindahkan business logic ke Action/service yang bisa diuji. Jangan membuat perilaku aplikasi berbeda hanya karena environment `testing`.

### Command Test yang Umum

Backend:

```bash
php artisan test
./vendor/bin/pest
```

Frontend:

```bash
npm run test
npm run test:e2e
```

Jika task menyentuh database, prioritaskan verifikasi dengan PostgreSQL.

## Keamanan dan Secrets

- Jangan commit `.env`, credential, token, private key, API key, dump database, atau file konfigurasi produksi.
- Gunakan `.env.example` sebagai referensi nama variable, bukan tempat menyimpan secret asli.
- Jika secret tidak sengaja ter-commit, segera beri tahu maintainer. Jangan hanya menghapus file di commit berikutnya; secret harus dirotasi.
- Jangan paste credential di issue, PR, screenshot, atau komentar review.
- Pastikan screenshot evidence tidak menampilkan token, password, cookie, atau data sensitif.

## Menjalankan Tools

### Backend

Formatting check:

```bash
composer lint
```

Auto-fix formatting:

```bash
composer lint:fix
```

Static analysis:

```bash
./vendor/bin/phpstan analyse
```

Jika perlu memory lebih besar:

```bash
./vendor/bin/phpstan analyse --memory-limit=1G
```

Test backend:

```bash
php artisan test
```

Atau langsung Pest:

```bash
./vendor/bin/pest
```

Dengan Docker:

```bash
docker compose exec app composer lint
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app php artisan test
```

### Frontend

ESLint:

```bash
npm run lint
```

Prettier check:

```bash
npm run format:check
```

Auto-format:

```bash
npm run format
```

Unit test:

```bash
npm run test
```

Coverage:

```bash
npm run test:coverage
```

E2E test:

```bash
npm run test:e2e
```

Build:

```bash
npm run build
```

Dengan Docker:

```bash
docker compose exec app npm run lint
docker compose exec app npm run format:check
docker compose exec app npm run test
docker compose exec app npm run build
```

Catatan: saat dokumen ini ditulis, `package.json` belum punya script `lint:fix`. Gunakan `npm run format` untuk auto-format, lalu perbaiki error ESLint secara manual. Jika tim ingin auto-fix ESLint, buat task terpisah untuk menambahkan script tersebut agar ekspektasinya jelas.

## Definition of Done

Sebuah task dianggap selesai hanya jika seluruh kriteria yang relevan sudah terpenuhi:

- Scope sesuai issue `[Task]` dan tidak melebar ke refactor/fitur lain.
- Acceptance criteria di issue terpenuhi.
- Implementasi mengikuti konvensi bahasa, branch, commit, dan struktur kode di dokumen ini.
- Business logic ditempatkan di layer yang sesuai.
- Test ditulis atau diperbarui bila perubahan membutuhkan test.
- Jika test di-skip, alasan skip ditulis jelas di PR.
- Quality gate relevan sudah dijalankan.
- Tidak ada file sementara, artifact lokal, credential, `console.log`, `dd()`, atau `dump()` tertinggal.
- Migration, seeder, atau query sudah dicek di PostgreSQL jika menyentuh database.
- Evidence hasil test/check disiapkan untuk PR.
- PR memakai template resmi dan menutup issue dengan `Closes #<nomor>`.

## Menulis dan Submit Pull Request

Sebelum membuat PR:

- Pastikan issue `[Task]` sudah di-link.
- Pastikan branch tidak membawa perubahan di luar scope.
- Jalankan quality gate yang relevan.
- Pastikan tidak ada `console.log`, `dd()`, atau `dump()` tertinggal.
- Jangan commit file temporary seperti `pr_body.md`, scratch file, report lokal, atau artifact test.

Saat membuat PR:

- Gunakan `.github/PULL_REQUEST_TEMPLATE.md`.
- Isi bagian `Issue yang Diselesaikan` dengan `Closes #<nomor>`.
- Jelaskan ringkasan perubahan dengan fokus pada apa yang berubah dan kenapa.
- Tulis cara test secara konkret.
- Lampirkan evidence hasil test, screenshot terminal, atau screenshot UI bila relevan.
- Jika test tidak ditulis atau tidak dijalankan, tulis alasan eksplisit.
- Request review ke reviewer yang sesuai.

Contoh PR title:

```text
[Docs] Add contributing guide
[Bug] Fix PostgreSQL group by statistics query
[Chore] Setup Laravel Telescope
```

## Checklist Sebelum PR

- [ ] Issue `[Task]` sudah assigned ke author.
- [ ] Epic dan Story parent sudah dicek.
- [ ] Branch dibuat dari branch dasar terbaru.
- [ ] Scope perubahan sesuai issue.
- [ ] Backend formatting sudah dicek dengan Pint bila menyentuh PHP.
- [ ] Larastan dijalankan bila menyentuh backend.
- [ ] Pest dijalankan bila menyentuh backend atau query.
- [ ] ESLint/Prettier dijalankan bila menyentuh frontend.
- [ ] Vitest/Playwright dijalankan bila menyentuh frontend yang punya test relevan.
- [ ] Migration dicek di PostgreSQL bila menyentuh database.
- [ ] PR memakai template repo.
- [ ] Evidence test disiapkan.

## Review dan Merge

- Minimal satu reviewer harus approve sebelum merge.
- Komentar review harus diselesaikan sebelum merge.
- CI harus hijau jika workflow sudah tersedia.
- Author PR melakukan merge setelah approved.
- Gunakan **Squash and merge**.
- Jangan merge langsung ke `main` tanpa PR.

## Rujukan Best Practice

- GitHub merekomendasikan `CONTRIBUTING.md` di root, `docs`, atau `.github`, dan dokumen tersebut sebaiknya menjelaskan cara membuat issue/PR yang baik, ekspektasi komunitas, serta link ke dokumen terkait.
- Open Source Guides menekankan dokumentasi yang jelas, jalur kontribusi yang mudah dimulai, dan proses review yang transparan.
- Conventional Commits memberi format commit yang eksplisit dan mudah dibaca manusia maupun tooling.
- GitHub merekomendasikan relative link untuk menghubungkan README dengan dokumen kontribusi agar tetap bekerja di clone lokal.

Referensi:

- https://docs.github.com/en/communities/setting-up-your-project-for-healthy-contributions/setting-guidelines-for-repository-contributors
- https://opensource.guide/building-community/
- https://www.conventionalcommits.org/en/v1.0.0/
- https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/about-readmes
