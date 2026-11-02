# FE v2 — Irisan I1: User, Verifikasi, Undangan, Reviewer — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Lima frame Identity & Access (OPERATOR-08-01..04, ADMIN-08-01) menjadi empat halaman v2 di `Pages/Identity/`. Isinya daftar user, detail user (deaktivasi, peran, hak akses langsung), antrian verifikasi NIDN (termasuk koreksi NIDN dan berkas biodata), undangan registrasi, dan manajemen reviewer. Halaman `Users/*`, `Identity/*` lama, dan `Reviewers/*` beserta route dan controller legacy-nya dihapus, dan setiap route dijaga permission DDD (§9 K1).

**Architecture:** Mengikuti pola S4. Controller tipis di `app/Http/Controllers/Identity/` memanggil Query (`app/Queries/Identity/`) dan Action (`app/Actions/Identity/`). Props disusun dari `Data` class di `app/Data/View/Identity/`, termasuk objek `can` dari `UserPolicy` yang baru. Route lama yang hanya mengandalkan `abort_unless(...can('users.manage'))` di controller pindah ke middleware permission sesuai `sidebar.md`. Setiap pasangan dikerjakan FE dulu baru BE, karena `ensure_pages_exist => true` di `config/inertia.php`. Halaman legacy dihapus di task BE yang berhenti merendernya.

**Tech Stack:** Laravel 13 / PHP 8.4, spatie/laravel-data, spatie/laravel-permission, Inertia v3 + Vue 3 `<script setup>` + TS, shadcn-vue, Ziggy, Pest, Vitest. Tanpa dependency baru.

**Spec:** `docs/design/fe-v2-design.md` §4.2–§4.5, baris I1 di §8, K1/W3/W4/W5/W15/W34 di §9. Frame `../simpas-wireframe/screens/{OPERATOR-08-01..04,ADMIN-08-01}/screen.js`, bagian yang sama di `docs/FRAME-AUDIT.md` dan `docs/STATE-COVERAGE.md`. DDD `ddd/generic/02_identity_access.md` (BR-IAM-02/05/09..16/19, activity diagram Jalur 1–3), `ddd/core/02_review.md` (BR-REV-04/09/10/11), BR-SM-09, BR-RPT-09. BDD `bdd/generic/identity/{verification,deactivation,invitation,permission,reviewer_provisioning,account_events,registration}.feature`. Navigasi `ux/ia/00_navigation/sidebar.md`.

**Pembagian session (§6, irisan dipecah sebelum dikerjakan):** **I1-1 = Task 1–5d** (fondasi, daftar & detail user, peran, dampak deaktivasi, sesi & kehadiran, pengaktifan kembali) dan **I1-2 = Task 6–12** (verifikasi NIDN, undangan, reviewer, penutup). Gate quality harus hijau di akhir setiap task.

## Kesesuaian dengan DDD dan BDD (diperiksa 2026-11-02)

- **Semua skenario yang mengikat kelima frame sudah hijau di Pest** (`pending.txt` dan `uncovered.txt` tidak memuat ID IAM): IAM-DEACT-01..08, IAM-PERM-01..04, IAM-REVIEWER-01..06, IAM-INVITE-01..08, IAM-VERIFY-01..12, IAM-REG-05/09/14/16, IAM-EVENT-01..05, SM-OWNER-03/04, REV-ASSIGN-08. Irisan ini mempertahankan semuanya. Test yang memanggil route yang dihapus dipindah ke route penggantinya **dengan ID yang sama** (Task 5, 7, 11).
- **State wajib dari `STATE-COVERAGE.md` digambar dan diuji Vitest dengan ID-nya:** IAM-DEACT-07 (Task 2, 3), IAM-DEACT-03/05/06, SM-OWNER-03/04, IAM-PERM-02/03/04 (Task 3), IAM-REG-14, IAM-VERIFY-07/08/10 (Task 6), IAM-INVITE-02/04/05/06, IAM-REG-09/16 (Task 8), IAM-DEACT-04, IAM-REVIEWER-03/04/05, REV-ASSIGN-08 (Task 10). Semuanya sudah dicakup Pest, jadi `pending.txt` tidak berubah (§5).
- **Satu perilaku DDD yang belum punya jalur maupun skenario: koreksi NIDN.** Activity diagram Jalur 1 berbunyi "NIDN dikoreksi Operator? → Ya → NIDN valid?", BR-IAM-13 berbunyi "Operator tetap bisa memverifikasi akun itu setelah NIDN-nya dikoreksi", dan wireframe 08-02 menggambar "NIDN dikoreksi menjadi …". Kode hari ini tidak punya cara mengubah NIDN sama sekali, sehingga test IAM-VERIFY-10 hanya memverifikasi ulang tanpa koreksi. Mengikuti preseden S4/D1 (skenario ditulis bersama test Pest-nya), Task 7 menambah ke `verification.feature`:
  - `[IAM-VERIFY-13]` @BR-IAM-13 @BR-IAM-02 @M3 @OPERATOR-08-02 @happy: Operator mengoreksi NIDN akun `rejected`. NIDN berubah, status **tetap** `rejected` (koreksi bukan verifikasi), dan jejak audit memuat NIDN lama dan baru.
  - `[IAM-VERIFY-14]` @BR-IAM-02 @M3 @OPERATOR-08-02 @denial: koreksi ke NIDN yang sudah dipakai akun lain ditolak, dan NIDN-nya tidak berubah.
  Test IAM-VERIFY-10 diperkuat menjadi koreksi lalu verifikasi. DDD tidak disunting.
- **Hak akses langsung dan peran belum lewat Action dan tidak diaudit.** `UserAccessController` memanggil `givePermissionTo` langsung, dan `UserController::update` memanggil `syncRoles`/`syncPermissions` tanpa jejak, padahal BR-RPT-09 meminta setiap keputusan tercatat. Task 5 memindahkannya ke tiga Action (strangler). Test IAM-PERM-01/02/04 diubah supaya memanggil Action itu, bukan Spatie langsung, sehingga langkah "When Operator memberi/mencabut" benar-benar melewati jalur produksi.
- **Penegakan BR-IAM-05 bolong di empat tempat** (ditemukan 2026-11-02, diperbaiki Task 5b dengan skenario IAM-DEACT-09..12 dan SM-OWNER-07):
  - Sesi yang sudah login tidak pernah diperiksa ulang.
  - `AssignReviewer`/`ReplaceReviewer` tidak menolak akun nonaktif. Test `[IAM-DEACT-04]` ternyata mengikat penunjukan, bukan penugasan yang ditulis skenarionya, sehingga diikat ulang.
  - Kepemilikan bisa dialihkan ke co-investigator berakun nonaktif.
  - Anggota nonaktif bisa ditambahkan.
- **Selisih wireframe↔DDD, DDD menang** (dicatat ke §9 di Task 12): kolom "Status" 08-01 menggabungkan dua sumbu yang oleh DDD dipisahkan dengan tegas (`is_active` dan `verification_status`, bagian "Status Akun dan Status Organisasi"). Di v2 keduanya tampil sebagai badge terpisah (W49). Badge "Beban Tinggi" 08-04 tidak punya ambang global, karena BR-REV-04 mengukurnya per skema (W54).

## Keputusan penulis rencana (dicatat ke §9 di Task 12; W47, W48, W60 diputuskan pemilik 2026-11-02: Operator menerbitkan undangan, BR-IAM-20/21 ditambahkan ke DDD, Reverb jadi irisan RT1)

- **W47 — wewenang per layar.** Ada tiga sumber dengan bunyi berbeda. `sidebar.md` berbunyi Reviewer = `reviewers.assign` dan User & Verifikasi = `users.verify` atau `users.manage`. DDD menyebut penunjukan reviewer dan undangan sebagai wewenang "Operator" (BR-IAM-11/16), sementara diagram deaktivasi menyebut "Admin". BDD IAM-INVITE-01 menuliskan aktornya sebagai "Operator memegang permission `users.manage`". Pemetaan yang dipakai:

  | Layar / aksi | Permission | Dasar |
  | --- | --- | --- |
  | Daftar & detail user | `users.verify` **atau** `users.manage` (`UserPolicy::viewAny/view`) | `sidebar.md` |
  | Antrian verifikasi, verifikasi, tolak, koreksi NIDN, berkas biodata | `users.verify` | BR-IAM-13, perilaku yang sudah ada |
  | Manajemen reviewer, tunjuk, ubah masa tugas | `reviewers.assign` (keluar dari `can:users.manage`) | `sidebar.md`, DDD Jalur 3 |
  | Undangan: terbit, cabut, daftar | `users.verify` **atau** `users.manage` (`InvitationPolicy::manage`) | **Pemilik, 2026-11-02**: Operator ikut menerbitkan (BR-IAM-16 harfiah); IAM-INVITE-01 (aktor `users.manage`) tetap berlaku |
  | Deaktivasi, peran, hak akses langsung | `users.manage` | wireframe 08-03 ("hanya dapat dilakukan oleh Admin"), diagram deaktivasi DDD |

  Akibatnya peran `operator` (tanpa `users.manage`) bisa melihat user, memverifikasi, menunjuk reviewer, serta menerbitkan dan mencabut undangan, tapi tidak bisa menonaktifkan atau mengubah peran. Karena Operator kini bisa melahirkan akun aktif tanpa verifikasi, preset hak akses undangan (W51) menjadi batas keamanan, bukan sekadar kenyamanan: Operator tidak boleh bisa menerbitkan akun yang lebih berkuasa daripada peneliti. Keputusan ini dicatat sebagai skenario `[IAM-INVITE-09]` (Task 9).
- **W48 — "Aktifkan" (08-01) dibangun lewat BR-IAM-20** (pemilik, 2026-11-02). Sebelumnya DDD dan BDD tidak punya aturan pengaktifan kembali, dan README BDD mewajibkan setiap skenario bertag BR yang terdefinisi. Alasannya mirip BR-IAM-13: akun yang tak bisa dihidupkan lagi = akun mati karena satu salah klik. Teks BR dan lima skenarionya di Task 5d.
- **W59 — dampak deaktivasi pada pekerjaan berjalan** (Task 5b, semuanya turunan BR yang sudah ada):
  - Sesi yang sedang login berakhir di request berikutnya (middleware), dan sesi database serta remember-me dicabut saat itu juga.
  - Pengajuan aktif sebagai ketua tetap memblokir (BR-IAM-10).
  - Penugasan review berjalan **tidak** memblokir dan tidak dihapus. Ia tetap `active` dan ditandai "perlu diganti" di layar Operator (BR-IAM-05 + BR-REV-10). Akibatnya keputusan pengajuan itu menunggu sampai Operator mengganti reviewernya, sebuah konsekuensi yang disengaja karena evaluasi dari akun yang tak bisa bertindak tidak akan pernah selesai.
  - Akun nonaktif tidak bisa ditugaskan, menjadi pengganti, menerima kepemilikan, atau ditambahkan sebagai anggota.
  - Yang **tidak** berubah: ulir diskusi terbuka diwarisi pengganti (BR-REV-15), undangan yang ia terbitkan tetap berlaku (BR-IAM-06 menutup daftar sebabnya; cabut manual bila perlu), ekspor yang sedang diproses tetap selesai tapi hanya bisa diunduh setelah login, dan pengumuman yang ia tulis tetap tayang.
