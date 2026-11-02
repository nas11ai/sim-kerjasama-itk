# FE v2 — Irisan P1: Profil — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dua frame profil (SHARED-01-06 Lengkapi Profil, SHARED-01-07 Profil Saya) menjadi `Profile/CompletePage` dan `Profile/ShowPage`, di atas `UserProfile` DDD (telepon, bidang keahlian, foto; BR-IAM-03). Untuk pertama kalinya ada jalur produksi yang mengisi ketiga data itu: hari ini tidak ada kode yang menulis `phone`/`expertise`/`photo_path`, sehingga setiap peneliti sungguhan tertahan IAM-VERIFY-04. Biodata Form-Engine legacy (`User/Biodata/*`, `CheckBiodata`) dihapus, beserta pembaca berkas biodata W58: aplikasi tidak menyimpan KTP (pemilik, 2026-11-02).

**Architecture:** Pola I1. Controller tipis di `app/Http/Controllers/Identity/` → FormRequest → Action di `app/Actions/Identity/` (semuanya mengaudit, BR-RPT-09) → redirect. Props dari `app/Data/View/Identity/OwnProfileData`. Satu definisi kelengkapan, `UserProfile::missingDetails()`, dibaca `isComplete()`, gerbang `StartSubmission`, banner dasbor, dan halaman Lengkapi Profil. Setiap task adalah satu irisan vertikal (route + controller + halaman + test) karena `ensure_pages_exist => true` dan `FrontendRouteNamesTest`: halaman tidak boleh menaut route yang belum ada, dan controller tidak boleh merender halaman yang belum ada.

**Tech Stack:** Laravel 13 / PHP 8.4, spatie/laravel-data, spatie/laravel-activitylog (`AuditTrail`), Inertia v3 + Vue 3 `<script setup>` + TS, shadcn-vue, Ziggy, Pest, Vitest. Tanpa dependency baru.

**Spec:** `docs/design/fe-v2-design.md` §4.2–§4.5, baris P1 di §8, K3/W15/W46/W52/W58 di §9. Frame `../simpas-wireframe/screens/SHARED-01-06/screen.js` dan `SHARED-01-07/screen.js`, bagian yang sama di `docs/FRAME-AUDIT.md` dan `docs/STATE-COVERAGE.md`. DDD `ddd/generic/02_identity_access.md` (BR-IAM-01..05, 13, 15, 18, bagian "Status Akun dan Status Organisasi", "Domain Events"), `ddd/02_ubiquitous_language.md` ("Profil lengkap"), `ddd/supporting/05_reporting.md` BR-RPT-09. BDD `bdd/generic/identity/verification.feature` (IAM-VERIFY-01/04/05/06/12), `account_events.feature` (IAM-EVENT-05), `bdd/README.md` ("Frame yang sengaja tidak punya skenario": SHARED-01-07). UX `ux/ia/01_auth/index.md` (Lengkapi Profil, Profil Saya, flow diagram).

**Ukuran:** irisan kecil, satu session (§6). Lima task.

## Kesesuaian dengan DDD dan BDD (diperiksa 2026-11-02)

**Skenario yang mengikat, dan di mana ia diikat:**

| Skenario | BR | Diikat di | Perubahan di P1 |
| --- | --- | --- | --- |
| `[IAM-VERIFY-04]` profil belum lengkap memblokir pembuatan | BR-IAM-03 | `NidnVerificationTest` → `StartSubmission` | Test Pest **tidak diubah**: "When memulai pengajuan baru" = `StartSubmission`, dan guard di sana tetap penegak yang mengikat. Redirect wizard (Task 3) hanya UX di depannya. State frame (banner + "Buat Pengajuan Baru" nonaktif) digambar di dasbor dan diuji Vitest dengan ID ini |
| `[IAM-VERIFY-05]` profil lengkap membuka pembuatan | BR-IAM-03 | idem | Tidak diubah. Test baru: `CompleteProfile` → `StartSubmission` lolos (jalur produksi ujung ke ujung) |
| `[IAM-VERIFY-12]` terisi sebagian tetap belum lengkap | BR-IAM-03 | idem | Tidak diubah. `isComplete()` di-refactor (**Extract Method** → `missingDetails()`), test ini penjaga regresinya. State frame ("2 dari 3 data tambahan belum terisi…") digambar di `CompletePage` dan diuji Vitest dengan ID ini |
| `[IAM-VERIFY-06]` pending dengan profil lengkap boleh membuat draft | BR-IAM-04 | idem | Tidak diubah. Akibatnya bagi P1: Lengkapi Profil **harus** terbuka bagi akun `pending` dan `rejected` juga (W63) |
| `[IAM-VERIFY-01]` pending login → layar menunggu verifikasi | BR-IAM-04 | `PostLoginDestination` | Tetap didahulukan: tujuan pasca-login "Lengkapi Profil" (Task 3) hanya untuk akun yang lolos cabang pending/rejected |
| `[IAM-EVENT-05]` melengkapi profil sendiri tidak memberi tahu | BR-IAM-15 | `AccountEventNotificationTest`, lewat `$profile->update()` langsung | **Diikat ulang** ke `CompleteProfile::run()`, sebab langkah "When Budi melengkapi profilnya" kini punya jalur produksi (preseden I1: IAM-PERM-01/02/04 diikat ulang ke Action). ID dan judul tetap |

`pending.txt` dan `uncovered.txt` tidak berubah, dan **tidak ada skenario baru**. SHARED-01-07 sengaja tanpa skenario (`bdd/README.md`: "Tidak ada BR; penyuntingan profil biasa"), dan P1 tidak menambah aturan bisnis (§2 non-tujuan). Sejauh BR yang ada menyentuh SHARED-01-07, aturannya ditegakkan dan diuji Pest tanpa ID skenario:

- **BR-IAM-01/02 → email dan NIDN tidak bisa diubah sendiri.** Pemilik email mengganti emailnya sendiri akan melompati pemeriksaan domain `@itk.ac.id` BR-IAM-01, yang hanya berjalan saat registrasi mandiri. NIDN hanya dikoreksi Operator (`CorrectNidn`, IAM-VERIFY-13, W52), karena NIDN adalah objek verifikasi BR-IAM-13. `ProfileController::update` legacy, yang mengizinkan ganti email, dihapus.
- **BR-IAM-18 → organisasi tidak bisa dipindah sendiri.** Ia tampil read-only.
- **BR-IAM-15 → tidak ada notifikasi** untuk melengkapi atau menyunting profil, mengganti foto, atau mengganti kata sandi ("Perubahan yang user lakukan sendiri — melengkapi profil, mengganti password — tidak menerbitkan event").
- **BR-RPT-09 → setiap perubahan lewat Action yang mengaudit**, termasuk ganti kata sandi. Hari ini `Auth\PasswordController` menulis langsung tanpa audit. Karena ShowPage menyentuh flow itu, ia diekstrak ke `ChangeOwnPassword` (strangler, AGENTS.md). Entri audit tidak pernah memuat hash.
- **BR-IAM-05 → akun nonaktif tidak bisa menyunting profilnya.** `EnsureAccountActive` (I1) sudah ada di grup `web`; route baru tidak boleh keluar dari grup itu.
- **W15 (pemilik) → foto profil disimpan di disk privat** dan hanya dibaca lewat route ber-scope milik sendiri.

**Biodata Form-Engine bertentangan dengan DDD, DDD menang (W61):**

- DDD dan ubiquitous language mendefinisikan profil lengkap persis sebagai telepon + bidang keahlian + foto (`isComplete()`), dan bagian "Status Akun dan Status Organisasi" menegaskan **tiga** gerbang terpisah. Mereka secara eksplisit memperingatkan bahwa satu gerbang "user siap?" akan membuat salah satu kasus salah tanpa gagal mencolok.
- `CheckBiodata` adalah gerbang keempat seperti itu. Ia mengalihkan seluruh menu sampai sebuah submission Form-Engine "biodata" berstatus `Approved`. Ia memakai state machine pengajuan untuk sesuatu yang bukan pengajuan, dan hanya berlaku bagi role legacy `Mahasiswa`/`Tenaga Kependidikan`, yang bukan peran DDD (`RoleCatalog`, W57).
- DDD, BDD, UX, dan wireframe sama sekali tidak menyebut biodata. Kolom legacy §8 baris P1 sudah menjadwalkan penghapusan `User/Biodata/*`, dan K3 (pemilik) mengatur bahwa layar tanpa wireframe dihapus beserta route dan controller-nya.
- Task 4 menghapus seluruh rantai penulis dan gerbangnya. Nama `user.biodata.index` tetap ada sebagai redirect ke `/profile` sampai F99, karena legacy `Components/AppSidebar.vue` masih menautnya (pola W46).
- **Pemilik, 2026-11-02: aplikasi tidak menyimpan KTP**, karena isinya data pribadi sensitif. Karena itu pembaca berkas biodata W58 (`BiodataFiles`, `VerificationFileController`, route `admin.verifications.files.show`, daftar "berkas" di kartu antrian 08-02) ikut dihapus di Task 4. Ini sejalan dengan DDD: BR-IAM-13 memverifikasi **NIDN**, bukan dokumen identitas, dan frame 08-02 tidak menggambar berkas. Tidak ada migrasi data atau berkas karena aplikasi belum live; DB dev dibersihkan dengan `migrate:fresh`.

## Keputusan penulis rencana (dicatat ke §9 di Task 5)

- **W61** — Biodata Form-Engine dan gerbang `check_biodata` dihapus (lihat di atas). **Pemilik, 2026-11-02:** tanpa unggahan KTP, sehingga pembaca W58 ikut dihapus dan baris W58 di §9 ditutup. Baris `FormType` "Biodata" (id 1) di seeder tetap ada supaya id jenis lain tidak bergeser. Bawaan untuk E2: layar Form tidak boleh menawarkan jenis "Biodata" untuk form baru.
- **W62** — "Bidang Keahlian" berupa input teks, bukan select seperti di frame. DDD hanya punya `+string expertise` tanpa katalog, dan membuat katalog berarti membuat data referensi baru (§2 non-tujuan). Kalau pemilik menginginkan katalog, tempatnya C1 (konfigurasi umum).
- **W63** — Lengkapi Profil terbuka bagi setiap pemilik `UserProfile`, apa pun status verifikasinya. UX IA menulis "status active", tapi BR-IAM-04 + IAM-VERIFY-06 mengizinkan akun pending membuat draft bila profilnya lengkap, jadi pending harus bisa melengkapinya (DDD menang). Tujuan pasca-login mengikuti flow diagram UX: akun yang lolos cabang verifikasi, punya `submissions.create`, dan profilnya belum lengkap mendarat di Lengkapi Profil. Pending/rejected tetap mendarat di layar verifikasi (IAM-VERIFY-01), dan dari dasbor mereka melihat banner profil. User tanpa `UserProfile` (mis. Admin hasil seeder) tidak punya data tambahan. Lengkapi Profil mengalihkannya ke Profil Saya, yang hanya menampilkan nama, email, dan kata sandi. P1 **tidak** membuat baris `UserProfile` baru, karena profil lahir `pending` dan akan masuk antrian verifikasi (BR-IAM-12).
- **W64** — Email, NIDN, dan organisasi read-only (BR-IAM-01/02/18). Nama route `profile.edit` dipertahankan untuk `GET /profile` (dipakai `AppSidebarUser` v2, legacy `AppSidebar`, dan lima berkas test). `profile.destroy` sudah tidak ada.
- **W65** — Foto disimpan di `PrivateUploadDisk::NAME` (`profile-photos/`, nama acak `hashName`), JPG/PNG maks 2 MB (frame), dan dibaca hanya lewat `profile.photo.show` milik sendiri (tanpa parameter, jadi tidak ada IDOR). Foto lama dihapus setelah penggantian ter-commit. "Crop sederhana" (frame 01-07) tidak dibangun. Avatar sidebar tetap inisial. Operator yang melihat foto orang lain di detail user belum dibutuhkan frame mana pun.
- **W66** — Ganti kata sandi tetap lewat route Laravel `password.update` (PUT, aturan `current_password` + `Password::defaults()` + `confirmed`), sekarang memanggil `ChangeOwnPassword` yang mengaudit "Kata sandi diganti" tanpa properti. Sesi lain **tidak** diakhiri: BR-IAM-21 memberi jalur itu kepada `users.manage`, bukan kepada ganti kata sandi.

