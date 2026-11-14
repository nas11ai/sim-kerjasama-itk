# Paket A — Masukan Pemilik 10 Nov (Implementation Plan)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (native, keputusan pemilik
> 2026-11-10). Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** tujuh masukan pemilik (chat 2026-11-10) yang mengubah aplikasi: TKT, Operator mengelola form
builder, label hak akses, pemilih organisasi multi, Jurusan, welcome page, dan UI berbahasa Indonesia.

**Architecture:** perubahan tampilan + katalog; tidak ada tabel baru. Jurusan memakai tipe organisasi
`department` yang sudah ada di UL dan di pohon `parent_id`; pencocokan akses (ancestor-or-self, ADR 0013)
tidak berubah. Pemberian akses multi-organisasi tetap satu Action (`GrantFormAccess`) yang kini menerima
daftar organisasi dalam satu transaksi.

**Tech Stack:** Laravel 13, Inertia, Vue 3, reka-ui (`ui/combobox`, `ui/tooltip`), Pest, Vitest.

**Spec:** pesan pemilik 2026-11-10 + keputusan di sesi 2026-11-13 (dicatat di bawah). Paket lain dari pesan
yang sama (review UI impeccable, seeder data uji, staging, matriks QA Excel) **di luar** rencana ini dan
dikerjakan berurutan sesudahnya.

## Keputusan (sesi 2026-11-13, disetujui pemilik "langsung jalan pakai usulanmu")

- K1 Urutan paket: A (ini) → review UI impeccable → seeder data uji → staging (Neon + storage S3-kompatibel,
  bukan Google Drive) → matriks QA Excel.
- K2 Glosarium UI (Task 7) — kode, nama route, kolom DB, dan dokumen DDD tetap memakai istilah UL:
  Dashboard→Beranda, Submission→Pengajuan, Submit→Kirim, Draft→Draf, Form→Formulir,
  Form Builder→Pembuat Formulir, Upload→Unggah, Download→Unduh, File→Berkas, Reviewer→Penilai,
  Review→Penilaian, Step→Langkah, Phase(s)→Tahap, Field→Isian, Export→Ekspor, Audit Log→Log Audit,
  Login→Masuk, Logout→Keluar, Password→Kata Sandi, Settings→Pengaturan, Role→Peran,
  Permission→Hak Akses, User→Pengguna, Deadline→Tenggat, Preview→Pratinjau. Tetap: Monev, Operator, Email.
- K3 TRL tampil sebagai **TKT** (Tingkat Kesiapterapan Teknologi). Identifier kode (`trl_id`,
  `trl_selector`, `TechnologyReadinessLevel`) tidak diganti — UL dicatat "TKT (kode: `trl`)".
- K4 Operator mendapat `schemes.manage` (Form Builder, Form Evaluasi, Fase, Skema — satu gerbang W88).
- K5 Hak akses: label pendek bahasa Indonesia + tooltip berisi penjelasan satu kalimat dan kode mesinnya.
- K6 Organisasi di tab akses: combobox multi-pilih yang bisa dicari, berindentasi menurut pohon;
  organisasi yang sudah punya aturan untuk permission terpilih tampil nonaktif.
- K7 `/` untuk tamu = welcome page (membalik W157) dengan pengumuman publik; user masuk tetap ke Beranda.
  Panel pengumuman di halaman login tetap ("juga ada" = tambahan, bukan pindah).
- K8 Pratinjau formulir sudah ada (G3-W8, `FormPreview`) — tidak ada task.
- K10 (pemilik, 2026-11-13 di tengah sesi) Kotak pencarian topbar mencari **halaman dan fitur** seperti command palette
  pada umumnya (GitHub, Linear, VS Code): hasil instan di klien, cocok sebagian kata dan sinonim (termasuk istilah Inggris
  lama), letaknya di menu tampil di samping, hanya yang boleh dibuka user. Fitur = tindakan yang punya halaman tujuan
  langsung; fitur di dalam satu pengajuan (mis. tambah anggota) tidak didaftar karena butuh konteks.