- **W60 — "seketika" dan kehadiran langsung.** Penegakan yang mengikat ada di server: setiap request memeriksa `is_active`, sehingga akun nonaktif keluar pada klik berikutnya. Tab yang terbuka tanpa aktivitas baru bereaksi saat itu juga hanya bila server bisa mendorong pesan (WebSocket). Polling ditolak, karena setiap ketukan memperpanjang sesi dan mematikan batas idle `SESSION_LIFETIME`. Laravel Reverb adalah dependency dan proses baru, jadi **pemilik, 2026-11-02:** irisan tersendiri **RT1** setelah F99 (§8, §9 K7). Kehadiran ("sedang aktif", "terakhir aktif", "Akhiri Semua Sesi") dibangun sekarang dari tabel `sessions` yang sudah ada (Task 5c, BR-IAM-21), lewat `SessionRegistry` sebagai satu-satunya pembacanya, sehingga RT1 cukup mengganti sumber kehadiran tanpa menyentuh layar.
- **W49** — Status akun dan status verifikasi tampil sebagai dua badge. Badge akun berbunyi "Aktif" atau "Nonaktif". Badge verifikasi berbunyi "Menunggu Verifikasi" atau "Ditolak", dan hanya tampil bila belum terverifikasi. Akun undangan dan akun tanpa profil tidak punya badge verifikasi. Aksi deaktivasi hanya ada di detail, tidak di baris tabel, supaya penghalang SM-OWNER-03/04 tidak dihitung per baris.
- **W50** — Membuat user dengan password oleh Admin (`admin.users.create/store`) dan menyunting nama/email/password (`admin.users.edit/update`) dihapus beserta `CreateUserRequest`/`UpdateUserRequest`. Wireframe dan DDD hanya mengenal dua jalur registrasi (BR-IAM-12). Di manajemen reviewer, "Hapus" (`DismissReviewer`), `activate`, dan `deactivate` juga dihapus. Masa tugas yang diakhiri lewat "Edit Periode" menggantikannya dan tetap menyisakan jejak (BR-REV-10, BR-RPT-09).
- **W51** — Undangan:
  - Hak akses dipilih dari preset `InvitationPreset` (satu nilai: "Membuat & mengelola pengajuan" = permission peran `researcher`), bukan daftar bebas. Parameter `permissions[]` yang dipalsukan diabaikan.
  - Tautan tampil **sekali** setelah terbit, lewat flash native Inertia `issuedInvitationUrl` yang tidak tersimpan di riwayat browser, beserta tombol "Salin Link". Baris tabel tidak punya "Salin Link" karena token tidak dikirim ke daftar.
  - "Buat Ulang" mengisi form dengan organisasi dan preset baris itu.
  - Status menyebut sebabnya ("Dicabut", "Organisasi Nonaktif", "Kuota Habis", "Kedaluwarsa"). Peleburan pesan anti-enumerasi W3 hanya berlaku di layar pendaftar.
  - Maks. pemakaian dan Berlaku Sampai wajib diisi (frame). Tanggal disimpan pukul 23:59:59 (pola W34).
- **W52** — Koreksi NIDN dilakukan Operator (`users.verify`) lewat `CorrectNidn`, tanpa notifikasi karena BR-IAM-15 tidak mendaftarnya, dan tanpa mengubah status. Verifikasi tetap tindakan terpisah.
- **W53** — "Ditolak pada" (W5) diturunkan dari entri audit `RejectNidnVerification::AUDIT_DESCRIPTION` terbaru (pola W7), tanpa kolom baru. Nilainya tampil di kartu antrian 08-02 **dan** di SHARED-01-05 (`AccountVerificationData.rejectedAt`), sehingga W5 tertutup.
- **W54** — "Beban Tugas" = penugasan `active` yang evaluasinya belum beres (`unsettledEvaluation`). Badge "Beban Tinggi" tidak digambar.
- **W55** — Tab "Log Aktivitas" di 08-03 tidak dibangun, menunggu `Reporting/AuditPage` di R1 (filter pelaku). "Ditambahkan oleh … · tanggal" pada hak akses tambahan juga tidak digambar. Filter "Organisasi" di 08-01 tidak dibangun (pola W28).
- **W56** — Undangan tidak punya item sidebar (`shell.yaml` pun tidak). Pintunya tombol "Undangan Registrasi" di header `UserIndexPage` dan tombol di EmptyState pencarian reviewer (IAM-REVIEWER-03). Keduanya hanya tampil bila `InvitationPolicy::manage` lolos (Operator dan Admin).
- **W57** — Penyuntingan peran dibatasi `RoleCatalog::ASSIGNABLE` = `researcher`, `operator`, `admin`. `reviewer_internal`/`reviewer_external` hanya lewat penunjukan (BR-IAM-11; tanpa itu record `reviewers` dan role-nya bisa menyimpang). Role legacy (`Super Admin`, `Admin`, `Tenaga Kependidikan`, `Mahasiswa`) tidak ditawarkan dan tidak tersentuh oleh sinkronisasi. Akun Super Admin dan akun milik penyunting sendiri tidak bisa dinonaktifkan atau diubah perannya (Policy). Super Admin sebagai **pelaku** tetap melewati Policy lewat `Gate::before`.
- **W58** — Berkas biodata pendaftar (W15) dibaca Operator lewat `admin.verifications.files.show`, dijaga `users.verify` dan dicakup ke submission biodata milik profil itu.
- **W4 ditutup** oleh `PermissionCatalog`: label manusia untuk setiap permission, dipakai di detail user, undangan, dan halaman registrasi undangan SHARED-01-03.

## Global Constraints

- Berkas Vue baru ≤ 250 baris, sasaran < 150; PHP baru ≤ 200 baris, method ≤ 40 baris, CC ≤ 8, ≤ 4 parameter. Tanpa blok duplikat 6 baris dengan berkas mana pun, termasuk legacy: tulis dari frame, jangan menyalin `Reviewers/IndexPage.vue`, `Users/EditPage.vue`, `Identity/*.vue`.
- Baris pertama `<script setup>`: `// Frame: OPERATOR-08-01` / `OPERATOR-08-02` / `OPERATOR-08-03` / `OPERATOR-08-04` / `ADMIN-08-01`.
- Teks UI diambil dari frame apa adanya, termasuk judul, subjudul, label kolom, label tombol, dan kalimat banner. Kalimat di `stateGallery` adalah anotasi, **kecuali** banner/label yang dipakai ulang sebagai state di bawah (disebut eksplisit per task).
- Frontend tidak memeriksa nama peran atau permission untuk aksi. Semuanya dibaca dari objek `can` per halaman. Label peran, permission, status, dan tipe dikirim backend.
- Identifier Inggris; komentar Indonesia, prosa maks 3 baris, sebut kode BR. TS tanpa `any`/`console.log`/cast; PHPStan tanpa `@phpstan-ignore`, baseline, `assert()`, inline `@var`, cast.
- PostgreSQL: pencarian lewat `whereLike` (ILIKE) dengan `%`/`_` di-escape.
- Mutasi lewat `useForm`/`router`, tanpa `axios`. Filter dan paginasi di server lewat `useFilters`. Konfirmasi lewat `ConfirmDialog` atau `ActionDialog`.
- Setiap Action baru mengaudit (BR-RPT-09, `AuditCoverageTest`).
- Gate: `composer run quality` + `npm run quality`. Pest di container `simpas-ci:8.4` (Docker Desktop menyala), `-e DB_PORT=55432 -e DB_PASSWORD=postgres`, **serial** (`--parallel` gagal di DB bersama). Worktree butuh salinan `vendor/` asli dan `.env`.
- Commit satu baris, Indonesia, conventional, **tanpa** trailer `Co-Authored-By`.

## Review Focus

1. **Eskalasi lewat undangan.** Operator (hanya `users.verify`) mengirim `permissions[]=users.manage` atau preset karangan untuk melahirkan akun admin. Token tetap hanya berisi permission preset `researcher`, dan preset tak dikenal ditolak 422. → Task 9 `permission di luar preset diabaikan` dan `preset tak dikenal ditolak`.
2. **Mengunci diri sendiri atau menyentuh Super Admin.** Admin menonaktifkan atau mencabut peran `admin` dirinya, atau menyunting akun Super Admin. Hasilnya 403 dan tidak ada yang berubah. → Task 5 `aksi atas diri sendiri ditolak` dan `akun Super Admin tidak bisa disunting`.
3. **Sinkronisasi peran menghapus role reviewer atau role legacy.** User reviewer_internal + `Tenaga Kependidikan` diberi peran `operator`. Kedua role lama tetap ada, dan record reviewer tidak terlepas dari role-nya. → Task 5 `peran di luar ASSIGNABLE tidak tersentuh`.
4. **Menebak berkas biodata orang lain.** Mengganti `{profile}` atau `{response}` di URL berkas ke milik user lain, atau ke jawaban non-file. Hasilnya 404, tanpa membocorkan keberadaan berkas. → Task 7 `berkas milik profil lain 404` dan `jawaban bukan file 404`.
5. **User dinonaktifkan saat sedang login** (tab terbuka, atau cookie remember-me dari kemarin). Klik berikutnya mengeluarkannya ke login dengan pesan, tanpa satu pun aksi tulis yang lolos. → Task 5b `[IAM-DEACT-09]` + varian remember-me. (Pencarian `%`/`_`/NIDN sebagian tetap diuji di Task 4 dan 11.)

---

## I1-1 — Fondasi, user, deaktivasi, sesi

### Task 1: Fondasi Identity — label peran & permission, `UserPolicy`, `ActionDialog` bersama (BE + pindah komponen)

**Files:**
- Create: `app/Support/Identity/RoleCatalog.php`, `app/Support/Identity/PermissionCatalog.php`, `app/Policies/UserPolicy.php`, `app/Policies/InvitationPolicy.php`
- Modify: `app/Support/Announcement/AnnouncementAudience.php` (`ROLE_LABELS` dipindah ke `RoleCatalog::LABELS` lewat **Move Field**; `AnnouncementAudience` membacanya dari sana), `app/Providers/AppServiceProvider.php` (`Gate::policy(User::class, UserPolicy::class)`, `Gate::policy(InvitationToken::class, InvitationPolicy::class)`)
- Move: `resources/js/Components/Submission/Manage/ActionDialog.vue` → `resources/js/Components/app/ActionDialog.vue` (**Move Class**). Ubah keenam import di `Components/Submission/Manage/*Dialog.vue` dan komentar kepalanya menjadi umum ("Kerangka dialog aksi dengan alasan/konfirmasi").
- Test: `tests/Unit/Architecture/IdentityCatalogTest.php`, `tests/Feature/Identity/UserPolicyTest.php`