## Global Constraints

- Berkas Vue baru ≤ 250 baris, sasaran < 150; PHP baru ≤ 200 baris, method ≤ 40 baris, CC ≤ 8, ≤ 4 parameter. Tanpa blok duplikat 6 baris dengan berkas mana pun, termasuk legacy: tulis dari frame, jangan menyalin `Profile/EditPage.vue`, `Profile/Partials/*`, `User/Biodata/IndexPage.vue`.
- Baris pertama `<script setup>`: `// Frame: SHARED-01-06` / `// Frame: SHARED-01-07`.
- Teks UI diambil dari frame apa adanya, termasuk judul, subjudul, label, placeholder, dan tombol. Kalimat `stateGallery` adalah anotasi, **kecuali** kalimat banner yang disebut eksplisit per task.
- Frontend tidak menghitung kelengkapan sendiri. Ia membaca `missing` dari backend (anti-tujuan §2).
- Identifier Inggris; komentar Indonesia, prosa maks 3 baris, sebut kode BR (`// BR-IAM-03 — …`). TS tanpa `any`/`console.log`/cast; PHPStan tanpa `@phpstan-ignore`, baseline, `assert()`, inline `@var`, cast.
- Mutasi lewat `useForm` Inertia (unggahan: `forceFormData: true`), tanpa `axios`.
- Setiap Action baru mengaudit lewat `AuditTrail::record` (BR-RPT-09, `AuditCoverageTest`), tidak ada yang mengirim notifikasi (BR-IAM-15).
- Gate: `composer run quality` + `npm run quality`. Pest di container `simpas-ci:8.4`, `-e DB_PORT=55432 -e DB_PASSWORD=postgres`, **serial**. Worktree butuh salinan `vendor/` asli dan `.env`.
- Commit satu baris, Indonesia, conventional, **tanpa** trailer `Co-Authored-By`.

## Review Focus

1. **Lompatan gerbang lewat profil.** Peneliti mengirim `email`, `nidn`, `organization_id`, atau `verification_status` ke `PATCH /profile` atau `POST /profile/complete`. Tidak ada yang berubah selain field yang diizinkan, dan status verifikasi tetap. → Task 2 `field di luar formulir diabaikan`, Task 3 `melengkapi profil tidak mengubah status verifikasi`.
2. **Profil sebagian lolos gerbang.** Telepon terisi tapi keahlian kosong, atau foto belum diunggah. Simpan ditolak 422, dan `missingDetails()` tetap menyebut kekurangannya. → Task 1 urutan `missingDetails`, Task 3 `foto wajib bila belum ada`.
3. **Open redirect lewat `next`.** `POST /profile/complete?next=https://evil.example` mendarat di dasbor, bukan di URL luar. → Task 3 `next tak dikenal jatuh ke dasbor`.
4. **Berkas berbahaya sebagai foto.** SVG, GIF, PDF yang diganti nama `.jpg`, atau > 2 MB ditolak 422, dan tidak ada berkas yang tertinggal di disk. → Task 1 `foto selain JPG/PNG atau lebih dari 2 MB ditolak`.
5. **Jejak audit membocorkan rahasia.** Entri "Kata sandi diganti" tidak memuat password/hash, dan entri foto tidak memuat path privat. → Task 2 `audit ganti kata sandi tanpa properti`, Task 1 `audit foto tanpa path`.

---

### Task 1: Satu definisi kelengkapan + foto profil (BE)

**Files:**
- Modify: `app/Models/UserProfile.php` (**Extract Method** `missingDetails()`; `isComplete()` membacanya), `app/Support/AccountStateGuard.php` (tambah `isProfileComplete`; `assertProfileComplete` membacanya), `routes/web.php` (dua route foto di grup `auth`)
- Create: `app/Actions/Identity/ReplaceProfilePhoto.php`, `app/Http/Requests/Identity/ProfilePhotoRequest.php`, `app/Http/Controllers/Identity/ProfilePhotoController.php`, `app/Data/View/Identity/OwnProfileData.php`
- Test: `tests/Feature/Identity/ProfileCompletenessTest.php`, `tests/Feature/Identity/ProfilePhotoTest.php`