- K9 (pemilik, 2026-11-13 di tengah sesi) Teks UI yang dilihat user **tidak boleh** memuat "—". Pemisah judul → titik dua
  ("Langkah 3: Skema & TKT", "TKT 4: Validasi Lab"); keterangan → kurung ("Fakultas X (beserta unit di bawahnya)");
  nilai kosong → "-". Komentar kode, dokumen DDD/BDD, dan pesan commit bukan UI.

## Global Constraints

- Tidak ada migration baru; tidak ada dependency baru.
- Setiap BR yang disentuh disebut kodenya di komentar; status hanya lewat `transitionTo()` (tak relevan di sini).
- Komentar: maks 3 baris prosa per blok (QualityBudget `hard.comment.prose`).
- Gate per task: test terkait; gate penuh di Task 8, satu langkah per waktu dengan watchdog memori.

## Review Focus

1. Registrasi mandiri: prodi di bawah Jurusan harus tetap muncul (RegistrableStudyPrograms saat ini mensyaratkan induk `faculty`) — test di Task 5.
2. Grant multi-organisasi yang memuat satu pasangan duplikat: tidak ada baris yang tersimpan sebagian — test di Task 4.
3. Statistik per fakultas dengan Jurusan di tengah: prodi tetap terhitung di fakultasnya (UnitFaculty rekursif) — test di Task 5.
4. Tamu yang membuka `/` setelah logout tidak mendarat di halaman yang butuh auth — test di Task 6.
5. Sapuan istilah tidak mengubah nilai yang dibaca mesin (value option, nama route, kunci props) — dijaga test yang sudah ada + vue-tsc di Task 7.

---

### Task 1: TRL → TKT di tampilan

**Files:** `app/Queries/Submission/{WizardReviewSummary,ResearcherSubmissionDetail,WizardFieldOptions}.php`,
`app/Queries/Form/FieldTypeOptions.php`, `app/Support/{WizardStepGate,SchemeFieldGuard}.php`,
`app/Support/Config/ReferenceEntityRegistry.php`, `resources/js/Pages/Scheme/FormPage.vue`,
`resources/js/Components/Submission/{InfoSection,Wizard/SchemeStep,Wizard/ReviewStep}.vue`,
`docs/requirement-analysis/ddd/02_ubiquitous_language.md` (baris TRL/trl_selector), test yang menegaskan teks lama.

- [ ] Ganti teks tampilan: `"TRL {level} — {name}"` → `"TKT {level} — {name}"`; `'Pilihan TRL'` → `'Pilihan TKT'`;
  label acuan `'Technology Readiness Level'` → `'Tingkat Kesiapterapan Teknologi (TKT)'`; judul langkah
  `'Skema, TKT & Target Luaran'`; pesan SchemeFieldGuard menyebut "pilihan TKT"; Vue: "TKT yang Diizinkan", kolom "TKT".
- [ ] `grep -rn "TRL" tests resources/js app` → perbarui setiap assert teks tampilan; komentar boleh tetap.
- [ ] UL: tambah entri **TKT** "Tingkat Kesiapterapan Teknologi; istilah tampilan untuk TRL, kode memakai `trl`".
- [ ] Jalankan test Scheme/Submission wizard + vitest komponen terkait → PASS. Commit `feat(scheme): TRL tampil sebagai TKT`.

### Task 2: Operator mengelola Form Builder

**Files:** `database/seeders/PermissionSeeder.php` (`operator` + `schemes.manage`),
`docs/requirement-analysis/ddd/generic/02_identity_access.md:179`, test baru `tests/Feature/Identity/OperatorFormBuilderAccessTest.php`.