**Interfaces:**
- Produces: `RoleCatalog::LABELS: array<string,string>` (`researcher`→`Peneliti`, `reviewer_internal`→`Reviewer (internal)`, `reviewer_external`→`Reviewer (eksternal)`, `operator`→`Operator`, `admin`→`Admin`). Label pengumuman tetap menghasilkan teks yang sama seperti sekarang lewat peta milik `AnnouncementAudience` sendiri bila teksnya berbeda ("Dosen", "Operator LPPM"); jangan ubah teks yang sudah tampil di A1. Juga `RoleCatalog::ASSIGNABLE = ['researcher','operator','admin']`, `RoleCatalog::options(list<string> $names): list<array{value:string,label:string}>`, dan `RoleCatalog::label(string): string` (fallback nama mentah).
- Produces: `PermissionCatalog::LABELS` untuk **setiap** `PermissionSeeder::PERMISSIONS`, mis. `submissions.create`→"Membuat pengajuan", `submissions.view-own`→"Melihat pengajuan sendiri", `submissions.view-all`→"Melihat semua pengajuan", `submissions.view-assigned`→"Melihat pengajuan yang ditugaskan", `budget.edit`→"Menyunting anggaran", `members.manage`→"Mengelola anggota", `reviewers.assign`→"Menugaskan reviewer", `reviewers.evaluate`→"Mengevaluasi pengajuan (reviewer)", `reviewers.view-scores-others`→"Melihat skor reviewer lain", `periods.manage`→"Mengelola periode", `schemes.manage`→"Mengelola skema & konfigurasi", `outputs.manage`→"Mengelola luaran", `users.verify`→"Memverifikasi NIDN", `users.manage`→"Mengelola user & hak akses", `reporting.export`→"Mengekspor laporan", `reporting.view-audit-log`→"Melihat audit log", `announcements.manage`→"Mengelola pengumuman". Ditambah `label(string): string` (fallback nama mentah) dan `options(list<string>): list<array{value,label}>`.
- Produces: `UserPolicy`:
  - `viewAny(User $actor): bool` dan `view(User $actor, User $user): bool` → `users.verify` ∨ `users.manage`.
  - `deactivate`, `changeRoles`, dan `managePermissions(User $actor, User $user): bool` → `users.manage` ∧ `$actor->isNot($user)` ∧ target tidak ber-role `Super Admin` (BR-IAM-05/09, W57).
- Produces: `InvitationPolicy::manage(User $actor): bool` → `users.verify` ∨ `users.manage` (W47, pemilik 2026-11-02). Satu definisi untuk route, `can` halaman, dan guard Action.
- Produces: `Components/app/ActionDialog.vue` (props tetap: `title`, `description?`, `submitLabel`, `destructive?`, `processing?`, `submitDisabled?`; `v-model:open`; emit `submit`).

- [ ] **Step 1: Tulis test yang gagal.** `IdentityCatalogTest`:
  - `it('setiap permission katalog punya label')` → `array_keys(PermissionCatalog::LABELS)` sama persis dengan `PermissionSeeder::PERMISSIONS` (urutan bebas).
  - `it('setiap peran DDD punya label')` → kunci `ROLE_PERMISSIONS` ⊆ kunci `RoleCatalog::LABELS`.
  - `it('ASSIGNABLE tidak memuat peran reviewer')`.

  `UserPolicyTest`, satu `it` per baris: pemegang `users.verify` → viewAny true, deactivate false. Pemegang `users.manage` → semua true atas user lain, false atas dirinya, dan false atas pemegang role `Super Admin`. Peneliti → semuanya false. `InvitationPolicy::manage`: `users.verify` → true, `users.manage` → true, `reviewers.assign` saja → false, peneliti → false.
- [ ] **Step 2:** Jalankan `./vendor/bin/pest tests/Unit/Architecture/IdentityCatalogTest.php tests/Feature/Identity/UserPolicyTest.php` (container). Expected: FAIL (kelas belum ada).
- [ ] **Step 3:** Implementasikan `RoleCatalog`, `PermissionCatalog`, `UserPolicy`, registrasi policy, pemindahan `ROLE_LABELS`, pemindahan `ActionDialog.vue` dan import-nya.
- [ ] **Step 4:** Jalankan test di atas + `tests/Feature/Announcement` + `npx vitest run tests/frontend/pages/ManageShowPage.spec.ts`. Expected: PASS.
- [ ] **Step 5: Commit** `feat(be): katalog label peran dan permission serta UserPolicy`

### Task 2: `Identity/UserIndexPage` (FE)

**Files:**
- Create: `resources/js/types/identity.ts`, `resources/js/Pages/Identity/UserIndexPage.vue`, `resources/js/Components/Identity/UserTable.vue`, `resources/js/Components/Identity/AccountStatusBadges.vue`
- Test: `tests/frontend/pages/UserIndexPage.spec.ts`

**Interfaces:**
- Produces (TS, `types/identity.ts`): `type Option = FilterOption` (diimpor dari `@/types/submission-page`; status pengajuan memakai `SubmissionStatusView` dari `@/types/submission-status`). Ditambah:
  - `AccountStatus { active: boolean; verification: Option | null }`
  - `UserRowData { id: number; name: string; email: string; nidn: string | null; organization: string | null; roles: Option[]; status: AccountStatus; showUrl: string }`
  - `UserIndexPageProps { users: Paginated<UserRowData>; filters: { search: string; role: string; account: string; verification: string }; roleOptions: Option[]; accountOptions: Option[]; verificationOptions: Option[]; awaitingVerification: number; can: { verify: boolean; manageInvitations: boolean } }`
- Produces: `AccountStatusBadges.vue` (prop `status: AccountStatus`) dengan badge "Aktif"/"Nonaktif" ditambah badge verifikasi bila `verification !== null` (W49). Dipakai juga di Task 3.

- [ ] **Step 1: Tulis test yang gagal** (`UserIndexPage.spec.ts`, pola `ManageIndexPage.spec.ts`):
  - `it('menampilkan kolom Nama, Email, NIDN, Organisasi, Role, Status')` → baris berisi label peran dan dua badge untuk user pending.
  - `it('NIDN kosong tampil "—"')`.
  - `it('tombol "Antrian Verifikasi NIDN (2)" hanya bila can.verify')` → menaut `admin.verifications.index`.
  - `it('tombol "Undangan Registrasi" hanya bila can.manageInvitations')`.
  - `it('[IAM-DEACT-07] tidak ada aksi hapus maupun nonaktifkan di baris')` → tidak ada teks "Hapus"/"Nonaktifkan", hanya "Detail" yang menaut `showUrl`.
  - `it('mengubah filter Role memanggil router.get ke admin.users.index tanpa page')`.
  - `it('banner ketua pengajuan aktif tampil')`, dengan teks frame "User yang masih menjadi ketua pengajuan aktif tidak dapat dinonaktifkan sebelum kepemilikannya dialihkan.".
  - `it('EmptyState saat tidak ada user')`.
- [ ] **Step 2:** `npx vitest run tests/frontend/pages/UserIndexPage.spec.ts` → FAIL.
- [ ] **Step 3:** Implementasikan. PageHeader judul "Daftar User", subjudul "Semua user sistem — kelola status, role, dan verifikasi.". Toolbar: pencarian "Cari nama / email / NIDN...", `FilterSelect` Role/Status/Verifikasi (`useFilters('admin.users.index', …)`). Lalu `DataTable` + `PaginationBar`, dan banner di bawah tabel.
- [ ] **Step 4:** Test PASS; `npx vue-tsc --noEmit` bersih.
- [ ] **Step 5: Commit** `feat(fe): layar daftar user`

### Task 3: `Identity/UserShowPage` — baca, deaktivasi, hak akses langsung (FE)

Dialog ubah peran **tidak** di sini. Route `admin.users.roles.update` baru lahir di Task 5, dan `FrontendRouteNamesTest` harus hijau di setiap commit. Semua route yang ditaut task ini sudah ada: `admin.users.deactivate`, `admin.users.permissions.grant`/`revoke`, `admin.submissions.show`.

**Files:**
- Create: `resources/js/Pages/Identity/UserShowPage.vue`, `resources/js/Components/Identity/UserProfileSection.vue`, `UserAccessSection.vue`, `DeactivationPanel.vue`, `UserSubmissionHistory.vue`, `UserReviewHistory.vue`
- Modify: `resources/js/types/identity.ts`
- Test: `tests/frontend/pages/UserShowPage.spec.ts`

**Interfaces:**
- Produces (TS):
  - `UserDetailData { id: number; name: string; email: string; nidn: string | null; phone: string | null; organization: string | null; expertise: string | null; registeredAt: string; status: AccountStatus }`
  - `UserAccessData { roles: Option[]; directPermissions: Option[]; effectivePermissions: Option[]; assignableRoles: Option[]; grantablePermissions: Option[] }`
  - `UserSummaryData { activeLeadSubmissions: number; runningReviews: number; registeredAt: string }`
  - `DeactivationBlockerData { submissionId: number; title: string; status: SubmissionStatusView; hasActiveCoInvestigator: boolean; manageUrl: string | null }`
  - `UserSubmissionRowData { id: number; title: string; role: string; status: SubmissionStatusView; url: string | null }`
  - `UserReviewRowData { submissionId: number; title: string; assignmentStatus: Option; evaluationStatus: Option; assignedAt: string }`
  - `UserTab = 'profile' | 'submissions' | 'reviews'`
  - `UserShowPageProps { user: UserDetailData; access: UserAccessData; summary: UserSummaryData; deactivationBlockers: DeactivationBlockerData[]; submissions: UserSubmissionRowData[]; reviews: UserReviewRowData[]; tab: UserTab; can: { deactivate: boolean; changeRoles: boolean; managePermissions: boolean; appointReviewer: boolean } }`
- Mutasi:
  - `router.post(route('admin.users.deactivate', id))`
  - `router.post(route('admin.users.permissions.grant', id), { permission })`
  - `router.delete(route('admin.users.permissions.revoke', id), { data: { permission } })`

- [ ] **Step 1: Tulis test yang gagal** (`UserShowPage.spec.ts`):
  - `it('header: nama, "email · NIDN", badge status')`.
  - `it('tab Profil, Riwayat Submission (n), Riwayat Reviewer (n); tab awal dari prop tab')`. Tanpa tab "Log Aktivitas" (W55). `tab: 'reviews'` membuka Riwayat Reviewer; tautan "Riwayat" dari manajemen reviewer mendarat di sini.
  - `it('[IAM-DEACT-07] tidak ada aksi hapus; aksi yang tersedia Nonaktifkan bila can.deactivate')`.
  - `it('[SM-OWNER-03] penghalang deaktivasi menonaktifkan tombol dan menaut Alihkan Kepemilikan ke manageUrl')`. Banner berbunyi "{nama} masih menjadi ketua {n} pengajuan aktif. Alihkan kepemilikannya lebih dulu.".
  - `it('[SM-OWNER-04] penghalang tanpa co-investigator menyebut kekurangannya')`. Teksnya "Pengajuan \"{judul}\" belum punya co-investigator aktif, jadi belum ada calon penerima kepemilikan. Tambahkan co-investigator lebih dulu.".
  - `it('[IAM-DEACT-03] akun nonaktif: banner jejak tetap tersimpan, riwayat tetap tampil')`. Teksnya "Akun nonaktif. Data dan seluruh jejaknya tetap tersimpan; yang berhenti hanya kemampuan masuk dan menerima penugasan baru.". Tidak ada tombol "Aktifkan" (W48).
  - `it('Nonaktifkan membuka ConfirmDialog lalu router.post ke admin.users.deactivate')`.
  - `it('[IAM-PERM-03] hak akses efektif tampil sebagai gabungan')` → daftar `effectivePermissions` tampil, beserta catatan frame "Hak akses dari role dan hak akses langsung berdiri sendiri — mencabut salah satunya tidak mencabut yang lain. Perubahan role dan hak akses hanya dapat dilakukan oleh Admin.".
  - `it('[IAM-PERM-02][IAM-PERM-04] mencabut hak akses langsung memanggil router.delete dengan permission-nya')`. Tombol cabut hanya ada di `directPermissions`, tidak di `effectivePermissions`.
  - `it('menambah hak akses langsung memilih dari grantablePermissions lalu router.post')`.
  - `it('tanpa can.managePermissions tidak ada tombol tambah/cabut')`.
  - `it('riwayat submission menaut url bila ada, teks biasa bila null')`.
