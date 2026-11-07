# FE v2 — Irisan C1-3: Wewenang Organisasi & Aktifkan Kembali Unit — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup dua pertanyaan pemilik dari C1-2 yang diputuskan 2026-11-07: **W105** — struktur organisasi dikelola permission baru `organizations.manage` (Admin + Operator), bukan `users.manage`; **W119** — unit nonaktif bisa diaktifkan kembali (BR-IAM-22 baru), per unit, beraudit, ditolak bila induknya masih nonaktif.

**Architecture:** Lanjutan C1-2 di layar `SystemConfig/OrganizationPage`. Pola sama: route group → FormRequest/Action beraudit → redirect; kelayakan tombol dibaca dari backend (`OrganizationUnitData`). Satu definisi untuk jumlah undangan yang berlaku lagi (`RevivableInvitations`), dipakai layar dan Action (**Extract Method → Query**). Pengaktifan memakai `$organization->update()` (bukan update massal) supaya model event `Organization::saved` membuang cache `subtreeIds`/`ancestorIds`.

**Tech Stack:** Laravel 13 / PHP 8.4, spatie/laravel-permission, lorisleiva/laravel-actions, spatie/laravel-data, Inertia + Vue 3 `<script setup>` + TS, Pest, Vitest. Tanpa dependency baru.

**Spec:** keputusan pemilik 2026-11-07 (percakapan, rekomendasi W105 opsi C dan W119 lima aturan) — dicatat ke `docs/design/fe-v2-design.md` §9 di Task 4. DDD `ddd/generic/02_identity_access.md` (BR-IAM-06, 14, 17, 18, 20; katalog permission + matriks peran; Domain Events). BDD `bdd/generic/identity/organization.feature`. Navigasi `ux/ia/00_navigation/sidebar.md`. Hasil C1-2: `docs/plans/2026-11-07-fe-c1-config.md` Task 5–8.

**Ukuran:** kecil, satu session, 4 task.

## Keputusan pemilik (2026-11-07) yang diikat rencana ini