- [ ] Test: `it('[K4] operator membuka Form Builder, Form Evaluasi, Fase, dan Skema')` — user `assignRole('operator')`
  setelah `seed(PermissionSeeder)`, GET `admin.forms.index`, `admin.phases.index` (form fixture), `admin.schemes.index` → 200.
  Plus `expect(Role::findByName('operator')->hasPermissionTo('schemes.manage'))->toBeTrue()`.
- [ ] Jalankan → FAIL 403. Tambah permission di matriks seeder + baris DDD 179. Jalankan → PASS.
- [ ] Cari test yang menegaskan operator ditolak di route `schemes.manage` (`grep -rn "operator" tests | grep -i "form\|scheme"`) → sesuaikan.
- [ ] Commit `feat(identity): operator mendapat schemes.manage`.

### Task 3: Label hak akses + tooltip

**Files:** `app/Support/Identity/PermissionCatalog.php`, `app/Data/View/FormEngine/FormAccessRuleData.php`,
`resources/js/Components/FormEngine/FormAccessTab.vue`, `resources/js/Components/Identity/UserAccessSection.vue`,
`resources/js/types/{form-engine,identity}.ts`, `tests/Unit/**/IdentityCatalogTest.php` (atau lokasinya), Vitest FormAccessTab.

- [ ] `PermissionCatalog::DESCRIPTIONS` (`array<string,string>`, kunci = `LABELS`), satu kalimat per permission;
  label disesuaikan glosarium (reviewer→penilai, user→pengguna, audit log→log audit).
  `options()` mengembalikan `{value, label, description}`; `description(string $name): string`.
- [ ] Test katalog: kunci DESCRIPTIONS === kunci LABELS === `PermissionSeeder::PERMISSIONS`.
- [ ] `FormAccessRuleData` + `permissionDescription`. FormAccessTab: kolom "Hak Akses"; baris `<code>` diganti ikon
  info ber-`UiTooltip` berisi deskripsi + kode; field form berlabel "Hak Akses". UserAccessSection: tooltip sama di
  daftar langsung & efektif.
- [ ] Vitest: tooltip trigger punya `aria-label` berisi label; kode tidak dirender di sel. Commit `feat(identity): hak akses berlabel dan bertooltip`.

### Task 4: Tab akses — organisasi multi-pilih

**Files:** `app/Http/Requests/Form/FormAccessControlRequest.php`, `app/Data/Request/FormAccessInput.php`,
`app/Actions/Form/GrantFormAccess.php`, `app/Queries/Identity/OrganizationOptions.php` (+ `depth`),
`app/Http/Controllers/FormEngine/FormAccessController.php` (flash berjumlah),
create `resources/js/Components/app/MultiOptionCombobox.vue`, modify `FormAccessTab.vue`,
tests `tests/Feature/FormEngine/FormAccessGrantTest.php`, `tests/frontend/components/FormEngine/FormAccessTab.spec.ts`,
`tests/frontend/components/app/MultiOptionCombobox.spec.ts`.

**Interfaces:** `FormAccessInput(formId:int, permission:string, organizationIds: list<int>)`;
`GrantFormAccess::run(FormAccessInput, User): list<FormAccessControl>`; option `{value,label,depth:int}`;
`MultiOptionCombobox` props `options: {value,label,depth?,disabled?}[]`, `v-model: string[]`, `placeholder`, `id`.

- [ ] Test Pest: grant 3 organisasi → 3 baris + 3 audit; satu sudah ada → ValidationException `duplicate` memuat nama
  organisasinya dan **0** baris baru (Review Focus 2); `organization_ids` kosong → 422; id nonaktif → 422 `organization_ids.*`.
- [ ] Request: `organization_ids` `required|array|min:1`, `organization_ids.*` integer exists aktif, `distinct`.
- [ ] Action: cek duplikat untuk semua id dulu (BR-FE-02), lalu buat semua dalam satu `DB::transaction`.
- [ ] Combobox: cari klien, item berindentasi `depth`, chip terpilih bisa dilepas, item `disabled` tak bisa dipilih.
  FormAccessTab menonaktifkan organisasi yang sudah ber-aturan untuk permission terpilih (dari `rules`; perlu
  `organizationId` di `FormAccessRuleData`). Flash: "{n} aturan akses ditambahkan."