**Interfaces:**
- Produces: `UserProfile::missingDetails(): list<'phone'|'expertise'|'photo'>`, dalam urutan tetap phone, expertise, photo. `isComplete()` = `missingDetails() === []`. Komentar BR-IAM-03 yang ada dipindah ke `missingDetails`.
- Produces: `AccountStateGuard::isProfileComplete(User $user): bool`, bernilai false bila tanpa `UserProfile`.
- Produces: `ReplaceProfilePhoto::run(User $user, UploadedFile $photo): UserProfile`. Ia menyimpan lewat `$photo->store('profile-photos', PrivateUploadDisk::NAME)`, menyetel `photo_path`, mengaudit `'Foto profil diganti'` (subjek profil, tanpa `AuditChange`), lalu menghapus berkas lama setelah transaksi ter-commit. Profil tidak ada → `ValidationException` `photo: 'Akun ini tidak memiliki profil peneliti.'`.
- Produces: route `profile.photo.show` (GET `/profile/photo`) → `ProfilePhotoController::show` stream berkas milik sendiri (`Storage::disk(...)->response`), 404 bila tidak ada. Route `profile.photo.update` (POST `/profile/photo`) → `ProfilePhotoController::update` → redirect back, flash success `'Foto profil diperbarui.'`.
- Produces: `ProfilePhotoRequest` rules `photo: required|image|mimes:jpg,jpeg,png|max:2048`; pesan `max` → `'Ukuran foto maksimal 2 MB.'`, `mimes` → `'Foto harus berformat JPG atau PNG.'`.
- Produces: `OwnProfileData` (spatie Data): `string $name`, `string $email`, `bool $hasProfile`, `?string $nidn`, `?string $organization` (`$user->organization?->name`, seperti `UserDetailData`), `?string $phone`, `?string $expertise`, `?string $photoUrl` (`route('profile.photo.show').'?v='.md5(photo_path)`, null tanpa foto), `list<string> $missing` (label: `Nomor Telepon`, `Bidang Keahlian`, `Foto Profil`; kosong bila tanpa profil). Factory `OwnProfileData::of(User $user): self`.

- [ ] **Step 1: Tulis test yang gagal.** `ProfileCompletenessTest`:
  - `it('missingDetails menyebut kekurangan dalam urutan tetap')` → profil `incomplete()` → `['phone','expertise','photo']`; state `partiallyComplete()` (sudah ada) → `['expertise','photo']`; default → `[]`.
  - `it('isProfileComplete false bagi user tanpa profil')`.
  - `it('OwnProfileData melabeli kekurangan dan menyembunyikan path foto')` → `partiallyComplete()` → `missing === ['Bidang Keahlian','Foto Profil']`; profil lengkap → `photoUrl` diawali `route('profile.photo.show')` dan tidak memuat `profil/uji.jpg`.

  `ProfilePhotoTest` (`Storage::fake('local')`):
  - `it('mengganti foto menyimpannya di disk privat dan menghapus yang lama')` → POST `profile.photo.update` dengan `UploadedFile::fake()->image('a.png')`. Hasilnya `photo_path` baru ada di disk fake `local`, path lama hilang, dan disk `public` kosong.
  - `it('audit foto tanpa path')` → entri `'Foto profil diganti'` ada, `properties` kosong.
  - `it('foto selain JPG/PNG atau lebih dari 2 MB ditolak')` → dataset `gif`, `svg` (`UploadedFile::fake()->create('x.svg', 10, 'image/svg+xml')`), `pdf` bernama `.jpg`, `image('b.jpg')->size(2049)`. Masing-masing 422/`assertSessionHasErrors('photo')`, `photo_path` tetap, dan tidak ada berkas baru di disk.
  - `it('foto sendiri terbaca, tanpa foto 404, tamu ke login')`.
  - `it('mengganti foto tidak mengirim notifikasi')` → `Notification::assertNothingSent()` (BR-IAM-15).
- [ ] **Step 2:** Jalankan `./vendor/bin/pest tests/Feature/Identity/ProfileCompletenessTest.php tests/Feature/Identity/ProfilePhotoTest.php` (container). Expected: FAIL.
- [ ] **Step 3:** Implementasikan semua item di **Interfaces**. State factory `incomplete()` dan `partiallyComplete()` sudah ada; jangan menambah yang baru.
- [ ] **Step 4:** Jalankan test di atas + `tests/Feature/Identity/NidnVerificationTest.php` (IAM-VERIFY-04/05/12 tetap hijau). Expected: PASS.
- [ ] **Step 5: Commit** `feat(be): kelengkapan profil satu definisi dan foto profil privat`

### Task 2: Profil Saya — SHARED-01-07 (BE + FE)

**Files:**
- Create: `app/Http/Controllers/Identity/ProfileController.php` (`show`, `update`), `app/Http/Requests/Identity/UpdateOwnProfileRequest.php`, `app/Data/Request/ProfileDetailsInput.php`, `app/Actions/Identity/UpdateOwnProfile.php`, `app/Actions/Identity/ChangeOwnPassword.php`, `resources/js/types/profile.ts`, `resources/js/Pages/Profile/ShowPage.vue`, `resources/js/Components/Profile/PhotoCard.vue`, `resources/js/Components/Profile/PasswordCard.vue`
- Modify: `routes/web.php` (`profile.edit` → `Identity\ProfileController@show`, `profile.update` → `@update`), `app/Http/Controllers/Auth/PasswordController.php` (validasi tetap, tulis lewat `ChangeOwnPassword`), `tests/Feature/ProfileTest.php` (ditulis ulang untuk kontrak baru)
- Delete: `app/Http/Controllers/ProfileController.php`, `app/Http/Requests/ProfileUpdateRequest.php`, `resources/js/Pages/Profile/EditPage.vue`, `resources/js/Pages/Profile/Partials/*`
- Test: `tests/Feature/ProfileTest.php`, `tests/Feature/Auth/PasswordUpdateTest.php` (tambahan), `tests/frontend/pages/ProfileShowPage.spec.ts`