- **W105 → C.** Permission baru `organizations.manage`, label "Mengelola struktur organisasi", diberikan ke peran `operator` dan `admin` (admin = seluruh katalog). Semua route organisasi (index + 6 route tulis) pindah ke `can:organizations.manage`. Pemegang `users.manage` saja → 403 di Organisasi. `users.manage` **tidak** diberikan ke Operator (membuka ubah peran, hak akses langsung, deaktivasi akun — eskalasi wewenang).
- **W119 → BR-IAM-22.** Teks BR (ditulis apa adanya ke DDD di Task 2):
  > Unit organisasi nonaktif dapat **diaktifkan kembali** oleh pemegang wewenang yang sama dengan deaktivasi ([BR-IAM-14](#business-rules)). Pengaktifan berlaku untuk **unit itu saja**: deaktivasi menular ke seluruh subtree, tetapi tidak tercatat turunan mana yang ikut nonaktif karena induknya dan mana yang memang dipensiunkan sendiri, jadi turunannya tetap nonaktif sampai diaktifkan satu per satu. Pengaktifan **ditolak** selama induknya masih nonaktif — unit aktif di bawah induk nonaktif tetap terputus dari rantai leluhur access control ([BR-FE-02](01_form_engine.md)), dan status aktifnya akan menipu. Undangan unit itu yang tidak dicabut, belum kedaluwarsa, dan belum habis kuotanya **berlaku lagi** (BR-IAM-06), dan jumlahnya disebut sebelum Operator mengonfirmasi. Pengaktifan diaudit dan tidak menghapus atau memulihkan apa pun selain status unit itu
- Unit yang sudah aktif: tidak ada keputusan, jadi tanpa perubahan dan tanpa audit (pola IAM-REACT-05 / `ReactivateUser`).

## Global Constraints

- PHP baru ≤ 200 baris, method ≤ 40 baris, CC ≤ 8, ≤ 4 parameter; Vue ≤ 250 baris. Tanpa blok duplikat 6 baris.
- Identifier Inggris; komentar Indonesia, maks 3 baris, sebut kode BR/W. TS tanpa `any`/`console.log`/cast. PHPStan tanpa ignore/baseline/`assert()`/inline `@var`/cast baru.
- Teks UI apa adanya dari rencana ini. Frontend tidak menghitung kelayakan (`canReactivate`, `revivableInvitations` dari backend).
- Setiap Action baru mengaudit lewat `AuditTrail::record` (`AuditCoverageTest`).
- Skenario baru ditulis di commit yang sama dengan test Pest-nya (BddCoverageTest); ID dicek dulu terhadap `docs/requirement-analysis/bdd` dan `tests` (`IAM-ORG-17..20` belum terpakai per 2026-11-07).
- Edit DDD hanya yang tertulis di rencana ini (disetujui pemilik 2026-11-07); versi dokumen 2.5 → 2.6 sekali, di Task 1.
- Pest serial di `simpas-ci:8.4` lewat skrip di workspace (guard worktree menolak perintah gabungan/heredoc/`VAR=`); Vitest tidak bersamaan dengan container Pest.
- Commit satu baris, Indonesia, conventional, **tanpa** trailer `Co-Authored-By`.

## Review Focus

1. **Cache rantai leluhur basi setelah pengaktifan.** `ancestorIds()` di-cache 30 menit; update massal tidak membuangnya → peneliti tetap tak melihat Form setelah unitnya aktif lagi. → Task 2 `[IAM-ORG-17]` membaca akses **sebelum** dan sesudah (cache terisi saat nonaktif).
2. **Operator dengan `organizations.manage` tanpa `users.manage`.** Bisa seluruh layar Organisasi termasuk Pindahkan User, tetap 403 di aksi akun (ubah peran). → Task 1 `wewenang: operator ...`.
3. **Database yang sudah berjalan belum punya permission baru.** Admin role kehilangan menu Organisasi sampai `PermissionSeeder` dijalankan ulang (Super Admin lolos via `Gate::before`). → Task 4 mencatat langkah pemilik di §9 W105; Task 1 `seeder memberi organizations.manage ke operator dan admin`.
4. **Undangan mati yang ikut dihitung "berlaku lagi".** Token dicabut, kedaluwarsa, atau habis kuota tidak boleh dihitung. → Task 2 `[IAM-ORG-20]` dengan keempat jenis token.
5. **Pengaktifan menular diam-diam ke turunan.** → Task 2 `[IAM-ORG-19]`.

---

### Task 1: Permission `organizations.manage` (W105)

**Files:**
- Modify: `database/seeders/PermissionSeeder.php` (`PERMISSIONS` + `ROLE_PERMISSIONS['operator']`), `database/seeders/EndToEndFlowSeeder.php` (operator@ juga `organizations.manage`, resep cek browser), `app/Support/Identity/PermissionCatalog.php` (`LABELS`), `routes/web.php` (grup organisasi → `can:organizations.manage`), `app/Actions/Identity/SaveOrganization.php` + `MoveUserToOrganization.php` (cek internal `users.manage` → `organizations.manage`), docblock `Identity\OrganizationController` / `OrganizationRequest` / `MoveUserRequest`, `resources/js/lib/navigation.ts` (Organisasi → `['organizations.manage']`), `docs/requirement-analysis/ddd/generic/02_identity_access.md` (katalog permission, baris `operator` di matriks peran, versi 2.6), `docs/requirement-analysis/bdd/generic/identity/organization.feature` (Given IAM-ORG-09), `docs/requirement-analysis/ux/ia/00_navigation/sidebar.md` (dua tempat: diagram + tabel → `organizations.manage`)
- Test: `tests/Feature/Identity/OrganizationTreeTest.php`, `tests/Feature/Identity/OrganizationLifecycleTest.php`, `tests/Feature/Identity/RegistrationPathTest.php` (aktor IAM-REG-10), `tests/Feature/PermissionSeederTest.php`, `tests/frontend/lib/navigation.spec.ts`

**Interfaces:**
- Produces: permission `organizations.manage` (guard `web`), label `'Mengelola struktur organisasi'`; route group `['auth', 'can:organizations.manage']` prefix `admin` name `admin.` berisi ketujuh route `admin.organizations.*` (nama tidak berubah).
- Produces: `SaveOrganization`/`MoveUserToOrganization` melempar `AuthorizationException` bila aktor tanpa `organizations.manage` (pesan lama dipertahankan).

- [ ] **Step 1: Tulis test yang gagal.**
  - `OrganizationTreeTest`: `organizationScreenActor()` memberi `organizations.manage`; ganti `it('wewenang: users.manage 200, ...')` menjadi `it('wewenang: organizations.manage 200, users.manage saja 403, schemes.manage saja 403, peneliti 403')` (index + 6 route tulis, termasuk `reactivate` **mulai Task 2** — di Task 1 hanya 5 route tulis yang ada); tambah `it('wewenang: operator peran seeder mengelola organisasi tanpa users.manage')` (aktor `assignRole('operator')` → index 200, `move-user` sukses, `put users.roles.update` 403 — route ubah peran, dijaga `UserPolicy::changeRoles`).
  - `OrganizationLifecycleTest`: `operatorOrganisasi()` dan aktor HTTP IAM-ORG-04 memakai `organizations.manage`; tambah `it('[IAM-ORG-09] pemegang users.manage saja tidak bisa menyusun struktur')` (`SaveOrganization::run` → `AuthorizationException`).
  - `RegistrationPathTest` IAM-REG-10: aktor `organizations.manage`.
  - `PermissionSeederTest`: `it('seeder memberi organizations.manage ke operator dan admin')`.
  - `navigation.spec.ts`: ubah test W105 → Organisasi tampil untuk `organizations.manage`, tidak untuk `users.manage` saja maupun `schemes.manage` saja; tambah `'organizations.manage'` ke `FULL_CATALOG`.
  - Given IAM-ORG-09 → `Given Operator memegang permission "organizations.manage"`.
- [ ] **Step 2:** Jalankan `pest tests/Feature/Identity tests/Feature/PermissionSeederTest.php` + `npx vitest run tests/frontend/lib/navigation.spec.ts`. Expected: FAIL (permission belum ada / route masih `users.manage`).
- [ ] **Step 3:** Implementasikan **Interfaces** + berkas di **Modify**. DDD: tambah `organizations.manage` ke blok permission dan ke baris `operator`; versi 2.6. Jangan ubah teks BR-IAM-17/18 ("oleh Operator" kini benar).
- [ ] **Step 4:** Jalankan `tests/Feature/Identity` + `tests/Feature/PermissionSeederTest.php` + `tests/Feature/DatabaseSeederPermissionTest.php` + `tests/Feature/Inertia` + `tests/Unit` + `npx vitest run tests/frontend/lib/navigation.spec.ts tests/frontend/pages/OrganizationPage.spec.ts`. Expected: PASS.
- [ ] **Step 5: Commit** `feat: wewenang organizations.manage untuk struktur organisasi`

### Task 2: Aktifkan kembali unit — BE (W119, BR-IAM-22)

**Files:**
- Create: `app/Actions/Identity/ReactivateOrganization.php`, `app/Queries/Identity/RevivableInvitations.php`
- Modify: `app/Data/View/SystemConfig/OrganizationUnitData.php` (`canReactivate`, `revivableInvitations`), `app/Http/Controllers/Identity/OrganizationController.php` (`reactivate`; `selectedUnit` mengisi field baru), `routes/web.php` (`POST /organizations/{organization}/reactivate` → `organizations.reactivate`), `app/Actions/Identity/DeactivateOrganization.php` (komentar "Kalau unit ini diaktifkan kembali…" → rujuk BR-IAM-22), `docs/requirement-analysis/ddd/generic/02_identity_access.md` (baris BR-IAM-22 setelah BR-IAM-21 dengan teks di atas; Domain Events: `OrganizationReactivated` | Operator/Admin mengaktifkan kembali unit | Form Engine (invalidasi cache leluhur), IAM (undangan berlaku lagi)), `docs/requirement-analysis/bdd/generic/identity/organization.feature` (IAM-ORG-17..20)
- Test: `tests/Feature/Identity/OrganizationLifecycleTest.php`, `tests/Feature/Identity/OrganizationTreeTest.php`

**Interfaces:**
- Produces: `RevivableInvitations::run(Organization $organization): int` (`ChangesNoState`) — token `organization_id` = unit itu (bukan subtree), `revoked_at` null, `expires_at` null atau > now, `max_uses` null atau `used_count < max_uses`. Predikat yang sama dengan `InvitationToken::isValid()` minus syarat organisasi aktif.
- Produces: `ReactivateOrganization::run(Organization $organization): int` (jumlah undangan yang berlaku lagi). Urutan: sudah aktif → `return 0` tanpa audit; induk ada dan nonaktif → `ValidationException` `organization_id: 'Induk unit ini masih nonaktif. Aktifkan induknya lebih dulu.'` (`// BR-IAM-22`); hitung `RevivableInvitations` **sebelum** update; `$organization->update(['is_active' => true])`; audit `'Organisasi diaktifkan kembali'` dengan `AuditChange(old: ['is_active' => false], new: ['is_active' => true])`.
- Produces: `OrganizationUnitData` + `bool $canReactivate` (= nonaktif **dan** induk null atau aktif) + `int $revivableInvitations`. `of()` menerima `int $revivableInvitations` sebagai parameter ketiga; controller mengisinya dengan `RevivableInvitations::run($unit)`.
- Produces: `reactivate(Organization)` → redirect back, success `'Unit organisasi diaktifkan kembali.'` atau `"Unit organisasi diaktifkan kembali. {$n} undangan registrasi berlaku lagi."`.

- [ ] **Step 1: Tulis test yang gagal** (+ skenario di `organization.feature`, tag `@BR-IAM-22 @M3 @ADMIN-08-02`):
  - `it('[IAM-ORG-17] mengaktifkan kembali unit memulihkan rantai akses Form')` — susunan IAM-ORG-08 (ITK → FTI → Informatika, Form di ITK, Sari di Informatika); FTI nonaktif → `canAccessForm` false (cache terisi); `ReactivateOrganization::run(FTI)` → `canAccessForm` true; audit "Organisasi diaktifkan kembali" ada. Skenario: Given FTI nonaktif dan "Pengajuan Penelitian" tidak muncul bagi "Sari" / When Operator mengaktifkan kembali "FTI" / Then "Pengajuan Penelitian" muncul lagi di daftar Form "Sari". (Informatika tetap aktif di fixture ini — pasang `is_active` FTI saja.)
  - `it('[IAM-ORG-18] unit di bawah induk nonaktif tidak bisa diaktifkan kembali')` — ITK nonaktif, FTI nonaktif → 422 `organization_id` dengan pesan di atas lewat HTTP, FTI tetap nonaktif, tanpa audit.
  - `it('[IAM-ORG-19] pengaktifan hanya untuk unit itu, turunannya tetap nonaktif')` — `DeactivateOrganization::run(FTI)` (menular ke Informatika) lalu `ReactivateOrganization::run(FTI)` → FTI aktif, Informatika nonaktif.
  - `it('[IAM-ORG-20] undangan yang masih sah berlaku lagi dan disebut sebelum konfirmasi')` — unit nonaktif dengan 4 token: sah, dicabut, kedaluwarsa, habis kuota → `selected.revivableInvitations` = 1, `ReactivateOrganization::run` = 1, token sah `isValid()` true. Skenario: ...Then layar konfirmasi menyebut 1 undangan yang berlaku lagi / And undangan yang dicabut atau kedaluwarsa tetap tidak berlaku.
  - `it('mengaktifkan unit yang sudah aktif tidak mengubah apa pun')` — return 0, tanpa audit.
  - `OrganizationTreeTest`: kontrak `selected` memuat `canReactivate` + `revivableInvitations`; `it('canReactivate mengikuti status induk')` (akar nonaktif true, di bawah induk nonaktif false, aktif false); tambahkan `reactivate` ke loop test wewenang.
- [ ] **Step 2:** Jalankan `pest tests/Feature/Identity`. Expected: FAIL.
- [ ] **Step 3:** Implementasikan **Interfaces**.
- [ ] **Step 4:** Jalankan `tests/Feature/Identity` + `tests/Feature/FormEngine` + `tests/Unit`. Expected: PASS.
- [ ] **Step 5: Commit** `feat: aktifkan kembali unit organisasi`

### Task 3: Aktifkan kembali unit — FE

**Files:**
- Modify: `resources/js/types/system-config.ts` (`OrganizationUnitData.canReactivate`, `revivableInvitations`), `resources/js/Components/SystemConfig/OrganizationUnitPanel.vue`, `resources/js/Components/SystemConfig/OrganizationLifecycleDialogs.vue` (`OrganizationLifecycleAction` + `'reactivate'`)
- Test: `tests/frontend/components/SystemConfig/OrganizationUnitPanel.spec.ts` (+ fixture `unit()` diberi dua field baru di semua spec yang membangun `OrganizationUnitData`)

**Interfaces:**
- Consumes: Task 2 `canReactivate`, `revivableInvitations`, route `admin.organizations.reactivate`.
- Produces: aksi panel `'Aktifkan Kembali'` (ikon `RotateCcw`) hanya untuk unit nonaktif, `available = canReactivate`; hint tersedia `'Hanya unit ini yang aktif kembali; unit turunannya tetap nonaktif.'`, terhalang `'Induk unit ini masih nonaktif. Aktifkan induknya lebih dulu.'` (`<!-- BR-IAM-22 -->`).
- Produces: `ConfirmDialog` "Aktifkan kembali unit?" / `"Unit ini kembali ditawarkan dan rantai akses Form-nya tersambung lagi. Unit turunannya tetap nonaktif; aktifkan satu per satu bila perlu."` + bila `revivableInvitations > 0`: `" {n} undangan registrasi yang belum kedaluwarsa berlaku lagi."`; tombol `'Aktifkan Kembali'` (tidak destructive) → `router.post(route('admin.organizations.reactivate', id), {}, callbacks(...))` (helper `callbacks` yang sama dengan nonaktifkan).

- [ ] **Step 1: Tulis test yang gagal** di `OrganizationUnitPanel.spec.ts`: `it('[IAM-ORG-18] aktifkan kembali terhalang induk nonaktif')` (disabled + hint), `it('[IAM-ORG-20] konfirmasi aktifkan kembali menyebut undangan yang berlaku lagi')` (teks + `router.post('/admin.organizations.reactivate/3', {}, objectContaining({ preserveScroll: true }))`), `it('unit aktif tidak menawarkan Aktifkan Kembali')`.
- [ ] **Step 2:** `npx vitest run tests/frontend/components/SystemConfig tests/frontend/pages/OrganizationPage.spec.ts`. Expected: FAIL.
- [ ] **Step 3:** Implementasikan **Interfaces**.
- [ ] **Step 4:** Ulangi Step 2 + `npm run lint` + `npm run typecheck`. Expected: PASS.
- [ ] **Step 5: Commit** `feat(fe): aktifkan kembali unit organisasi v2`

### Task 4: Tutup C1-3

**Files:**
- Modify: `docs/design/fe-v2-design.md` (§8: baris baru `C1-3 | ☑ | Wewenang & aktifkan kembali organisasi | ADMIN-08-02 | …` setelah C1-2; §9 W105 dan W119 ditulis ulang sebagai keputusan pemilik 2026-11-07, W105 menyebut langkah pemilik: jalankan `php artisan db:seed --class=PermissionSeeder` di database yang sudah berjalan — `syncPermissions` menimpa perubahan manual pada peran bawaan)

- [ ] **Step 1:** Gate penuh `composer run quality` (Pest serial) lalu `npm run quality`. Expected: hijau; laporkan jumlah test apa adanya.
- [ ] **Step 2: Cek browser** (`c1-3-browser.mjs`, fixture `c1-org-fixture.php` + FTI C1 dinonaktifkan oleh fixture sesudah memindah user Informatika C1, ITK C1 tetap aktif; login **operator@itk.ac.id**): (1) menu Organisasi tampil untuk Operator; (2) Informatika C1 (nonaktif, induk FTI C1 nonaktif): Aktifkan Kembali terkunci + hint; (3) FTI C1: konfirmasi Aktifkan Kembali → badge Nonaktif hilang dari FTI C1, Informatika C1 tetap Nonaktif; (4) Informatika C1 kini bisa diaktifkan kembali. Cek DB sesudahnya.
- [ ] **Step 3:** Perbarui §8 dan §9.
- [ ] **Step 4: Commit** `docs: irisan C1-3 wewenang dan aktifkan kembali organisasi selesai`