- [ ] Pest + Vitest PASS. Commit `feat(form): aturan akses untuk beberapa organisasi sekaligus`.

### Task 5: Jurusan di antara Fakultas dan Prodi

**Files:** `app/Support/Identity/OrganizationTypeCatalog.php` (`'department' => 'Jurusan'`),
`database/seeders/OrganizationSeeder.php`, `app/Queries/Identity/RegistrableStudyPrograms.php`,
`app/Queries/Submission/ResearcherStudyProgram.php`, `resources/js/Components/Auth/OrganizationSelect.vue` (label grup),
tests registrasi, statistik, seeder.

- [ ] Seeder: Institusi → Fakultas → Jurusan → Prodi persis daftar pemilik (6 jurusan, 26 prodi). Kode jurusan:
  JSAD, JTEIB, JTK, JTSP, JTI, JRI. Prodi yang sudah ada dipindah induknya (upsert `parent_id`), tidak diduplikasi.
- [ ] Test seeder: setiap prodi berinduk `department`, setiap jurusan berinduk `faculty`; jumlah 3/6/26.
- [ ] RegistrableStudyPrograms: prodi aktif dikelompokkan per **induk langsung** (fakultas atau jurusan) yang aktif
  dan seluruh leluhurnya aktif; nama grup jurusan = "Jurusan X — Fakultas Y". Test: prodi di bawah jurusan muncul
  (Review Focus 1); jurusan nonaktif → prodinya tidak ditawarkan.
- [ ] ResearcherStudyProgram: `faculty.name` = leluhur bertipe `faculty` (bukan induk langsung). Test.
- [ ] Test statistik: prodi di bawah jurusan terhitung di fakultasnya (Review Focus 3).
- [ ] Commit `feat(identity): jurusan sebagai subunit fakultas`.

### Task 6: Welcome page

**Files:** `routes/web.php:63`, create `app/Http/Controllers/WelcomeController.php`,
create `resources/js/Pages/Welcome/WelcomePage.vue`, `tests/Feature/HomeRedirectTest.php` → `WelcomePageTest.php`,
Vitest `tests/frontend/pages/WelcomePage.spec.ts`, `docs/design/fe-v2-design.md` (baris W173 membalik W157).

- [ ] Test: tamu GET `/` → Inertia `Welcome/WelcomePage` dengan `announcements` (PublicAnnouncementPanel, ≤5,
  hanya yang berlaku untuk tamu); user masuk → redirect `dashboard`; route `login` tetap bisa dibuka.
- [ ] Halaman: AuthLayout-tanpa-kartu atau layout publik sederhana — logo ITK, nama SIMPAS + satu kalimat tujuan,
  tombol "Masuk" dan "Daftar", daftar pengumuman publik (pakai `PublicAnnouncementPanel`), kosong → tidak ada seksi.
- [ ] Commit `feat(welcome): halaman sambutan dengan pengumuman publik`.

### Task 7: Sapuan istilah UI ke bahasa Indonesia

**Files:** create `docs/design/2026-11-13-glosarium-ui.md` (tabel K2 + aturan "kode tetap UL");
teks tampilan di `resources/js/**` (template, label map, `navigation.ts`, `<Head title>`), dan string user-facing di
`app/**` (flash `with('success'|'error')`, `ValidationException::withMessages`, `messages()`, label Data/Support,
Notification subject/line, judul ekspor); test Pest/Vitest/Playwright yang menegaskan teks itu.

- [ ] Per istilah K2: `grep -rnw` di `resources/js` dan `app`, ganti hanya teks tampilan (bukan value, key, route,
  nama tipe, komentar kode). Nomor W/BR di komentar dibiarkan.