**Interfaces:**
- Consumes: `OwnProfileData::of`, route `profile.photo.update`/`profile.photo.show` (Task 1).
- Produces: `ProfileDetailsInput` (spatie Data atau kelas final readonly, ikuti `app/Data/Request/` yang ada): `string $phone`, `string $expertise`. Aturan bersama, dipakai juga Task 3: `phone: required|string|max:32|regex:/^\+?[0-9][0-9 \-]{6,30}$/` (pesan `'Nomor telepon hanya boleh berisi angka, spasi, tanda hubung, dan + di depan.'`), `expertise: required|string|max:255`. Tempatkan aturan itu sekali (mis. `ProfileDetailsInput::rules()`), jangan diduplikasi di dua FormRequest.
- Produces: `UpdateOwnProfile::run(User $user, string $name, ProfileDetailsInput $details): User`. Ia menulis `users.name` dan `user_profiles.phone/expertise`, lalu mengaudit `'Profil diperbarui'` dengan `AuditChange` old/new **hanya untuk field yang berubah**. Tidak ada yang berubah → tanpa audit (preseden `CorrectNidn`). User tanpa profil hanya boleh mengubah nama, sehingga `UpdateOwnProfileRequest` mewajibkan phone/expertise hanya bila profil ada.
- Produces: `ChangeOwnPassword::run(User $user, string $plainPassword): void` → `Hash::make`, audit `'Kata sandi diganti'` dengan subjek user, tanpa `AuditChange`.
- Produces: page `Profile/ShowPage`, props `ProfileShowPageProps { profile: OwnProfile }` di `types/profile.ts` (`OwnProfile` mencerminkan `OwnProfileData`).
- Isi halaman (frame 01-07): `PageHeader` "Profil Saya" / "Kelola data profil dan keamanan akun Anda.".
  - **Grid 2 kolom.** `PhotoCard` "Foto Profil" (avatar = `photoUrl` atau inisial, nama, organisasi; unggah "Ganti Foto", hint "JPG/PNG maks 2MB." → `profile.photo.update`). `SectionCard` "Data Profil" berisi field Nama Lengkap, Email (disabled, hint "Tidak dapat diubah sendiri"), NIDN (disabled, hint sama; `—` bila null), Nomor Telepon, Bidang Keahlian (input teks, W62), dan tombol "Simpan Perubahan" → PATCH `profile.update`.
  - **`PasswordCard`** "Ganti Password" (grid 3: Password Lama, Password Baru, Konfirmasi Password Baru; tombol outline "Ubah Password" → PUT `password.update`, reset form saat sukses).
  - `hasProfile === false` → PhotoCard, Telepon, Keahlian, dan NIDN disembunyikan.

- [ ] **Step 1: Tulis test yang gagal.** `ProfileTest` (ganti isinya):
  - `it('Profil Saya merender kontrak OwnProfileData')` → `component('Profile/ShowPage')->has('profile.missing')->where('profile.email', …)`.
  - `it('memperbarui nama, telepon, dan keahlian beserta auditnya')` → old/new hanya memuat field yang berubah.
  - `it('field di luar formulir diabaikan')` → kirim juga `email`, `nidn`, `organization_id`, `verification_status`. Email, NIDN, organisasi, dan status tetap (BR-IAM-01/02/18).
  - `it('telepon tidak sah ditolak')` → `'abc'` → error `phone`.
  - Tiga test Breeze lama (`profile page is displayed`, `profile information can be updated`, `email verification status is unchanged…`) dihapus: yang pertama digantikan test kontrak, dua sisanya menguji ganti email yang sengaja dihapus (W64, BR-IAM-01).
  - `it('user tanpa profil hanya mengubah nama')` → tanpa phone/expertise → 302 sukses, dan tetap tidak ada baris `user_profiles` (W63).
  - `it('menyunting profil sendiri tidak mengirim notifikasi')` (BR-IAM-15).
  - `it('akun nonaktif tidak bisa menyunting profil')` → `is_active=false` → dialihkan ke login, nama tetap (BR-IAM-05).

  `PasswordUpdateTest` tambahan: `it('audit ganti kata sandi tanpa properti')` → entri `'Kata sandi diganti'` ada, `properties` kosong, dan tidak ada nilai yang memuat password atau `$2y$`. `it('ganti kata sandi tidak mengirim notifikasi')`.

  `ProfileShowPage.spec.ts`:
  - `it('email dan NIDN read-only dengan hint')` → input disabled, teks "Tidak dapat diubah sendiri".
  - `it('tanpa profil hanya nama, email, dan kata sandi')`.
  - `it('Ubah Password mengirim PUT ke password.update')`.
  - `it('avatar memakai photoUrl bila ada, inisial bila tidak')`.
- [ ] **Step 2:** Jalankan `./vendor/bin/pest tests/Feature/ProfileTest.php tests/Feature/Auth/PasswordUpdateTest.php` dan `npx vitest run tests/frontend/pages/ProfileShowPage.spec.ts`. Expected: FAIL.
- [ ] **Step 3:** Implementasikan semua item di **Interfaces**. Halaman FE dan route ada di commit yang sama (ensure_pages_exist). Hapus berkas legacy di daftar Delete. Komponen ditulis dari frame, bukan dari Partials.
- [ ] **Step 4:** Jalankan test di atas + `tests/Feature/Inertia/NativeFlashTest.php tests/Feature/Inertia/SharedNavigationPropsTest.php tests/Feature/Auth`. Expected: PASS.
- [ ] **Step 5: Commit** `feat(fe): layar Profil Saya`

### Task 3: Lengkapi Profil — SHARED-01-06, beserta pintunya (BE + FE)