- [ ] **Step 2:** Vitest → FAIL.
- [ ] **Step 3:** Implementasikan. Tata letak `simpas-detail-split`: kiri berisi "Data Personal" + "Role & Hak Akses", kanan berisi "Ringkasan" (`Submission Aktif`, `Review Berjalan`, `Terdaftar`) + `DeactivationPanel`. Tab pindah lewat state lokal (tanpa kunjungan server).
- [ ] **Step 4:** Vitest PASS; `vue-tsc` bersih.
- [ ] **Step 5: Commit** `feat(fe): layar detail user`

### Task 4: Kontrak daftar & detail user, re-guard route baca, hapus legacy daftar/buat user (BE)

**Files:**
- Create: `app/Http/Controllers/Identity/UserController.php` (`index`, `show`), `app/Queries/Identity/UserDirectory.php`, `app/Queries/Identity/UserDetail.php`, `app/Queries/Identity/DeactivationBlockers.php`, `app/Support/Search/LikePattern.php` (`contains(string $term): string`, meng-escape `\`, `%`, `_`), `app/Data/View/Identity/{UserRowData,AccountStatusData,UserDetailData,UserAccessData,UserSummaryData,DeactivationBlockerData,UserSubmissionRowData,UserReviewRowData,UserAbilitiesData}.php`
- Modify:
  - `app/Actions/FormSubmission/DeactivateUser.php`: penghalang dibaca dari `DeactivationBlockers` lewat **Extract Class**, pesan tetap sama.
  - `routes/web.php`: hapus `admin.users.index/create/store` dari grup `can:users.manage`. Tambah grup `Route::middleware(['auth', 'can:viewAny,App\Models\User'])->prefix('admin')->name('admin.')` berisi `GET /users` → `users.index` dan `GET /users/{user}` → `users.show` (`->can('view','user')`). Path `GET /users/{user}` dipakai legacy `admin.users.edit`, jadi path `edit` diganti `GET /users/{user}/edit` sampai Task 5 menghapusnya.
  - `tests/Feature/Inertia/RepairedPagesTest.php` (hapus baris `admin.users.create`), `quality-legacy.txt`, `quality-budget.txt` (`max.legacy.files` turun sebanyak baris yang dihapus).
- Delete: `resources/js/Pages/Users/IndexPage.vue`, `Users/CreatePage.vue`, `Users/Components/*`, `app/Http/Requests/Admin/CreateUserRequest.php`, method `index/create/store` di `app/Http/Controllers/UserController.php` legacy.
- Test: `tests/Feature/Identity/UserDirectoryTest.php`, `tests/Feature/Identity/UserDetailTest.php`

**Interfaces:**
- Consumes: `RoleCatalog`, `PermissionCatalog`, `UserPolicy` (Task 1).
- Produces: `UserDirectory::run(array{search:string, role:string, account:string, verification:string} $filters): array{users: LengthAwarePaginator<int, UserRowData>, roleOptions: list<Option>, accountOptions: list<Option>, verificationOptions: list<Option>}`. Ketentuannya:
  - 20 per halaman, urut nama.
  - `role` ∈ kunci `RoleCatalog::LABELS`.
  - `account` ∈ `active`/`inactive` (`users.is_active`).
  - `verification` ∈ `pending`/`active`/`rejected` (`user_profiles.verification_status`).
  - Search mencocokkan nama, email, atau NIDN lewat `whereLike` + `LikePattern::contains`.
  - Nilai filter yang tak dikenal diabaikan.
- Produces: `AccountStatusData::of(User $user): self` (`active: bool`, `verification: ?Option`, null bila profil tidak ada **atau** status `active`; label dari `VerificationStatus::label()`). `UserRowData::fromModel(User): self` (`showUrl` = `admin.users.show`).
- Produces: props `Identity/UserIndexPage` = `UserIndexPageProps`. `awaitingVerification` = `UserProfile::awaitingVerification()->count()`. `can.verify` = actor `users.verify`. `can.manageInvitations` = `$actor->can('manage', InvitationToken::class)`.
- Produces: `DeactivationBlockers::run(User $user): Collection<int, FormSubmission>`. Isinya pengajuan `submitted_by` yang statusnya bukan Rejected/Withdrawn (`whereNotState`), dengan co_investigator aktif ter-eager-load. Ini definisi tunggal untuk `DeactivateUser` dan layar.
- Produces: `UserDetail::run(User $viewer, User $user, string $tab): array` dengan kunci = `UserShowPageProps`:
  - `tab` ∈ `UserTab`; nilai lain → `profile`.
  - `submissions`: pengajuan sebagai ketua atau anggota, induk saja. `url` = `admin.submissions.show` bila viewer `submissions.view-all` dan `is_submitted`, selain itu null.
  - `reviews`: semua `SubmissionReviewer` milik record reviewer user itu, termasuk `replaced`, urut terbaru.
  - `summary.activeLeadSubmissions` = jumlah `DeactivationBlockers`.
  - `summary.runningReviews` = penugasan `active` + `unsettledEvaluation`.
  - `access.effectivePermissions` = `EffectivePermissionNames::run` berlabel `PermissionCatalog`.
  - `access.grantablePermissions` = katalog minus direct milik user.
  - `access.assignableRoles` = `RoleCatalog::options(ASSIGNABLE)`.
  - `deactivationBlockers.manageUrl` = `admin.submissions.show` bila viewer `submissions.view-all`.
  - `can.*` dari `UserPolicy`; `can.appointReviewer` = viewer `reviewers.assign`.

- [ ] **Step 1: Tulis test yang gagal.** `UserDirectoryTest`:
  - `it('Operator pemegang users.verify membuka daftar user')` → 200, `component('Identity/UserIndexPage')`, `has('users.data')`, `has('can')`.
  - `it('pemegang users.manage membuka daftar user')` → 200.
  - `it('peneliti mendapat 403')`.
  - `it('filter account=inactive hanya memuat user nonaktif')`.
  - `it('filter verification=pending memuat akun pending dan tidak memuat akun undangan')` (IAM-REG-14: akun undangan `active` tanpa NIDN).
  - `it('filter role=reviewer_internal')`.
  - `it('pencarian NIDN sebagian dan karakter wildcard di-escape')` → `"0987"` menemukan NIDN `0987654321`, sedangkan `"%"` tidak mencocokkan semua baris.
  - `it('status verifikasi null untuk akun terverifikasi dan akun tanpa profil')`.
  - `it('route admin.users.create dan admin.users.store tidak ada lagi')`.

  `UserDetailTest`:
  - `it('kontrak detail user')` → `component('Identity/UserShowPage')` dengan kunci `user, access, summary, deactivationBlockers, submissions, reviews, tab, can`.
  - `it('Operator users.verify membuka detail, can.deactivate false')`.
  - `it('peneliti 403')`.
  - `it('[SM-OWNER-03] penghalang memuat pengajuan aktif dan manageUrl')`.
  - `it('[SM-OWNER-04] hasActiveCoInvestigator false bila tak ada co-investigator aktif')`.
  - `it('[IAM-DEACT-03] detail user nonaktif tetap terbuka dengan riwayatnya')`.
  - `it('riwayat reviewer memuat penugasan replaced')` (BR-REV-10).
  - `it('tab tak dikenal menjadi profile')`.
- [ ] **Step 2:** Jalankan → FAIL.
- [ ] **Step 3:** Implementasikan. Controller hanya membaca filter/tab → `Inertia::render(...)`. Hapus legacy dan barisnya di `quality-legacy.txt`.
- [ ] **Step 4:** Jalankan `tests/Feature/Identity tests/Feature/Submission/OwnershipTest.php tests/Feature/Inertia tests/Unit/Architecture` → PASS.
- [ ] **Step 5: Commit** `feat(be): kontrak daftar dan detail user dijaga permission DDD`

### Task 5: Action peran & hak akses beraudit, dialog ubah peran, hapus legacy sunting user (BE + FE)

**Files:**
- Create: `app/Actions/Identity/ChangeUserRoles.php`, `app/Actions/Identity/GrantDirectPermission.php`, `app/Actions/Identity/RevokeDirectPermission.php`, `app/Http/Requests/Identity/ChangeUserRolesRequest.php`, `app/Http/Requests/Identity/DirectPermissionRequest.php`, `resources/js/Components/Identity/ChangeRolesDialog.vue`
- Modify:
  - `app/Http/Controllers/Identity/UserController.php` (`updateRoles`).
  - `app/Http/Controllers/UserDeactivationController.php` dan `app/Http/Controllers/Identity/UserAccessController.php`: tanpa `abort_unless`, mutasi lewat Action.
  - `routes/web.php`: grup `can:viewAny,…` dari Task 4 mendapat `PUT /users/{user}/roles` → `users.roles.update` (`->can('changeRoles','user')`). `POST /users/{user}/deactivate` (`->can('deactivate','user')`) dan `POST`/`DELETE /users/{user}/permissions` (`->can('managePermissions','user')`) dipindah dari grup `auth`, dengan nama tetap. Hapus `admin.users.edit/update`.
  - `resources/js/Pages/Identity/UserShowPage.vue`: tombol "Ubah Peran" bila `can.changeRoles` → `ChangeRolesDialog`.
  - `tests/Feature/Identity/AccessAndAppointmentTest.php` (IAM-PERM-01/02/04 lewat Action), `tests/Feature/Http/SubmissionActionsHttpTest.php` (bila memakai `admin.users.deactivate` dengan aktor tanpa `users.manage`), `quality-legacy.txt`, `quality-budget.txt`.
- Delete: `resources/js/Pages/Users/EditPage.vue`, `app/Http/Controllers/UserController.php` legacy (seluruhnya), `app/Http/Requests/Admin/UpdateUserRequest.php`.
- Test: `tests/Feature/Identity/UserAccessActionsTest.php`, tambahan di `tests/frontend/pages/UserShowPage.spec.ts`.

**Interfaces:**
- Produces: `ChangeUserRoles::run(User $actor, User $user, list<string> $roles): User`. Hanya menyinkronkan irisan `RoleCatalog::ASSIGNABLE`: role lain milik user tidak tersentuh, dan role di luar ASSIGNABLE pada input ditolak `ValidationException('roles')`. Audit `'Peran user diubah'` dengan `AuditChange(old: ['roles'=>…], new: ['roles'=>…])`.
- Produces: `GrantDirectPermission::run(User $actor, User $user, string $permission): void` (audit `'Hak akses langsung diberikan'`) dan `RevokeDirectPermission::run(...)` (audit `'Hak akses langsung dicabut'`; hanya direct, BR-IAM-09). Keduanya menolak permission di luar `PermissionSeeder::PERMISSIONS` lewat `ValidationException('permission')`.
- Produces (FE): `ChangeRolesDialog.vue` (props `userId: number`, `roles: Option[]`, `assignableRoles: Option[]`, `v-model:open`). Isinya checkbox per `assignableRoles`, tercentang bila ada di `roles`, lalu `useForm({ roles }).put(route('admin.users.roles.update', userId))` di dalam `ActionDialog`. Role non-assignable milik user tampil sebagai teks "Tidak diubah di sini: …".

- [ ] **Step 1: Tulis test yang gagal.** `UserAccessActionsTest`:
  - `it('peran di luar ASSIGNABLE tidak tersentuh')` → user `reviewer_internal` + `Tenaga Kependidikan`, `ChangeUserRoles(['operator'])`, lalu ketiganya ada.
  - `it('peran reviewer di input ditolak')`.
  - `it('aksi atas diri sendiri ditolak')` → 403 di `users.roles.update` dan `users.deactivate`, tanpa perubahan.
  - `it('akun Super Admin tidak bisa disunting')` → 403.
  - `it('grant/revoke/change mengaudit')`.
  - `it('Operator tanpa users.manage mendapat 403 di deactivate, roles, permissions')`.
  - `it('route admin.users.edit dan admin.users.update tidak ada lagi')`.

  Ubah `[IAM-PERM-01]`, `[IAM-PERM-02]` (cabut role lewat `ChangeUserRoles` → `[]`), dan `[IAM-PERM-04]` di `AccessAndAppointmentTest` supaya memakai Action. Assertion-nya tetap. Vitest: `it('Ubah Peran hanya bila can.changeRoles dan mengirim roles tercentang')`.
- [ ] **Step 2:** Jalankan → FAIL.
- [ ] **Step 3:** Implementasikan. `updateRoles` = FormRequest → Action → `back()->with('success', 'Peran user diperbarui.')`. Hapus legacy dan barisnya di `quality-legacy.txt`.
- [ ] **Step 4:** Jalankan `tests/Feature/Identity tests/Feature/Http tests/Unit/Architecture` → PASS (termasuk `AuditCoverageTest` dan `[IAM-DEACT-07]`, yang memastikan `admin.users.deactivate` ada) + Vitest.
- [ ] **Step 5: Commit** `feat(be): aksi peran dan hak akses langsung beraudit`

### Task 5b: Deaktivasi berlaku seketika dan menutup penugasan baru (BE + FE kecil)

Semua isinya turunan BR yang **sudah ada**: BR-IAM-05 ("tidak bisa login", "tidak menerima penugasan baru", "penugasan berjalan tidak hilang, digantikan lewat BR-REV-10"), BR-SM-09/BR-IAM-10 ("Co-Investigator yang aktif"). Pemeriksaan 2026-11-02 menemukan empat celah:
- `is_active` hanya diperiksa `LoginRequest`. Sesi yang sudah login, termasuk cookie remember-me, tetap bisa bertindak setelah deaktivasi.
- `AssignReviewer`/`ReplaceReviewer` tidak menolak akun nonaktif. Test `[IAM-DEACT-04]` mengikat **penunjukan** (`AppointReviewer`), padahal skenarionya tentang **penugasan**.
- `TransferSubmissionOwnership` menerima co-investigator berakun nonaktif.
- `AddResearchMember` menerima user nonaktif (catatan tertunda S2b).

**Files:**
- Create: `app/Http/Middleware/EnsureAccountActive.php`, `app/Support/Identity/SessionRegistry.php`
- Modify:
  - `bootstrap/app.php`: `EnsureAccountActive` di-append ke grup `web`, sesudah sesi dan auth.
  - `app/Actions/FormSubmission/DeactivateUser.php`: setelah `is_active = false`, panggil `SessionRegistry::endAllFor($user)` dan putar `remember_token`.
  - `app/Actions/Review/AssignReviewer.php`: `guardAccountActive`, sebelum `guardTenure`.
  - `app/Actions/FormSubmission/TransferSubmissionOwnership.php` (`guardSuccessor` + `whereHas('userProfile.user', is_active)`).
  - `app/Queries/Submission/OwnershipCandidates.php`.
  - `app/Queries/Identity/DeactivationBlockers.php`: `hasActiveCoInvestigator` hanya menghitung akun aktif.
  - `app/Actions/FormSubmission/AddResearchMember.php`.
  - `app/Queries/Review/ManagedReviewers.php` + `app/Data/View/Submission/Manage/ManagedReviewerData.php` (`accountActive: bool`).
  - `resources/js/Components/Submission/Manage/ReviewersSection.vue`: badge "Akun nonaktif — perlu diganti" dengan tombol Ganti yang sudah ada.
  - `resources/js/Components/Identity/DeactivationPanel.vue`: dialog konfirmasi menyebut dampaknya.
  - `bdd/generic/identity/deactivation.feature`, `bdd/core/submission/ownership.feature`, `bdd/core/review/reassignment.feature`.
- Test: `tests/Feature/Identity/DeactivationEffectTest.php`, `tests/Feature/Identity/AccessAndAppointmentTest.php` (`[IAM-DEACT-04]` diikat ulang), `tests/frontend/components/Submission/ReviewersSection.spec.ts`, `UserShowPage.spec.ts`

**Interfaces:**
- Produces: `EnsureAccountActive::handle(Request, Closure)`. Bila `$request->user()?->is_active === false`, jalankan `Auth::guard('web')->logout()`, `session()->invalidate()`, dan `regenerateToken()`, lalu `redirect()->route('login')->with('error', 'Akun Anda dinonaktifkan. Hubungi LPPM bila ini keliru.')`. Inertia mengikuti 302 itu, sehingga klik atau partial reload berikutnya langsung mendarat di login. Tidak ada query tambahan, karena user sudah dimuat oleh guard.
- Produces: `SessionRegistry` (satu-satunya pembaca/penulis tabel `sessions`; driver `database` adalah konfigurasi produksi `.env.example`):
  - `endAllFor(User $user): int` menghapus baris `sessions.user_id` dan mengembalikan jumlahnya.
  - `activeCountFor(User $user): int` menghitung sesi dengan `last_activity` ≥ sekarang − `SESSION_LIFETIME`.
- `DeactivateUser`: entri audit `'Akun dinonaktifkan'` kini memuat `new: ['sessions_ended' => n]`.
- Dialog Nonaktifkan (FE) menyebut dampaknya dari `summary`: "{n} penugasan review berjalan akan tetap ada dan perlu diganti Operator" (`runningReviews`) dan "Sesi yang sedang login akan diakhiri". Ditambah satu baris tetap: "Pengajuan, evaluasi, dan riwayatnya tetap tersimpan."

- [ ] **Step 1: Tulis skenario + test yang gagal.** Skenario baru, semuanya @M3:
  - `[IAM-DEACT-09]` @BR-IAM-05 @OPERATOR-08-03 @denial: "Given \"Sari\" sedang login / When Operator menonaktifkan \"Sari\" / Then permintaan berikutnya dari \"Sari\" ditolak dan ia keluar dari aplikasi". Test-nya actingAs → deactivate → `get(route('dashboard'))` → redirect login, `assertGuest()`. Ditambah varian remember-me: cookie recaller lama tidak lagi masuk.
  - `[IAM-DEACT-10]` @BR-IAM-05 @BR-REV-10 @OPERATOR-03-02 @denial: "reviewer yang akunnya nonaktif tidak bisa menjadi pengganti". `ReplaceReviewer` → `ValidationException('reviewer_id')`, penugasan lama tetap `active`.
  - `[IAM-DEACT-11]` @BR-IAM-05 @BR-REV-10 @OPERATOR-08-04 @boundary: "penugasan berjalan milik reviewer yang dinonaktifkan tetap aktif sampai diganti, dan draf evaluasinya tetap terbaca". Penugasan masih `active`, `ManagedReviewers` memberi `accountActive=false`, dan `SubmittedEvaluationSheets`/draf masih terbaca Operator.
  - `[IAM-DEACT-12]` @BR-IAM-05 @RESEARCHER-02-08 @denial: "user nonaktif tidak bisa ditambahkan sebagai anggota penelitian".
  - `[SM-OWNER-07]` @BR-SM-09 @BR-IAM-05 @OPERATOR-02-04 @denial: "kepemilikan tidak bisa dialihkan ke co-investigator yang akunnya nonaktif". Kandidat tidak ditawarkan, Action menolak, dan `DeactivationBlockers` melaporkan `hasActiveCoInvestigator=false` bila satu-satunya co-investigator nonaktif (pesan SM-OWNER-04 muncul).

  Ikat ulang `[IAM-DEACT-04]`: reviewer ber-record **dalam masa tugas** yang akunnya nonaktif → `AssignReviewer::run` melempar `ValidationException('reviewer_id', 'Akun reviewer ini nonaktif, jadi tidak dapat ditugaskan.')`, dan tidak ada `SubmissionReviewer` baru. Test penunjukan lama tetap ada tanpa ID (`it('BR-IAM-05 — akun nonaktif tidak bisa ditunjuk sebagai reviewer')`). Ditambah `it('deaktivasi mengakhiri sesi database user itu saja')`. Vitest: badge "Akun nonaktif — perlu diganti" di `ReviewersSection`, dan dialog Nonaktifkan memuat kalimat dampak.
- [ ] **Step 2:** Jalankan → FAIL.
- [ ] **Step 3:** Implementasikan.
- [ ] **Step 4:** Jalankan `tests/Feature tests/Unit` (serial) + Vitest → PASS. Middleware menyentuh setiap request: semua test yang memakai user `is_active=false` sambil `actingAs` harus dibaca ulang. Yang mengharapkan aksi berhasil oleh akun nonaktif adalah bug yang kini tertangkap, bukan test yang perlu dilonggarkan.
- [ ] **Step 5: Commit** `feat(be): deaktivasi mengakhiri sesi dan menutup penugasan baru`

### Task 5c: Sesi aktif, kehadiran, dan "Akhiri Semua Sesi" — BR-IAM-21 (BE + FE)

**Pemilik, 2026-11-02: BR-IAM-21 ditambahkan ke DDD** dengan teks persis di bawah. Barisnya masuk ke tabel Business Rules `ddd/generic/02_identity_access.md` **di commit yang sama** dengan skenario dan test-nya, karena `BddCoverageTest` assertion 7 menolak BR tanpa skenario dan `uncovered.txt` berplafon 0. Naikkan `**Versi:**` dokumen itu 2.4 → 2.5 (sekali, di task DDD pertama yang mendarat):

> **BR-IAM-21** — Sesi login adalah keadaan akun yang dapat dibaca dan diakhiri. Pemegang `users.verify` atau `users.manage` melihat kapan seorang user terakhir aktif dan apakah ia sedang aktif. Yang dimaksud "sedang aktif" adalah ada sesi dengan aktivitas dalam 5 menit terakhir; datanya diturunkan dari sesi, bukan dari pelacakan halaman. Pemegang `users.manage` dapat mengakhiri seluruh sesi seorang user tanpa menonaktifkannya (perangkat hilang, kata sandi bocor), dan tindakan itu diaudit. Deaktivasi (BR-IAM-05) selalu mengakhiri seluruh sesi.

**Files:** `app/Support/Identity/SessionRegistry.php` (`lastActiveAt(User): ?CarbonImmutable`, `isOnline(User): bool`, `ONLINE_WINDOW_MINUTES = 5`), `app/Actions/Identity/EndUserSessions.php` (audit `'Sesi user diakhiri'`, `UserPolicy::endSessions` = `users.manage` ∧ bukan diri sendiri), route `POST admin/users/{user}/sessions/end` → `admin.users.sessions.end`, `UserRowData`/`UserDetailData` (`lastActiveAt`, `online`), `AccountStatusBadges.vue` (titik "Sedang aktif"), `UserShowPage` (tombol "Akhiri Semua Sesi" + `ConfirmDialog`), filter daftar user `presence=online`. Skenario `[IAM-SESSION-01..03]` (@BR-IAM-21): akhiri sesi tanpa menonaktifkan, user tetap bisa login lagi; "sedang aktif" hanya dalam jendela 5 menit; peneliti tidak melihat data kehadiran (403 / prop tidak dikirim).

- [ ] **Step 1: Tulis yang gagal.** Baris BR-IAM-21 di DDD, `[IAM-SESSION-01..03]` di `bdd/generic/identity/session.feature` (baru), dan test Pest-nya di `tests/Feature/Identity/UserSessionTest.php`. Test `SessionRegistry` menyisipkan baris `sessions` sendiri, karena driver test adalah `array`. Vitest: titik "Sedang aktif" dan tombol "Akhiri Semua Sesi" hanya bila `can.endSessions`.
- [ ] **Step 2:** Jalankan → FAIL.
- [ ] **Step 3:** Implementasikan.
- [ ] **Step 4:** Jalankan `tests/Feature/Identity tests/Unit/Bdd` + Vitest → PASS.
- [ ] **Step 5: Commit** `feat(be): sesi dan kehadiran user (BR-IAM-21)`


### Task 5d: Aktifkan kembali akun — BR-IAM-20 (BE + FE)

**Pemilik, 2026-11-02: BR-IAM-20 ditambahkan ke DDD** dengan teks persis di bawah, di commit yang sama dengan skenarionya (alasan yang sama dengan Task 5c). Tambahkan juga baris `UserReactivated` (Trigger: Operator/Admin mengaktifkan kembali user; Consumer: Notification) ke tabel Domain Events:

> **BR-IAM-20** — Akun nonaktif dapat **diaktifkan kembali** oleh pemegang wewenang yang sama dengan deaktivasi. Pengaktifan memulihkan kemampuan login. Ia tidak memulihkan apa pun yang sudah diserahkan selama nonaktif: kepemilikan yang sudah dialihkan tetap pada penerimanya (BR-SM-09), penugasan yang sudah `replaced` tetap riwayat (BR-REV-10), dan reviewer tetap hanya bisa ditugaskan dalam masa tugasnya (BR-REV-09). Peran, hak akses langsung, profil, dan status verifikasi tidak berubah karena deaktivasi tidak pernah menyentuhnya (BR-IAM-05). User yang bersangkutan diberi tahu (BR-IAM-15).

**Files:** `app/Actions/Identity/ReactivateUser.php` (audit `'Akun diaktifkan kembali'`, notifikasi `AccountReactivatedNotification` berkategori `Account` di `NotificationCategory`, tujuan `dashboard` di `NotificationDestination`), `UserPolicy::reactivate` (= aturan `deactivate`), route `POST admin/users/{user}/reactivate` → `admin.users.reactivate`, tombol "Aktifkan" di `DeactivationPanel` saat akun nonaktif. Skenario baru @BR-IAM-20:
- `[IAM-REACT-01]` @happy: akun yang diaktifkan bisa login lagi, peran dan data utuh, user menerima notifikasi.
- `[IAM-REACT-02]` @boundary: pengaktifan tidak mengembalikan kepemilikan yang sudah dialihkan maupun penugasan yang sudah `replaced`.
- `[IAM-REACT-03]` @boundary: reviewer yang diaktifkan dan masa tugasnya sudah lewat tetap tidak bisa ditugaskan sampai diperpanjang.
- `[IAM-REACT-04]` @denial: Operator tanpa `users.manage` tidak bisa mengaktifkan.
- `[IAM-REACT-05]` @boundary: mengaktifkan akun yang sudah aktif tidak mengubah apa pun dan tidak diaudit.

- [ ] **Step 1: Tulis yang gagal.** Baris BR-IAM-20 + event di DDD, skenario di `deactivation.feature`, dan test di `tests/Feature/Identity/ReactivationTest.php`. Kelas notifikasi baru otomatis diperiksa `NotificationCategoryTest`. Vitest: tombol "Aktifkan" hanya pada akun nonaktif dengan `can.reactivate`, lewat `ConfirmDialog`. Assertion "tidak ada tombol Aktifkan" di test `[IAM-DEACT-03]` Task 3 diganti "tanpa `can.reactivate` tidak ada tombol Aktifkan". `[IAM-DEACT-07]` tetap hijau (tidak ada aksi hapus).
- [ ] **Step 2:** Jalankan → FAIL.
- [ ] **Step 3:** Implementasikan.
- [ ] **Step 4:** Jalankan `tests/Feature tests/Unit` (serial) + Vitest → PASS.
- [ ] **Step 5:** `composer run quality` dan `npm run quality` (container). Laporkan apa adanya.
- [ ] **Step 6: Commit** `feat(be): pengaktifan kembali akun (BR-IAM-20)`. **Akhir I1-1:** perbarui memory hand-off, sarankan session baru untuk I1-2.

---

## I1-2 — Verifikasi, undangan, reviewer, penutup

### Task 6: `Identity/VerificationIndexPage` — verifikasi & tolak (FE)

Dialog Koreksi NIDN menyusul di Task 7 bersama route-nya (`FrontendRouteNamesTest`).

**Files:**
- Create: `resources/js/Pages/Identity/VerificationIndexPage.vue`, `resources/js/Components/Identity/VerificationCard.vue`, `RejectVerificationDialog.vue`
- Modify: `resources/js/types/identity.ts`
- Test: `tests/frontend/pages/VerificationIndexPage.spec.ts`

**Interfaces:**
- Produces (TS):
  - `VerificationCardData { profileId: number; name: string; email: string; nidn: string | null; organization: string | null; registeredAt: string; rejectedAt: string | null; rejectionReason: string | null; files: { label: string; url: string }[] }`
  - `VerificationIndexPageProps { pending: VerificationCardData[]; rejected: VerificationCardData[] }`
- Produces: `VerificationCard.vue` punya slot `actions` supaya Task 7 bisa menambah tombol "Koreksi NIDN" tanpa menyunting kartu.
- Mutasi: `router.post(route('admin.verifications.verify', profileId))` dan `useForm({ reason }).post(route('admin.verifications.reject', profileId))`.

- [ ] **Step 1: Tulis test yang gagal**:
  - `it('header "Antrian Verifikasi NIDN" dengan badge "{n} menunggu" dan tombol kembali "Daftar User"')`.
  - `it('kartu pending: nama, email, "NIDN: … · organisasi · daftar tanggal", tombol Verifikasi dan Tolak')`.
  - `it('[IAM-VERIFY-07] Tolak membuka dialog berlabel "Alasan penolakan" dengan hint "Dikirimkan ke pendaftar dan tersimpan sebagai jejak keputusan." lalu post reason')`.
  - `it('[IAM-VERIFY-08] tombol "Tolak Pendaftaran" nonaktif selama alasan kosong atau spasi')`.
  - `it('[IAM-VERIFY-10] kartu ditolak: badge Ditolak, "ditolak {tanggal}", banner "Alasan penolakan sebelumnya: …", tombol Verifikasi, tanpa Tolak')`.
  - `it('[IAM-REG-14] banner jalur undangan tampil')`. Ini salah satu dari dua banner frame, yang berbunyi "Menolak pendaftaran wajib menyertakan alasan …" dan "Antrian ini hanya memuat pendaftar jalur mandiri. …".
  - `it('berkas biodata tampil sebagai tautan unduh bila ada')`.
  - `it('EmptyState saat antrian kosong')`.
- [ ] **Step 2:** Vitest → FAIL.
- [ ] **Step 3:** Implementasikan. Lebar `simpas-form-narrow`. Bagian "Ditolak" di bawah antrian, hanya bila ada isinya. Dialog memakai `ActionDialog` + `ReasonField` (S4).
- [ ] **Step 4:** Vitest PASS; `vue-tsc` bersih.
- [ ] **Step 5: Commit** `feat(fe): layar antrian verifikasi NIDN`

### Task 7: Kontrak verifikasi, koreksi NIDN, berkas biodata, "Ditolak pada" (BE + dialog koreksi)

**Files:**
- Create: `app/Queries/Identity/VerificationQueue.php`, `app/Queries/Identity/BiodataFiles.php`, `app/Queries/Identity/RejectedAt.php` (subquery entri audit terbaru), `app/Actions/Identity/CorrectNidn.php`, `app/Http/Requests/Identity/CorrectNidnRequest.php`, `app/Http/Requests/Identity/RejectVerificationRequest.php`, `app/Http/Controllers/Identity/VerificationFileController.php`, `app/Data/View/Identity/VerificationCardData.php`, `resources/js/Components/Identity/CorrectNidnDialog.vue`
- Modify:
  - `app/Http/Controllers/Identity/NidnVerificationController.php`: index lewat Query, reject lewat FormRequest, method `correctNidn`. `authorizeVerify()` dihapus dan diganti middleware.
  - `app/Actions/Identity/RejectNidnVerification.php`: konstanta `AUDIT_DESCRIPTION = 'Verifikasi NIDN ditolak'`.
  - `app/Data/View/AccountVerificationData.php` (`rejectedAt: ?string`) dan `resources/js/Pages/Auth/VerificationStatusPage.vue` + tipenya: baris "Ditolak pada" pada state penolakan SHARED-01-05 (W5/W53).
  - `resources/js/Pages/Identity/VerificationIndexPage.vue`: tombol "Koreksi NIDN" di slot `actions` kartu ditolak.
  - `routes/web.php`: verifikasi pindah ke grup baru `Route::middleware(['auth','can:users.verify'])->prefix('admin')->name('admin.')` dengan `verifications.index`, `verifications.verify`, `verifications.reject`, `POST /verifications/{profile}/nidn` → `verifications.nidn`, dan `GET /verifications/{profile}/files/{response}` → `verifications.files.show`.
  - `docs/requirement-analysis/bdd/generic/identity/verification.feature` (IAM-VERIFY-13/14), `tests/Feature/Identity/NidnVerificationTest.php`.
- Delete: `resources/js/Pages/Identity/VerificationQueuePage.vue` (+ barisnya di `quality-legacy.txt`, `max.legacy.files`)
- Test: `tests/Feature/Identity/NidnVerificationTest.php`, `tests/Feature/Identity/VerificationQueueContractTest.php`, `tests/Feature/Identity/VerificationFileTest.php`, tambahan di `tests/frontend/pages/VerificationIndexPage.spec.ts`

**Interfaces:**
- Produces: `CorrectNidn::run(User $actor, UserProfile $profile, string $nidn): UserProfile`. Langkahnya:
  1. trim.
  2. Kosong → `ValidationException('nidn')`.
  3. Dipakai profil lain → `ValidationException('nidn', 'NIDN ini sudah terdaftar pada akun lain.')` (BR-IAM-02).
  4. Sama dengan yang sekarang → return tanpa audit.
  5. Update `nidn`, lalu audit `'NIDN dikoreksi'` dengan `AuditChange(old: ['nidn'=>…], new: ['nidn'=>…])`.

  Status tidak berubah. Tanpa notifikasi (W52).
- Produces: `BiodataFiles::run(User $user): list<array{label:string,url:string}>`. Sumbernya submission biodata terakhir user (`form_type_id = FormType::BIODATA_ID`) dengan field bertipe `file` yang terisi. Label = label field, url = `admin.verifications.files.show`.
- Produces: `VerificationQueue::run(): array{pending: list<VerificationCardData>, rejected: list<VerificationCardData>}`. Isinya pending (`awaitingVerification`, urut `created_at`) dan rejected (urut `rejectedAt` terbaru). `rejectedAt` lewat `RejectedAt::subquery()`. Berkas di-eager-load sekali, tanpa N+1.
- `VerificationFileController::show(UserProfile $profile, FormFieldResponse $response)` → 404 kecuali response milik submission biodata `profile->user_id`, bertipe `file`, dan ada di disk privat (`PrivateUploadDisk::NAME`). Otorisasi lebih dulu (middleware), sama seperti `SubmissionFileController`.
- Produces (FE): `CorrectNidnDialog.vue` (props `profileId`, `currentNidn`, `v-model:open`): `useForm({ nidn }).post(route('admin.verifications.nidn', profileId))`, menampilkan `form.errors.nidn`.

- [ ] **Step 1: Tulis test yang gagal.**
  - Tulis dua skenario baru di `verification.feature` (teks di bagian "Kesesuaian").
  - `it('[IAM-VERIFY-13] Operator mengoreksi NIDN akun yang ditolak')`.
  - `it('[IAM-VERIFY-14] koreksi ke NIDN yang sudah dipakai ditolak')`.
  - `[IAM-VERIFY-10]` menjadi `CorrectNidn` lalu `VerifyNidn` → `active`, alasan null.
  - `[IAM-VERIFY-01] Operator membuka antrian verifikasi` → `component('Identity/VerificationIndexPage')` dengan `has('pending', 1)` dan `where('pending.0.profileId', …)`.

  `VerificationQueueContractTest`:
  - `it('rejectedAt dari entri audit penolakan terbaru')`.
  - `it('[IAM-REG-14] akun undangan tidak masuk pending maupun rejected')`.
  - `it('pemegang users.manage tanpa users.verify 403')`.
  - `it('akun ditolak membaca rejectedAt di layar status')` (`verification.status` → `verification.rejectedAt`).

  `VerificationFileTest`:
  - `it('Operator mengunduh berkas biodata pendaftar')`.
  - `it('berkas milik profil lain 404')`.
  - `it('jawaban bukan file 404')`.
  - `it('peneliti 403')`.

  Vitest: `it('Koreksi NIDN hanya di kartu ditolak, memposting nidn, dan menampilkan form.errors.nidn')`.
- [ ] **Step 2:** Jalankan → FAIL.
- [ ] **Step 3:** Implementasikan, termasuk route. Hapus legacy. Turunkan `max.legacy.files`.
- [ ] **Step 4:** Jalankan `tests/Feature/Identity tests/Feature/Auth tests/Feature/Dashboard tests/Feature/Notification tests/Unit` + `npx vitest run tests/frontend/pages/VerificationIndexPage.spec.ts tests/frontend/pages/LoginPage.spec.ts` → PASS (`BddCoverageTest` menemukan kedua ID baru).
- [ ] **Step 5:** `composer run quality` dan `npm run quality` (container). Laporkan apa adanya.
- [ ] **Step 6: Commit** `feat(be): kontrak verifikasi dengan koreksi NIDN dan berkas biodata`

### Task 8: `Identity/InvitationIndexPage` (FE)

**Files:**
- Create: `resources/js/Pages/Identity/InvitationIndexPage.vue`, `resources/js/Components/Identity/InvitationForm.vue`, `InvitationTable.vue`, `IssuedInvitationLink.vue`
- Modify: `resources/js/types/identity.ts`, `resources/js/types/global.d.ts` (`flashDataType.issuedInvitationUrl?: string`)
- Test: `tests/frontend/pages/InvitationIndexPage.spec.ts`

**Interfaces:**
- Produces (TS):
  - `InvitationRowData { id: number; organization: string | null; organizationId: number | null; preset: Option | null; usedCount: number; maxUses: number | null; remainingUses: number | null; expiresAt: string | null; status: Option; revocable: boolean }`. Nilai status: `valid`·`revoked`·`organization_inactive`·`exhausted`·`expired`.
  - `InvitationIndexPageProps { invitations: InvitationRowData[]; organizationOptions: Option[]; presetOptions: Option[] }`
- Mutasi: `useForm({ organization_id, preset, max_uses, expires_at }).post(route('admin.invitations.store'))` dan `router.delete(route('admin.invitations.revoke', id))`. Tautan dibaca dari `usePage().flash.issuedInvitationUrl`.

- [ ] **Step 1: Tulis test yang gagal**:
  - `it('form "Buat Undangan Baru": Organisasi Tujuan, Hak Akses, Maks. Pemakaian, Berlaku Sampai, tombol "Buat Undangan"')`.
  - `it('tautan undangan tampil sekali dari flash dengan tombol Salin Link')` → memanggil `navigator.clipboard.writeText` (mock).
  - `it('[IAM-REG-09] kolom "Terpakai / Kuota" berbunyi "3 / 5"; tanpa kuota "3 / ∞"')`.
  - `it('[IAM-INVITE-06] baris dicabut tetap tampil dengan badge Dicabut dan jumlah pemakaian')`.
  - `it('[IAM-REG-16][IAM-INVITE-04] Cabut membuka konfirmasi')`. Teks konfirmasinya "Mencabut undangan ini akan menghentikan {n} pemakaian yang tersisa. Akun yang sudah terlanjur dibuat tidak terpengaruh." Setelah konfirmasi, `router.delete` dipanggil.
  - `it('baris tidak valid menawarkan Buat Ulang yang mengisi form, tanpa Cabut')`.
  - `it('catatan kaki frame tampil')`, dengan teks "Undangan kedaluwarsa atau kuotanya habis otomatis menjadi Tidak Valid …". Ubah "Tidak Valid" sesuai label status bila perlu, tanpa mengubah makna.
- [ ] **Step 2:** Vitest → FAIL.
- [ ] **Step 3:** Implementasikan. PageHeader "Undangan Registrasi" dengan subjudul frame. IAM-INVITE-02/05 ditegakkan backend (Task 9 403); halaman tidak menyembunyikan apa pun berdasarkan peran.
- [ ] **Step 4:** Vitest PASS; `vue-tsc` bersih.
- [ ] **Step 5: Commit** `feat(fe): layar undangan registrasi`

### Task 9: Kontrak undangan, preset hak akses, label permission di registrasi undangan (BE)

**Files:**
- Create: `app/Enums/InvitationPreset.php`, `app/Enums/InvitationStatus.php`, `app/Queries/Identity/InvitationList.php`, `app/Data/View/Identity/InvitationRowData.php`, `app/Http/Requests/Identity/IssueInvitationRequest.php`
- Modify:
  - `app/Http/Controllers/Identity/InvitationController.php`: tipis, tanpa `abort_unless`.
  - `app/Http/Controllers/Auth/RegisteredUserController.php`: `permissions` dikirim sebagai label `PermissionCatalog`, W4.
  - `resources/js/Pages/Auth/RegisterInvitationPage.vue`: tetap `join(', ')`, isinya kini label.
  - `routes/web.php`: tiga route `admin.invitations.*` pindah ke `Route::middleware(['auth','can:manage,App\Models\InvitationToken'])`.
  - `app/Actions/Identity/IssueInvitation.php` dan `RevokeInvitation.php`: guard `$actor->can('users.manage')` diganti `$actor->can('manage', InvitationToken::class)` (pesan "Hanya Operator …" tetap).
  - `docs/requirement-analysis/bdd/generic/identity/invitation.feature`: skenario `[IAM-INVITE-09]` @BR-IAM-16 @M3 @ADMIN-08-01 @happy: "Given \"Sari\" berperan Operator tanpa permission \"users.manage\" / When Sari menerbitkan undangan untuk \"Mitra Industri\" lalu mencabutnya / Then undangan tersimpan dan kemudian berhenti berlaku".
  - `tests/Feature/Identity/InvitationLifecycleTest.php`.
- Delete: `resources/js/Pages/Identity/InvitationsPage.vue` (+ `quality-legacy.txt`, `max.legacy.files`)
- Test: `tests/Feature/Identity/InvitationScreenTest.php`

**Interfaces:**
- Produces: `enum InvitationPreset: string { case Researcher = 'researcher'; }`, dengan `label()` = "Membuat & mengelola pengajuan", `permissions(): list<string>` = `PermissionSeeder::ROLE_PERMISSIONS['researcher']`, `static options(): list<Option>`, dan `static fromPermissions(list<string>): ?self` (cocok persis setelah diurutkan; null = token lama/kustom → tampil "—").
- Produces: `enum InvitationStatus: string` dengan nilai `valid`/`revoked`/`organization_inactive`/`exhausted`/`expired`. `label()` berturut-turut "Valid", "Dicabut", "Organisasi Nonaktif", "Kuota Habis", "Kedaluwarsa". `static of(InvitationToken): self` memakai urutan prioritas sesuai daftar itu; `valid` hanya bila `isValid()`.
- Produces: `IssueInvitationRequest`:
  - `organization_id`: required, exists di organisasi aktif.
  - `preset`: required, `Rule::enum(InvitationPreset::class)`.
  - `max_uses`: required integer min:1.
  - `expires_at`: required date after_or_equal:today.

  Controller meneruskan `permissions = preset->permissions()` dan `expires_at = Carbon::parse(...)->endOfDay()` ke `IssueInvitation` yang sudah ada (signature tidak berubah). Lalu `Inertia::flash('issuedInvitationUrl', route('register.invitation.show', $token))` dan `back()->with('success', 'Undangan diterbitkan.')`.
- Produces: props `Identity/InvitationIndexPage` = `InvitationIndexPageProps`. `remainingUses` = `max(0, max_uses - used_count)` atau null. `revocable` = `revoked_at === null`.

- [ ] **Step 1: Tulis test yang gagal** (`InvitationScreenTest`):
  - `it('kontrak layar undangan')`.
  - `it('[IAM-INVITE-02] peneliti 403 di index dan store')`.
  - `it('[IAM-INVITE-05] peneliti 403 di revoke')`.
  - `it('[IAM-INVITE-09] peran operator (users.verify) menerbitkan dan mencabut undangan')` → lewat HTTP: 302 + token tersimpan, lalu revoke → `revoked_at` terisi.
  - `it('pemegang reviewers.assign saja 403')` (W47).
  - `it('status tiap sebab')` (lima dataset).
  - `it('[IAM-INVITE-06] undangan dicabut tetap terbaca dengan used_count')`.
  - `it('permission di luar preset diabaikan')` → post dengan `permissions[]=users.manage` menghasilkan token berisi tepat permission peran researcher.
  - `it('preset tak dikenal ditolak')` → 422/`assertSessionHasErrors('preset')`.
  - `it('expires_at disimpan pukul 23:59:59')`.
  - `it('tautan undangan dikirim sebagai flash dan tidak ada di props daftar')`.
  - `it('registrasi undangan menampilkan label permission')` (`register.invitation.show` → `permissions` berisi "Membuat pengajuan").

  Ubah test `InvitationLifecycleTest` yang memanggil `component('Identity/InvitationsPage')` menjadi `Identity/InvitationIndexPage`.
- [ ] **Step 2:** Jalankan → FAIL.
- [ ] **Step 3:** Implementasikan; hapus legacy.
- [ ] **Step 4:** Jalankan `tests/Feature/Identity tests/Feature/Auth tests/Unit` → PASS.
- [ ] **Step 5: Commit** `feat(be): kontrak undangan dengan preset hak akses`

### Task 10: `Identity/ReviewerIndexPage` + dialog penunjukan (FE)

**Files:**
- Create: `resources/js/Pages/Identity/ReviewerIndexPage.vue`, `resources/js/Components/Identity/ReviewerTable.vue`, `AppointReviewerDialog.vue`, `ReviseAppointmentDialog.vue`, `resources/js/composables/usePartialSearch.ts`
- Modify:
  - `resources/js/composables/useReviewerCandidates.ts`: menjadi pembungkus `usePartialSearch('reviewerCandidates', 'reviewer_search')` lewat **Parameterize Method**, perilaku sama.
  - `resources/js/Pages/Identity/UserShowPage.vue`: tombol "Tunjuk sebagai Reviewer" bila `can.appointReviewer`, membuka `AppointReviewerDialog` dengan user terkunci.
  - `resources/js/types/identity.ts`.
- Test: `tests/frontend/pages/ReviewerIndexPage.spec.ts`, `tests/frontend/composables/usePartialSearch.spec.ts`, tambahan di `UserShowPage.spec.ts`

**Interfaces:**
- Produces (TS):
  - `ReviewerStanding = 'active' | 'not_started' | 'ended' | 'account_inactive'`
  - `ReviewerRowData { id: number; userId: number; name: string; type: Option; tenureStart: string; tenureEnd: string | null; runningAssignments: number; standing: { value: ReviewerStanding; label: string }; historyUrl: string | null }`
  - `AppointmentCandidateData { userId: number; name: string; email: string; nidn: string | null; organization: string | null; currentReviewer: { type: Option; tenureStart: string; tenureEnd: string | null; standing: Option } | null }`
  - `ReviewerIndexPageProps { reviewers: Paginated<ReviewerRowData>; filters: { search: string; type: string; standing: string }; typeOptions: Option[]; standingOptions: Option[]; appointmentCandidates?: AppointmentCandidateData[]; can: { manageInvitations: boolean } }`
- Produces: `usePartialSearch(only: string, param: string): { search: Ref<string>; load: () => void }`.
- Mutasi:
  - `useForm({ user_id, reviewer_type, start_date, end_date }).post(route('admin.reviewers.store'))`
  - `useForm({ reviewer_type, start_date, end_date }).put(route('admin.reviewers.update', id))`

  Di `UserShowPage`, dialog yang sama memakai `user_id` tetap dan tanpa pencarian.

- [ ] **Step 1: Tulis test yang gagal**:
  - `it('header "Manajemen Reviewer", tombol "Tambah Reviewer", kolom Nama, Tipe, Periode Aktif, Beban Tugas, Status')`.
  - `it('aksi baris menurut standing')`: active/not_started → "Edit Periode", ended → "Perpanjang", account_inactive → tanpa aksi ubah. "Riwayat" ada hanya bila `historyUrl`.
  - `it('[REV-ASSIGN-08] Perpanjang pada reviewer berakhir menampilkan banner masa tugas berakhir')`, dengan teks "Masa tugas {nama} sudah berakhir. Perpanjang lebih dulu sebelum menugaskannya.".
  - `it('[IAM-DEACT-04] reviewer akun nonaktif berbadge "Akun Nonaktif"')`.
  - `it('[IAM-REVIEWER-03] pencarian tanpa hasil menampilkan EmptyState "Tidak ada user dengan nama itu" dan "Buat Undangan Registrasi" bila can.manageInvitations')`.
  - `it('[IAM-REVIEWER-04] kandidat yang sudah reviewer memunculkan banner pembaruan')`, dengan teks "{nama} sudah menjadi reviewer. Menyimpan akan memperbarui tipe dan masa tugasnya, bukan menambah entri baru.".
  - `it('[IAM-REVIEWER-05] mengubah tipe internal → eksternal memunculkan banner pencabutan skor')`, dengan teks "Mengubah tipe menjadi Eksternal akan mencabut kemampuan melihat skor reviewer lain. Evaluasi yang sudah ditulis tidak berubah.".
  - `it('banner bawah tabel frame tampil')`.
  - `usePartialSearch.spec.ts` meniru `useReviewerCandidates.spec.ts` (debounce 300 ms, `only`, `data`).
  - `UserShowPage.spec.ts`: `it('Tunjuk sebagai Reviewer hanya bila can.appointReviewer')`.
- [ ] **Step 2:** Vitest → FAIL.
- [ ] **Step 3:** Implementasikan. `AppointReviewerDialog` memakai `ActionDialog`, dengan kandidat dimuat lewat `usePartialSearch('appointmentCandidates','candidate_search')`.
- [ ] **Step 4:** Vitest PASS (termasuk `useReviewerCandidates.spec.ts` lama); `vue-tsc` bersih.
- [ ] **Step 5: Commit** `feat(fe): layar manajemen reviewer`

### Task 11: Kontrak manajemen reviewer, re-guard `reviewers.assign`, hapus legacy reviewer (BE)

**Files:**
- Create: `app/Http/Controllers/Identity/ReviewerManagementController.php` (`index`, `store`, `update`), `app/Queries/Identity/AppointmentCandidates.php`, `app/Actions/Identity/ReviseReviewerAppointment.php`, `app/Enums/ReviewerStanding.php`, `app/Data/View/Identity/{ReviewerRowData,AppointmentCandidateData}.php`, `app/Http/Requests/Identity/{AppointReviewerRequest,ReviseAppointmentRequest}.php`
- Modify:
  - `app/Queries/Review/ReviewerDirectory.php`: mengembalikan `LengthAwarePaginator<int, ReviewerRowData>`, filter `standing` menggantikan `status`, eager-load `user.userProfile`, `withCount` penugasan berjalan, search lewat `LikePattern`.
  - `routes/web.php`: hapus `Route::resource('reviewers')`, `reviewers.activate/deactivate`, `admin.users.appoint-reviewer`. Tambah `Route::middleware(['auth','can:reviewers.assign'])->prefix('admin')->name('admin.')` berisi `GET /reviewers` → `reviewers.index`, `POST /reviewers` → `reviewers.store`, `PUT /reviewers/{reviewer}` → `reviewers.update`.
  - `app/Http/Controllers/Identity/UserAccessController.php`: hapus `appointReviewer`.
  - `tests/Feature/Identity/ReviewerManagementTest.php` dan `AccessAndAppointmentTest.php`.
- Delete: `app/Http/Controllers/ReviewerController.php`, `app/Actions/Identity/DismissReviewer.php` (W50) beserta test-nya, `resources/js/Pages/Reviewers/*` (+ `quality-legacy.txt`, `max.legacy.files`)
- Test: `tests/Feature/Identity/ReviewerManagementTest.php` (ditulis ulang ke route baru), `tests/Feature/Identity/AppointmentCandidatesTest.php`

**Interfaces:**
- Consumes: `AppointReviewer`, `ChangeReviewerTenure` (tidak berubah), `LikePattern` (Task 4), `UserPolicy::viewAny` (untuk `historyUrl`).
- Produces: `enum ReviewerStanding: string` dengan nilai `active`/`not_started`/`ended`/`account_inactive`. `label()` berturut-turut "Aktif", "Belum Mulai", "Berakhir", "Akun Nonaktif". `static of(Reviewer): self` menempatkan `account_inactive` lebih dulu (BR-IAM-05), lalu `isWithinTenure()` → `active`, `start_date` di masa depan → `not_started`, selebihnya `ended`. Ditambah `static scope(Builder<Reviewer>, self): void` untuk filter.
- Produces: `ReviseReviewerAppointment::run(Reviewer $reviewer, string $type, string $start, ?string $end): Reviewer`. Isinya logika `ReviewerController::update` lama yang dipindah apa adanya lewat **Extract Method**: transaksi `ChangeReviewerTenure`, lalu `AppointReviewer` bila tipe berubah.
- Produces: `AppointmentCandidates::run(string $search): list<AppointmentCandidateData>`. Isinya user aktif ber-profil yang cocok nama/email/NIDN (minimal 2 karakter, kurang dari itu → `[]`), maks 20, urut nama, `currentReviewer` dari record reviewer bila ada.
- Produces:
  - `AppointReviewerRequest`: `user_id` required exists:users,id; `reviewer_type` in internal,external; `start_date` required date; `end_date` required date after_or_equal:start_date.
  - `ReviseAppointmentRequest`: sama, tanpa `user_id`.

  Controller `index` merender `Identity/ReviewerIndexPage` dengan `appointmentCandidates` = `Inertia::optional(fn () => AppointmentCandidates::run($request->string('candidate_search')->trim()->toString()))`, dan `historyUrl` = `admin.users.show` + `?tab=reviews` bila viewer lolos `viewAny`. `store`/`update` → Action → `back()->with('success', …)`.

- [ ] **Step 1: Tulis test yang gagal.** Tulis ulang `ReviewerManagementTest` ke route baru. Pertahankan setiap aturan yang masih punya jalur: role reviewer_internal, record sama untuk masa tugas habis, akun nonaktif ditolak, notifikasi + audit, ganti tipe mencabut role lama, audit masa tugas, duplikat record ditolak, masa tugas inklusif, belum mulai = `not_started`, dan kedua test `auth.user.is_reviewer`. Hapus test destroy/activate/deactivate/show/create, yang tidak lagi punya jalur. Tambah:
  - `it('Operator pemegang reviewers.assign membuka manajemen reviewer')` → 200 + kontrak.
  - `it('peneliti 403')`.
  - `it('pemegang users.manage saja 403')` (permission DDD, bukan users.manage).
  - `it('[IAM-DEACT-04] reviewer akun nonaktif berstanding account_inactive')`.
  - `it('beban tugas menghitung penugasan aktif yang belum beres')`.
  - `[IAM-REVIEWER-03]` ditulis ulang: `post(route('admin.reviewers.store'), ['user_id' => 999999, …])` → `assertSessionHasErrors('user_id')` dan `Reviewer::count()` tetap.
  - `it('route destroy, activate, deactivate, appoint-reviewer tidak ada lagi')`.

  `AppointmentCandidatesTest`:
  - `it('kandidat dicari case-insensitive')`.
  - `it('akun nonaktif tidak ditawarkan')`.
  - `it('kandidat yang sudah reviewer membawa currentReviewer')`.
  - `it('pencarian kurang dari 2 karakter kosong')`.
- [ ] **Step 2:** Jalankan → FAIL.
- [ ] **Step 3:** Implementasikan; hapus legacy.
- [ ] **Step 4:** Jalankan `tests/Feature/Identity tests/Feature/Review tests/Unit` → PASS.
- [ ] **Step 5: Commit** `feat(be): kontrak manajemen reviewer dijaga reviewers.assign`

### Task 12: Catat keputusan, gate penuh, cek browser

**Files:** `docs/design/fe-v2-design.md` (§8 I1-1 dan I1-2 → ☑ dengan rujukan rencana ini; §9 W47–W58, plus catatan "W4/W5 ditutup I1"), memory hand-off.

- [ ] **Step 1:** Tulis W47–W60 ke §9, isinya dari bagian Keputusan di atas, termasuk keputusan pemilik 2026-11-02 (W47 Operator menerbitkan undangan; BR-IAM-20/21 ditambahkan ke DDD; Reverb = RT1). Pastikan `KNOWN_BROKEN_FRONTEND_ROUTES` tidak bertambah dan legacy `Components/AppSidebar.vue` hanya menaut route yang masih ada (`admin.users.index`, `admin.reviewers.index`).
- [ ] **Step 2:** Jalankan `composer run quality` dan `npm run quality` (container). Laporkan hasilnya apa adanya.
- [ ] **Step 3: Cek browser** (resep memory: `simpas_browser`, `php -S` dari `public/`, Playwright).
  - Operator: daftar user → filter Verifikasi=Menunggu → antrian → tolak tanpa alasan (tombol nonaktif) → tolak dengan alasan → koreksi NIDN → verifikasi.
  - Admin: detail user ketua pengajuan aktif → Nonaktifkan nonaktif + tautan Alihkan Kepemilikan; user tanpa pengajuan → nonaktifkan lewat dialog → badge Nonaktif; ubah peran; cabut hak akses langsung.
  - Admin: terbitkan undangan → salin tautan → Back tidak menampilkan tautan lagi → cabut.
  - Operator: Tambah Reviewer (cari, pilih, simpan) → Edit Periode internal→eksternal dengan banner IAM-REVIEWER-05.
- [ ] **Step 4: Commit** `feat(fe): irisan I1 identity selesai`, lalu sarankan session baru dengan irisan berikutnya (P1).