- [ ] Jalankan suite per area yang tersentuh; perbaiki assert teks. vue-tsc → 0 error.
- [ ] K9: ganti setiap "—" di teks tampilan (template Vue tanpa komentar HTML, string literal TS/Vue-script, string literal
  PHP di `app/` lewat `token_get_all`) menurut aturan K9.
- [ ] Guard: `tests/Unit/Architecture/NoEmDashInUiTest.php` — token string PHP di `app/` (bukan komentar) tanpa "—";
  Vitest `tests/frontend/architecture/noEmDash.spec.ts` — bagian `<template>` setiap `.vue` (komentar HTML dibuang) dan
  string literal di `resources/js/**/*.{ts,vue}` tanpa "—".
- [ ] Commit per kelompok (navigasi & shell; pengajuan; penilaian; formulir; identitas & laporan).

### Task 9: Pencarian halaman & fitur di topbar

**Files:** `resources/js/lib/navigation.ts`, create `resources/js/lib/paletteEntries.ts`,
`resources/js/Components/app/CommandPalette.vue`, tests `tests/frontend/lib/paletteEntries.spec.ts`,
`tests/frontend/components/app/CommandPalette.spec.ts`, `docs/design/fe-v2-design.md` (catatan K2 → K10).

**Interfaces:** `NavItem.keywords?: string[]`; `PaletteEntry { kind: 'page' | 'feature'; label; icon; routeName;
routeParams?; location: string | null; keywords: string[]; anyPermission; requiresOutputSubmission?; requiresMonevSubmission? }`;
`paletteEntries(groups: VisibleNavGroup[], permissions, flags): PaletteEntry[]` (halaman = item sidebar terlihat, `location`
= label grupnya; plus `FEATURES` tersaring izin); `searchPalette(entries, term, limit = 8): PaletteEntry[]`.

- [ ] `FEATURES` (route · izin · letak): Buat Pengajuan Baru (`user.submissions.create` · submissions.create · Pengajuan),
  Ubah Profil & Foto (`profile.edit` · semua · Akun), Ubah Kata Sandi (`profile.edit` · semua · Akun),
  Buat Pengumuman (`admin.announcements.create` · announcements.manage · Manajemen), Undang Pengguna
  (`admin.invitations.index` · users.verify|users.manage), Verifikasi NIDN (`admin.verifications.index` · users.verify),
  Tunjuk Penilai (`admin.reviewers.index` · reviewers.assign), Buat Formulir (`admin.forms.create`), Buat Formulir Evaluasi
  (`admin.evaluation-forms.create`), Buat Tahap (`admin.phases.create`), Buat Skema (`admin.schemes.create`) — empat terakhir
  schemes.manage · Konfigurasi; Ekspor Data (`reporting.exports.index` · reporting.export · Laporan), Statistik Saat Ini /
  Riwayat / Tindak Lanjut (`reporting.stats` + tab `now|history|follow-up`), Catat Luaran (`research-outputs.create` ·
  outputs.manage + requiresOutputSubmission). Kata kunci memuat sinonim + istilah lama (form, user, reviewer, export,
  dashboard, password, audit log, …).
- [ ] `searchPalette`: normalisasi huruf kecil + buang diakritik; setiap token kueri harus cocok di label, letak, atau kata
  kunci. Urutan: label diawali kueri < kata di label diawali kueri < label memuat kueri < cocok lewat letak/kata kunci;
  seri → urutan asli. Kueri kosong → semua halaman sidebar (perilaku lama), tanpa fitur. Tes: "sandi" → Ubah Kata Sandi;
  "form" → Pembuat Formulir & Buat Formulir; "user" → Pengguna & Verifikasi; "eks dat" → Ekspor Data; peneliti tidak
  pernah melihat entri schemes.manage; satu route+params muncul sekali.