**Files:**
- Create: `app/Http/Controllers/Identity/ProfileCompletionController.php` (`create`, `store`), `app/Http/Requests/Identity/CompleteProfileRequest.php`, `app/Actions/Identity/CompleteProfile.php`, `app/Data/View/Dashboard/ProfileCompletionData.php`, `resources/js/Pages/Profile/CompletePage.vue`
- Modify: `routes/web.php` (`profile.complete` GET `/profile/complete`, `profile.complete.store` POST `/profile/complete`, grup `auth`, **tanpa** `check_biodata`), `app/Http/Controllers/Submission/SubmissionWizardController.php` (`create`: guard clause), `app/Queries/Identity/PostLoginDestination.php`, `app/Queries/Dashboard/DashboardOverview.php` + `app/Data/View/Dashboard/DashboardOverviewData.php` (`profileCompletion`), `resources/js/types/profile.ts`, `resources/js/types/dashboard.ts`, `resources/js/Pages/Dashboard/IndexPage.vue`, `resources/js/lib/dashboardActions.ts` + `resources/js/Components/Dashboard/QuickActions.vue` (aksi nonaktif dengan alasan), `tests/Feature/Identity/AccountEventNotificationTest.php` (IAM-EVENT-05 diikat ulang)
- Test: `tests/Feature/Identity/ProfileCompletionTest.php`, `tests/Feature/Dashboard/DashboardPageTest.php`, `tests/Feature/Auth/AccountVerificationTest.php` (tempat `PostLoginDestination` diuji), `tests/frontend/pages/ProfileCompletePage.spec.ts`, `tests/frontend/pages/DashboardIndexPage.spec.ts`

**Interfaces:**
- Consumes: `ProfileDetailsInput` + aturannya (Task 2), `ReplaceProfilePhoto::run`, `AccountStateGuard::isProfileComplete`, `OwnProfileData` (Task 1).
- Produces: `CompleteProfile::run(User $user, ProfileDetailsInput $details, ?UploadedFile $photo): UserProfile`. Ia menulis phone/expertise, memanggil `ReplaceProfilePhoto::run` bila `$photo` ada (dua entri audit, keduanya benar), dan mengaudit `'Profil dilengkapi'` dengan `AuditChange` old/new phone+expertise. **Tidak** menyentuh `verification_status`, `nidn`, atau `organization_id`. Tanpa profil → `ValidationException` seperti Task 1.
- Produces: `CompleteProfileRequest`, berisi aturan `ProfileDetailsInput` + `photo: image|mimes:jpg,jpeg,png|max:2048`, dan `required` hanya bila `photo_path` profil masih null (`Rule::requiredIf`).
- Produces: `ProfileCompletionController`:
  - `create`: tanpa profil, atau profil sudah lengkap → redirect `profile.edit`. Selain itu render `Profile/CompletePage` dengan props `{ profile: OwnProfileData, next: 'submission' | null }`. `next` dibaca dari query dan hanya `'submission'` yang diterima.
  - `store`: `next === 'submission'` → redirect `user.submissions.create`, selain itu `dashboard`. Flash success `'Profil tersimpan.'` (banner frame IAM-EVENT-05).
- Produces: `SubmissionWizardController::create`. Guard clause pertama berbunyi `// BR-IAM-03 — pintu UX; penegaknya tetap StartSubmission (IAM-VERIFY-04)`: `!isProfileComplete` → redirect `profile.complete` dengan `next=submission`. Return type `Response|RedirectResponse`.
- Produces: `PostLoginDestination`. **Setelah** cabang pending/rejected, akun yang `can('submissions.create')` dengan profil ada tapi belum lengkap → `'profile.complete'` (UX flow diagram, W63).
- Produces: `ProfileCompletionData { list<string> $missing }` (label seperti `OwnProfileData`). `DashboardOverviewData::$profileCompletion: ?ProfileCompletionData` bernilai non-null hanya bila `can('submissions.create')`, profil ada, dan profil belum lengkap.
- Produces: TS `ProfileCompletePageProps { profile: OwnProfile; next: 'submission' | null }`, `DashboardIndexPageProps.profileCompletion: { missing: string[] } | null`. `quickActions(permissions: string[], profileIncomplete: boolean): QuickAction[]`. `QuickAction` mendapat `disabledReason?: string`. Bila `profileIncomplete`, aksi "Buat Pengajuan Baru" mendapat `disabledReason: 'Lengkapi profil Anda sebelum membuat pengajuan.'` dan `QuickActions` merendernya sebagai tombol nonaktif dengan alasan itu.
- Isi `CompletePage` (frame 01-06):
  - `PageHeader` "Lengkapi Profil" / "Lengkapi profil Anda terlebih dahulu untuk dapat membuat pengajuan.". Kolom sempit (`max-w-2xl`), lalu banner info "Data dasar berasal dari registrasi dan tidak dapat diubah di halaman ini.".
  - `SectionCard` "Data Registrasi" (`InfoGrid`: Nama, NIDN [`—` bila null], Organisasi).
  - `SectionCard` "Data Tambahan": Nomor Telepon (placeholder "08xx-xxxx-xxxx", wajib), Bidang Keahlian (input teks, wajib, W62), Foto Profil (unggah, hint "JPG/PNG maks 2MB.", wajib bila belum ada foto; bila sudah ada tampilkan "Foto tersimpan" dan unggahan opsional). Nilai yang sudah ada diisi otomatis.
  - Tombol "Simpan Profil" (POST `profile.complete.store` + `next`, `forceFormData`) dan "Nanti Saja" (ghost, link `dashboard`).
  - `<!-- BR-IAM-03 — ketiganya wajib -->` banner peringatan, **hanya bila** `0 < missing.length < 3`: "`{n}` dari 3 data tambahan belum terisi. Profil dihitung lengkap hanya kalau ketiganya ada." (teks frame IAM-VERIFY-12).
- Isi banner dasbor (frame IAM-VERIFY-04): `AppBanner` tone warning "Lengkapi profil Anda sebelum membuat pengajuan." + link "Lengkapi Profil" → `profile.complete`. Diletakkan sebelum banner antrian, di samping banner biodata yang masih ada sampai Task 4.