- [ ] Palette: grup "Halaman" dan "Fitur" di atas hasil server; tiap item menampilkan `location` redup di kanan;
  placeholder "Cari halaman, fitur, pengajuan, pengguna...". Commit `feat(search): palette mencari halaman dan fitur`.

### Task 8: Gate penuh + dokumentasi

- [ ] `free -m` ≥ 3 GB, watchdog; satu per satu: pint, phpstan (2 tier), pest, eslint, prettier, vue-tsc, vitest.
- [ ] Catat hasil di bagian Status rencana ini; temuan yang tak langsung diperbaiki → catatan bug.
- [ ] Commit `docs: paket A selesai`.

## Status

**2026-11-13 (sesi 1)** di branch `worktree-paket-a` (dir `.claude/worktrees/r1-jadwal-periode`, dari 3aee1268), belum di-merge/push:

| Task | Status | Commit |
| ---- | ------ | ------ |
| 1 TKT | ☑ | 79da224f |
| 2 Operator `schemes.manage` | ☑ | e7f5f5bd |
| 3 Label hak akses + tooltip | ☑ | 0856a552 |
| 4 Organisasi multi-pilih | ☑ (komponen jadi `Components/app/MultiOptionPicker.vue`: daftar centang bercari, bukan popover) | 161a9e3a |
| 5 Jurusan | ☑ (3 fakultas, 6 jurusan, 26 prodi; `Organization::facultyAncestor()`) | 39e3ac4e |
| 6 Welcome page | ☑ (W174 di fe-v2-design) | 7dbbc978 |
| 9 Palette halaman & fitur | ☑ (`searchNavItems` diganti `lib/paletteEntries.ts`) | 56b23699 |
| 7 Sapuan istilah | ☑ em-dash (e9bc1476); navigasi & shell (d1014510); pengajuan, penilaian, formulir, identitas & laporan (sesi 2) | lihat commit sesi 2 |
| 8 Gate | ☑ sesi 2: pint, phpstan ×2, Pest 2424, eslint, prettier, vue-tsc, Vitest 798 | |

**Sesi 2 (2026-11-13), sisa Task 7:** satu sapuan untuk keempat kelompok, karena teksnya bercampur di berkas yang sama.
Pengubah berbasis token (template Vue, string TS, string PHP) dengan aturan frasa berurutan lalu ditinjau di diff.
Keputusan yang dicatat di glosarium: nama status pengajuan (Draf, Menunggu Penilaian, Sedang Dinilai, ...) dan
`SubmissionStatus::options()` diselaraskan dengan `label()` (sebelumnya Inggris di filter), dijaga test;
deskripsi log audit **tidak** diganti karena jadi kunci kueri (catatan bug B18). Tambahan: dokumen cetak Blade
("Judul Formulir", nilai kosong "-") dan guard em-dash untuk Blade; sidebar "Fase" → "Tahap"; pesan validasi lembar
penilaian kini bahasa Indonesia; `Submission/IndexPage` dan `Review/HistoryPage` memakai `FilterToolbar` (blok kotak-cari
yang identik setelah teksnya diseragamkan). Spec E2E diperbarui tetapi tidak dijalankan (butuh server).

**Catatan:** item sidebar "Buat Pengajuan Baru" punya `routeName: null` (tersembunyi) padahal route
`user.submissions.create` ada; palette menawarkannya sebagai fitur. Sidebar tidak diubah.

## Paket 2: perbaikan P1 critique (sesi 4, 2026-11-13)

Critique 23/40 (`.impeccable/critique/2026-11-13T14-52-15Z__resources-js.md`, tidak di-commit). Pemilik memilih: locale +
nada status dulu, dasar netral dengan warna hanya untuk status, cakupan = tiga P1. Gate: pint, phpstan ×2, Pest 2438,
eslint, prettier, vue-tsc, Vitest 808; detector impeccable 0 temuan baru (blockquote = false positive lama).

| P1 | Perbaikan |
| -- | --------- |
| Locale bocor | `config/app.php` mengunci `locale = id` dan `name = SIMPAS` (bukan dari `.env`); `lang/id` (auth, passwords, pagination, validation + nama field); judul tab `... - SIMPAS`; Dasbor → Beranda, Baca-saja → Hanya baca; seeder E2E "Research" → "Penelitian"; "Periode Periode" diatasi `PeriodName` / `formatPeriodName` (awalan hanya bila belum ada); `FileInput` menggantikan tombol "Choose File" bawaan peramban di tujuh tempat; guard Vitest menolak Dasbor, Baca-saja, Laravel di teks UI. |
| Nada status + kontras | Token `success` / `warning` / `info` (+ tint, garis tepi, light & dark) dan `destructive` 42% (3.76:1 → 6.47:1); badge nada berupa tint (≥5.6:1, success lama 2.3:1); AppBanner per nada (lock tetap netral); `AlertTitle` bukan h5 lagi; ring fokus kontrol 2px. Nada status pengajuan: Draf outline, Menunggu Penilaian / Sedang Dinilai / Diajukan Ulang info, Perlu Revisi warning, Disetujui success, Ditolak destructive, Ditarik Kembali secondary. |
| Jalur pengajuan pertama | "Buat Pengajuan Baru" kini di sidebar dan tidak pernah nonaktif-dengan-alasan-tersembunyi; ketiga pintu menaut `user.submissions.create`, backend meneruskan ke Lengkapi Profil yang kini menjelaskan pengalihan dan janji kembali ke langkah 1; banner profil Beranda membawa `next=submission`; `EmptyState` punya slot `#action`, Daftar Pengajuan kosong menawarkan pengajuan pertama. |

Sisa yang sengaja tidak dikerjakan: B22 (periode di sambutan, kartu langkah berikutnya), B23 (metode variant tak terpakai,
nada Monev), B24 (`MAIL_FROM_NAME` dari `.env` lama) di catatan bug. Dev DB perlu di-seed ulang (Pest mengosongkannya).

## Langkah 3: seeder data uji QA (2026-11-14)

Rencana `docs/plans/2026-11-14-seeder-data-uji-qa.md`, desain `docs/design/2026-11-13-seeder-data-uji-qa-design.md`,
cara pakai untuk QA `docs/qa/data-uji.md`. `php artisan db:seed --class=QaDataSeeder` sesudah `migrate:fresh --seed`:
empat set penguji (`qa1`–`qa4`, masing-masing 19 akun dan 16 pengajuan di setiap status, termasuk riwayat tahun
lalu) plus volume institusi (30 peneliti latar, 60 pengajuan); seluruh perubahan status lewat Action asli. Temuan
yang tidak diperbaiki: B25 (TKT tanpa seeder, simpan TKT di Konfigurasi gagal), B26 (`DummyUserSeeder` peran lama,
fixture E2E menulis status), B27 (unggahan selalu ke disk privat `local`, menentukan rancangan staging). Berikutnya menurut K1: staging (Neon + storage S3-kompatibel, `APP_ENV=staging`), mulai dari B27.

## Langkah 4: staging QA (2026-11-14)

Pemilik memilih satu host bersama di VM gratis, bukan tiap laptop + Neon + R2 (adapter S3 belum terpasang, dan
antrean, ekspor, serta Reverb nyasar antar laptop). Desain `docs/design/2026-11-14-staging-vm-design.md`, rencana
`docs/plans/2026-11-14-staging-vm.md`, panduan pemilik `docs/qa/staging.md`. Tumpukan: Caddy (HTTPS otomatis,
Basic Auth seluruh situs) + php-fpm + PostgreSQL + antrean + Reverb + scheduler di VM Oracle Always Free (Ampere A1),
`APP_ENV=staging`; B27 selesai tanpa perubahan kode. Pemilik: membuat akun dan VM, push cabang yang dilacak VM.
Berikutnya menurut K1: matriks QA Excel.