- [ ] **Step 1: Tulis test yang gagal.** `ProfileCompletionTest`:
  - `it('Lengkapi Profil merender kontrak bagi profil belum lengkap')` → `component('Profile/CompletePage')->where('next', null)`.
  - `it('profil lengkap atau tanpa profil dialihkan ke Profil Saya')`.
  - `it('akun pending dan rejected tetap bisa melengkapi profil')` → dataset dua status, 302 dan `isComplete()` true. Komentar: BR-IAM-04, IAM-VERIFY-06.
  - `it('melengkapi profil tidak mengubah status verifikasi')` → status pending tetap pending, dan `nidn`/`organization_id` yang dikirim diabaikan.
  - `it('foto wajib bila belum ada, opsional bila sudah ada')`.
  - `it('next=submission kembali ke wizard')`.
  - `it('next tak dikenal jatuh ke dasbor')` → `next=https://evil.example` → redirect `dashboard`.
  - `it('profil yang dilengkapi lewat layar ini membuka pembuatan pengajuan')` → `wizardFixture()` + profil `incomplete()`, POST `profile.complete.store`, lalu `StartSubmission::run(...)` menghasilkan `Draft` (komentar: jalur produksi IAM-VERIFY-05).
  - `it('wizard mengalihkan profil belum lengkap ke Lengkapi Profil')` → GET `user.submissions.create` → redirect `route('profile.complete', ['next' => 'submission'])`; profil lengkap → 200 `Submission/WizardPage`.

  `AccountEventNotificationTest`: ubah isi `[IAM-EVENT-05]` menjadi `CompleteProfile::run($budi, new ProfileDetailsInput('0813-9876-5432', 'Data Science'), UploadedFile::fake()->image('budi.jpg'))` dengan `Storage::fake('local')` + `Notification::assertNothingSent()`. Judul dan ID tetap.

  `AccountVerificationTest` (`PostLoginDestination`; test yang ada, termasuk `[IAM-VERIFY-01]` dan "mengalihkan akun terverifikasi ke dashboard" dengan profil lengkap bawaan factory, tetap hijau tanpa disunting):
  - `it('akun terverifikasi berprofil belum lengkap mendarat di Lengkapi Profil')`.
  - `it('[IAM-VERIFY-01] akun pending berprofil belum lengkap tetap mendarat di layar verifikasi')`, menyebut ID karena memperkuat urutan IAM-VERIFY-01 (satu skenario boleh diikat beberapa test).
  - Pemegang `users.manage` tanpa profil → `dashboard`.

  `DashboardPageTest`: `it('profileCompletion menyebut kekurangan bagi pembuat pengajuan')` (`partiallyComplete()` → `['Bidang Keahlian','Foto Profil']`), `it('profileCompletion null bila lengkap atau tanpa submissions.create')`.

  `ProfileCompletePage.spec.ts`:
  - `it('[IAM-VERIFY-12] banner menyebut data tambahan yang belum terisi')` → `missing` 2 → teks "2 dari 3 data tambahan belum terisi".
  - `it('banner sebagian tidak tampil bila ketiganya kosong')`.
  - `it('Data Registrasi read-only dan Nanti Saja ke dasbor')`.
  - `it('Simpan Profil mengirim next')`.

  `DashboardIndexPage.spec.ts`: `it('[IAM-VERIFY-04] profil belum lengkap: banner dan Buat Pengajuan Baru nonaktif')`, `it('tanpa profileCompletion tidak ada banner profil')`.
- [ ] **Step 2:** Jalankan Pest untuk berkas-berkas di atas + `npx vitest run tests/frontend/pages/ProfileCompletePage.spec.ts tests/frontend/pages/DashboardIndexPage.spec.ts`. Expected: FAIL.
- [ ] **Step 3:** Implementasikan semua item di **Interfaces**, termasuk `quickActions` + `QuickActions.vue` dan pemanggilnya.
- [ ] **Step 4:** Jalankan test di atas + `tests/Feature/Identity tests/Feature/Submission tests/Feature/Dashboard tests/Feature/Auth`. Expected: PASS (IAM-VERIFY-04/05/06/12 tidak disunting dan tetap hijau).
- [ ] **Step 5: Commit** `feat(fe): layar Lengkapi Profil dan pintunya`

### Task 4: Hapus biodata Form-Engine dan gerbang `check_biodata` (W61)

**Files:**
- Delete: `app/Http/Controllers/BiodataController.php`, `app/Actions/Identity/SaveBiodata.php`, `app/Http/Requests/Identity/BiodataRequest.php`, `app/Data/Request/BiodataInput.php`, `app/Queries/Identity/BiodataFormView.php`, `app/Queries/Identity/BiodataStatus.php`, `app/Http/Middleware/CheckBiodata.php`, `app/Queries/Identity/BiodataFiles.php`, `app/Http/Controllers/Identity/VerificationFileController.php`, `resources/js/Pages/User/Biodata/IndexPage.vue`, `tests/Feature/BiodataFormPayloadTest.php`, `tests/Feature/CheckBiodataGateTest.php`, `tests/Feature/BiodataSubmissionStatusTest.php`, `tests/Feature/Identity/SaveBiodataTest.php`, `tests/Feature/Identity/VerificationFileTest.php`
- Modify:
  - `routes/web.php`: hapus `user.biodata.submit`; `user.biodata.index` → `Route::redirect('/user/biodata', '/profile')`, komentar W46/W61 "sampai F99". Lepas `check_biodata` dari `/dashboard` dan grup `user.*`, gabungkan grup `user.*` bila jadi identik, dan hapus komentar W33 yang tak lagi relevan.
  - `routes/web.php`: hapus juga `admin.verifications.files.show` beserta `use VerificationFileController`, dan sesuaikan komentar "berkas biodata (W58)" di grup verifikasi.
  - `app/Queries/Identity/VerificationQueue.php`: hapus pemanggilan `BiodataFiles::forProfiles` dan parameter `$files` di `cards()`.
  - `app/Data/View/Identity/VerificationCardData.php`: hapus properti `files` dan parameter ketiga `fromModel`.
  - `resources/js/types/identity.ts`: hapus `files` dari kartu verifikasi. `resources/js/Components/Identity/VerificationCard.vue`: hapus daftar berkas (baris `card.files`).
  - `bootstrap/app.php`: hapus alias `check_biodata`.
  - `app/Http/Middleware/HandleInertiaRequests.php`: hapus shared prop `biodataStatus`.
  - `resources/js/types/dashboard.ts`: hapus `BiodataStatusFlags`.
  - `resources/js/Pages/Dashboard/IndexPage.vue`: hapus banner biodata dan `usePage` generik-nya.
  - Test: `tests/frontend/pages/DashboardIndexPage.spec.ts` (hapus dua test biodata), `tests/Feature/Dashboard/DashboardPageTest.php` (test "biodata pending" diganti `it('dasbor tidak lagi mengirim biodataStatus')`), `tests/Feature/Announcement/AnnouncementReaderPagesTest.php` (hapus test "markRead tidak dihadang gerbang biodata"), `tests/Feature/Identity/VerificationQueueContractTest.php` (`->where('files', [])` → `->missing('files')`), `tests/frontend/pages/VerificationIndexPage.spec.ts` (hapus `files: []` di fixture `card()` dan test "berkas biodata tampil sebagai tautan unduh bila ada"), `quality-legacy.txt` (hapus `resources/js/Pages/User/Biodata/IndexPage.vue`), `quality-budget.txt` (`max.legacy.files` −1, atau lebih bila penghapusan membebaskan berkas lain, lihat jebakan D1/I1).
- **Tetap:** `FormType::BIODATA_ID` + baris seeder-nya (id jenis lain tidak bergeser), `HasApprovedSubmission` beserta test `biodata yang disetujui tidak menyalakan hasApprovedSubmission`, dan `FieldAnswerWriter` (dipakai jawaban pengajuan).

**Interfaces:**
- Consumes: banner profil dasbor (Task 3) menggantikan banner biodata.
- Produces: tidak ada simbol baru. `rg -n "biodataStatus|CheckBiodata|check_biodata|BiodataStatus|SaveBiodata|BiodataFormView|BiodataFiles|VerificationFileController|verifications.files|user.biodata.submit|biodata-uploads" app routes resources/js tests bootstrap` kosong (kecuali `resources/js/ziggy.js` yang basi, jangan diregenerasi). `card.files` dan `files:` tidak tersisa di `Components/Identity`/`types/identity.ts`.

- [ ] **Step 1: Tulis test yang gagal.** `DashboardPageTest`: `it('dasbor tidak lagi mengirim biodataStatus')` → `->missing('biodataStatus')`. `tests/Feature/ProfileTest.php`: `it('tautan biodata lama dialihkan ke Profil Saya')` → GET `/user/biodata` → redirect `/profile`. `it('pengguna role Mahasiswa tanpa biodata tidak dialihkan')` → user ber-role `Mahasiswa` dengan form biodata aktif → GET `user.submissions.index` dengan `submissions.view-own` → 200 (tiga gerbang DDD, bukan empat). `VerificationQueueContractTest`: `it('kartu verifikasi tidak membawa berkas identitas')` → `->missing('pending.0.files')`. `it('route berkas biodata tidak ada lagi')` → `expect(Route::has('admin.verifications.files.show'))->toBeFalse()`.
- [ ] **Step 2:** Jalankan test-test itu. Expected: FAIL.
- [ ] **Step 3:** Hapus dan ubah berkas sesuai daftar. Jalankan `rg` di **Interfaces**.
- [ ] **Step 4:** Jalankan `composer run quality` dan `npm run quality` (Pest serial di container). Expected: PASS, termasuk `FrontendRouteNamesTest`, `QualityBudget`, dan `AuditCoverageTest`.
- [ ] **Step 5: Commit** `refactor: hapus biodata Form-Engine, berkas KTP, dan gerbang check_biodata`

### Task 5: Penutup irisan

**Files:**
- Modify: `docs/design/fe-v2-design.md` (§8 P1 → `☑`, kolom BE + rencana; §9 baris W61–W66 dari bagian "Keputusan penulis rencana", W61 sebagai keputusan pemilik 2026-11-02; kolom keputusan W58 ditambah "**Dihapus P1 (W61):** aplikasi tidak menyimpan KTP"; W15 ditambah catatan bahwa unggahan biodata tidak ada lagi; baris I1-2 di §8 tidak disunting), `resources/js/ziggy.js` **tidak** diregenerasi.

- [ ] **Step 1:** Jalankan pemeriksaan browser (resep di memory `fe-v2-s1-carryover`, DB `simpas_browser`). Peneliti hasil seeder dengan profil dikosongkan login → mendarat di Lengkapi Profil → "Nanti Saja" → dasbor menampilkan banner dan "Buat Pengajuan Baru" nonaktif → isi telepon + keahlian + unggah PNG → "Simpan Profil" → dasbor tanpa banner. Kemudian Profil Saya → ganti foto (avatar berubah) → ubah nama → ubah kata sandi → logout → login dengan kata sandi baru. Terakhir `/user/biodata` → `/profile`. Simpan skrip di `.superpowers/sdd/2026-11-02-fe-p1-profile/`.
- [ ] **Step 2:** `composer run quality` + `npm run quality`. Laporkan hasilnya apa adanya.
- [ ] **Step 3:** Perbarui §8 dan §9.
- [ ] **Step 4: Commit** `feat(fe): irisan P1 profil selesai`
