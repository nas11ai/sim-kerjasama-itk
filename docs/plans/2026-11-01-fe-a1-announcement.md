# FE v2 — Irisan A1: Pengumuman Baca & Kelola — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Operator menerbitkan, menyunting, dan menarik pengumuman dari UI v2 (`Announcement/ManageIndexPage`, `Announcement/FormPage`); pengumuman privat bisa dipersempit per peran; semua pengguna login membacanya (`Announcement/IndexPage`, `Announcement/ShowPage`) dengan penanda baca dan jumlah belum dibaca di sidebar; tamu membaca yang publik lewat panel di halaman login. Isi pengumuman ditulis dengan editor rich text (K6).

**Architecture:** Satu definisi visibilitas, `Queries/Announcement/VisibleAnnouncements`, diperluas dengan filter peran lewat pivot baru `announcement_roles`; setiap jalur pembaca (daftar, detail, penanda baca, hitungan belum dibaca, panel login) lewat Query itu. Kelola pindah dari `can:users.manage` ke permission baru `announcements.manage` (pola K1). `AnnouncementController` tetap satu controller tipis; bacaan dipindah ke Query + `Data` class di `app/Data/View/Announcement/`. Setiap pasangan dikerjakan FE dulu, baru BE (Task 2→3, 4→5). Alasannya, `config/inertia.php` bagian testing memasang `ensure_pages_exist => true`, jadi test kontrak BE baru lulus setelah berkas halamannya ada. Halaman legacy dihapus di task BE yang berhenti merendernya, supaya `FrontendRouteNamesTest` hijau di setiap commit (§7).

**Tech Stack:** Laravel 13 / PHP 8.4, spatie/laravel-permission, spatie/laravel-data, Inertia v3 + Vue 3 `<script setup>` + TS, shadcn-vue, Ziggy, Pest, Vitest. Baru (K6, K6a): Tiptap (`@tiptap/vue-3`, `@tiptap/starter-kit`, `@tiptap/pm`, `@tiptap/extension-image`) dan `ezyang/htmlpurifier` sebagai dependency langsung.

**Spec:** `docs/design/fe-v2-design.md` §4.7 (aturan pengumuman — satu-satunya acuan), baris A1 di §8, K3/K5/W6/W15 di §9. Pengumuman **tidak punya** BR, skenario BDD, maupun frame wireframe; komentar kode merujuk `fe-v2-design §4.7`, bukan kode BR karangan.

## Kesesuaian dengan DDD dan BDD (diperiksa 2026-11-01)

- `docs/requirement-analysis/ddd/` dan `bdd/` tidak menyebut pengumuman sama sekali (grep "pengumuman|announcement" kosong). Karena itu irisan ini **tidak menambah skenario BDD**; `pending.txt`/`uncovered.txt` tidak berubah. Menulis skenario untuk perilaku yang tidak ada di DDD akan membuat aturan bisnis baru, dan itu non-tujuan §2.
- Aturan lintas konteks yang **tetap mengikat**:
  - **BR-RPT-09 / [RPT-AUDIT-07]**: setiap Action menulis audit (`AuditCoverageTest`). Publish/Revise/Retract sudah beraudit; target peran ditambahkan ke nilai lama/baru (BR-RPT-05: nilai sebelum-sesudah).
  - **BR-IAM-09**: permission additive. `announcements.manage` ditambah ke peran `operator`, dan `admin` mendapatkannya lewat katalog penuh.
- Satu selisih yang disengaja: katalog permission DDD (`ddd/generic/02_identity_access.md` §Spatie Permission Design) tidak memuat `announcements.manage`. Selisih ini sudah diputuskan pemilik (K5, 2026-10-25), dan DDD tidak disunting. `PermissionSeederTest` memakai konstanta seeder, jadi otomatis ikut.
- Penanda baca **tidak** diaudit dan tidak lewat Action. Ini preseden yang sama dengan `read_at` notifikasi (`NotificationController::read`, BR-NOT-04): penanda baca bukan state domain, dan mengauditnya akan membanjiri jejak audit.

## Keputusan pemilik (dicatat ke §4.7 dan §9 di Task 6)

- **K6** — Pemilik, 2026-11-01: isi pengumuman ditulis dengan **editor rich text**, menggantikan teks biasa. Keputusan ini mengubah paragraf "Isi" §4.7 dan mencoret "rich text" dari "Di luar cakupan". Bentuknya ditetapkan penulis rencana:
  - **Editor:** Tiptap 3 (headless, cocok dengan Vue 3 + shadcn-vue), versi 3.x stabil terbaru yang dirilis minimal dua minggu lalu, mengikuti gaya caret di `package.json`. Hanya dimuat di `FormPage`: halaman Inertia di-resolve lazy (`import.meta.glob` di `app.ts`), jadi pembaca dan tamu tidak mengunduhnya.
  - **Format simpan:** HTML yang sudah disaring, di kolom `announcements.content` yang sama (tanpa migration kolom).
  - **Batas kepercayaan ada di server.** `AnnouncementHtml::sanitize()` (HTMLPurifier) dijalankan saat menulis (Publish/Revise) **dan** saat membaca (`AnnouncementDetailData`), sehingga baris lama di DB dev yang ditulis tanpa saringan pun tidak lolos mentah. Daftar izin: `p, br, strong, em, u, s, h2, h3, ul, ol, li, blockquote, a[href]` (+ `img[src|alt]`, K6a); skema URI `http`, `https`, `mailto`; tautan diberi `target="_blank" rel="noopener noreferrer"`. Tabel, warna, dan atribut `style` tidak diizinkan; gambar hanya lewat unggahan K6a.
  - **Render:** satu komponen `Components/Announcement/RichTextContent.vue` adalah satu-satunya pemakai `v-html` di v2, dan ia hanya menerima `contentHtml` dari backend. Daftar, panel login, dan kutipan memakai teks polos `AnnouncementHtml::plainText()`.
  - **HTMLPurifier** sudah terpasang v4.19.0 sebagai dependency transitif `phpoffice/phpspreadsheet`. Ia dijadikan dependency langsung (`^4.19`, versi terkunci tidak berubah) supaya kode ini tidak bergantung pada paket lain.
  - Data dev lama berupa teks biasa akan kehilangan baris barunya saat dirender sebagai HTML. Aplikasi belum live, jadi tidak ada migrasi isi.
- **K6a** — Pemilik, 2026-11-01 (amandemen K6, saat eksekusi): editor pengumuman juga menyisipkan **gambar** dan tautan, "dalam batas kepatuhan keamanan dan praktik terbaik". Bentuknya:
  - **Gambar hanya lewat unggahan, tidak pernah lewat URL.** Endpoint `POST admin/announcements/images` (`admin.announcements.images.store`, grup `announcements.manage`) menerima satu berkas `image`: `File::image()` (SVG ditolak) jenis jpg/jpeg/png/webp, maks 2 MB, dimensi maks 4096×4096. Action `UploadAnnouncementImage` **menyandi ulang** gambar dengan GD (`ext-gd` sudah wajib lewat phpspreadsheet), sehingga metadata EXIF/GPS dan muatan poliglot ikut terbuang, lalu menyimpannya dengan nama acak di disk `public` (`announcements/inline/`, W15), mengaudit "Gambar pengumuman diunggah", dan menjawab JSON `{ url }` berupa path relatif `/storage/announcements/inline/<acak>.<ext>`. Frontend memanggilnya lewat `useHttp` Inertia v3 (tanpa axios).
  - **Saringan server.** Daftar izin K6 ditambah `img[src|alt]`. `src` hanya lolos bila cocok `^/storage/announcements/inline/[A-Za-z0-9]+\.(jpg|jpeg|png|webp)$` (filter URI HTMLPurifier untuk URI tertanam). URL luar (hotlink/piksel pelacak), `data:`, dan `javascript:` dibuang. Path relatif tetap sah walau `APP_URL` berganti.
  - **Tautan** tetap K6: `http`/`https`/`mailto`, `target="_blank" rel="noopener noreferrer"`.
  - **Daur hidup.** Gambar sisipan yang dibuang revisi atau milik pengumuman yang ditarik dihapus dari disk sesudah commit, kecuali masih dirujuk pengumuman lain. Unggahan yang tidak pernah disimpan ke pengumuman tetap tertinggal (tercatat di audit), dan pembersihannya ditunda.
  - Dependency tambahan: `@tiptap/extension-image` (versi sama dengan paket Tiptap lain). Tiptap StarterKit v3 sudah memuat Link dan Underline.

## Keputusan penulis rencana (dicatat ke §9 di Task 6)

- **W32** — Jumlah belum dibaca dikirim di `navigation.unreadAnnouncements`, bukan `auth.user.unread_announcements` seperti tertulis di §4.7. Alasannya, `navigation` sudah menjadi tempat flag sidebar yang bergantung pada data (§4.4), dan `AppSidebar` sudah membacanya.
- **W33** — `user.announcements.markRead` keluar dari grup `check_biodata` (nama dan path tetap). Kalau tetap di sana, POST dari halaman detail milik pengguna yang biodatanya belum lengkap dialihkan ke form biodata, sehingga ia terlempar dari pengumuman yang sedang dibaca. Daftar `user.announcements.index` tetap di balik gerbang biodata (`CheckBiodataGateTest` memakainya).
- **W34** — "Berlaku sampai" adalah tanggal (`Y-m-d`) yang disimpan sebagai pukul 23:59:59 hari itu, mengikuti pola tanggal tenggat di Perpanjang Tenggat. Jam tidak dipilih.
- **W35** — Layar detail khusus kelola (`admin.announcements.show`) dihapus. Pengelola membaca isinya di `FormPage`, dan pembaca memakai `Announcement/ShowPage`. Route `show` dikeluarkan dari resource.
- **W36** — Target peran hanya mencocokkan lima peran DDD. Akibatnya Super Admin dan role legacy `Admin` tidak ikut tercakup target `admin`, dan pengumuman privat bertarget tidak muncul di jalur pembaca mereka kecuali mereka memegang peran itu. Mereka tetap melihatnya di layar kelola. Ini mengikuti teks §4.7 apa adanya; tidak ada pengecualian Super Admin di Query.
- `WelcomePage` dan `PublicAnnouncementFeed` tetap hidup sampai F99 (baris F99 §8). Panel login memakai Query baru `PublicAnnouncementPanel`; keduanya membaca `VisibleAnnouncements::run(null)`.

## Global Constraints

- Berkas Vue baru ≤ 250 baris, sasaran < 150; PHP baru ≤ 200, method ≤ 40 baris, CC ≤ 8, ≤ 4 parameter; tanpa blok duplikat 6 baris dengan berkas mana pun termasuk legacy (jangan menyalin `Announcements/*` — tulis dari §4.7).
- Baris pertama `<script setup>` halaman baru: `// Frame: — (fe-v2-design §4.7)` (tidak ada frame wireframe).
- `v-html` **hanya** di `RichTextContent.vue` (satu `eslint-disable-next-line vue/no-v-html` dengan komentar yang menyebut `AnnouncementHtml::sanitize`). Di tempat lain isi tampil sebagai teks polos.
- Lampiran tetap disk `public` (W15), jpg/png/pdf, maks 2 MB (`AnnouncementRequest`).
- Peran yang bisa dipilih: `researcher` "Dosen", `reviewer_internal` "Reviewer Internal", `reviewer_external` "Reviewer Eksternal", `operator` "Operator LPPM", `admin` "Admin" (label dari `02_ubiquitous_language.md` §Actors), dikirim backend. Frontend tidak memeriksa nama peran.
- Identifier Inggris; komentar Indonesia, prosa maks 3 baris. TS tanpa `any`/`console.log`/cast; PHPStan tanpa `@phpstan-ignore`, baseline, `assert()`, inline `@var`, cast.
- PostgreSQL: pencarian `whereLike` (case-insensitive).
- Mutasi lewat `router`/`useForm`, tanpa `axios`. Dependency baru hanya yang disebut K6. `composer require` dijalankan di container (host PHP 8.5 gagal, AGENTS.md). Migration hanya ke DB dev/test.
- Gate: `composer run quality` + `npm run quality`. Pest di container (lihat AGENTS.md; di WSL pakai image `simpas-ci:8.4`, Docker Desktop harus menyala):
  `docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 ./vendor/bin/pest <path>`.
- Commit satu baris, Indonesia, conventional, **tanpa** trailer `Co-Authored-By`.

## Review Focus

1. **Pengguna dengan biodata belum lengkap membuka detail** (dari tautan langsung atau panel login setelah masuk): penanda baca tercatat dan ia tetap di halaman itu, tidak dilempar ke form biodata (W33). → Task 5 `markRead tidak dihadang gerbang biodata`.
2. **markRead pada pengumuman yang tidak terlihat** (privat bertarget peran lain, kedaluwarsa, atau id tebakan) → 404 tanpa baris `announcement_users`. Hari ini endpoint itu menandai apa saja. → Task 5 `markRead pada pengumuman tak terlihat dijawab 404`.
3. **Pengumuman privat bertarget direvisi menjadi publik**: target perannya hilang dari pivot. Pengumuman publik yang dikirim dengan target ditolak di field `target_roles`. → Task 3 `revisi ke publik melepas target peran`, `target peran pada pengumuman publik ditolak`.
4. **Kedaluwarsa "hari ini"**: tetap terlihat sampai 23:59:59, dan hilang lewat dari itu (W34). → Task 1 `terlihat sampai akhir hari kedaluwarsanya`, Task 3 `tanggal kedaluwarsa disimpan sampai akhir hari`.
5. **Isi berbahaya dikirim langsung ke endpoint**, melewati editor: `<script>`, `<img onerror>`, `<a href="javascript:…">`, atau atribut `style`. Semuanya dibuang sebelum disimpan dan sebelum dirender, sementara format yang diizinkan tetap utuh. Isi yang hanya berupa tag kosong (`<p></p>`, keadaan awal editor) ditolak sebagai isi wajib. → Task 3 `markup berbahaya dibuang saat disimpan`, `isi tanpa teks ditolak`; Task 5 `baris lama disaring saat dibaca`.
6. **Gambar berbahaya atau dari luar (K6a)**: SVG, berkas teks berekstensi `.png`, gambar > 4096 px, `<img src="https://evil…">`, `<img src="data:…">`, dan `src` dengan `..` semuanya ditolak atau dibuang; metadata EXIF hilang setelah unggah; pengguna tanpa `announcements.manage` mendapat 403. → Task 3 `unggah gambar …`.

---

### Task 1: Target peran dan permission kelola (BE)

**Files:**
- Create: `database/migrations/2026_11_01_100000_create_announcement_roles_table.php`, `app/Support/Announcement/AnnouncementAudience.php`
- Modify: `app/Models/Announcement.php`, `app/Queries/Announcement/VisibleAnnouncements.php`, `database/seeders/PermissionSeeder.php`
- Test: `tests/Feature/Announcement/AnnouncementAudienceTest.php`

**Interfaces:**
- Produces: tabel `announcement_roles` (`announcement_id` FK `announcements` cascade, `role_id` FK `roles` cascade, unik berpasangan, tanpa timestamps).
- Produces: `Announcement::targetRoles(): BelongsToMany<Role, $this>` (Spatie `Role`, pivot `announcement_roles`); `Announcement::syncTargetRoles(list<string> $roleNames): void`, yang memetakan nama ke id peran guard `web` lalu `sync`. Method ini ada di Model, bukan di Support, karena `SupportPurityTest` melarang Support menulis.
- Produces: `AnnouncementAudience` (murni, tanpa query): `ROLE_LABELS: array<string, string>` (lima peran di Global Constraints, urutan itu); `options(): list<array{value: string, label: string}>`; `describe(string $type, list<string> $roleNames): string`, yang mengembalikan "Publik" untuk `public`, "Semua pengguna login" untuk privat tanpa target, dan label peran dipisah koma untuk privat bertarget.
- Produces: `VisibleAnnouncements::run(?User)`. Pengguna login melihat pengumuman yang **tidak bertarget** atau yang bertarget salah satu id peran miliknya (`whereDoesntHave('targetRoles')` ATAU `whereHas` dengan `roles.id` ∈ peran penanya). Tamu tetap hanya melihat `public`. Komentar `// fe-v2-design §4.7 — privat dipersempit per peran; cukup cocok satu.`
- Produces: `PermissionSeeder::PERMISSIONS` + `'announcements.manage'`; `ROLE_PERMISSIONS['operator']` + `'announcements.manage'`.

- [ ] **Step 1: Tulis test yang gagal.** `privat tanpa target terlihat semua pengguna login`; `privat bertarget hanya terlihat pemegang salah satu peran` (target `researcher`+`operator`: peneliti melihatnya, `reviewer_external` tidak); `pengguna dua peran cukup cocok satu`; `tamu tidak melihat privat bertarget`; `Super Admin tanpa peran target tidak melihatnya di jalur pembaca` (W36); `terlihat sampai akhir hari kedaluwarsanya` (`expired_at` = hari ini 23:59:59; `Carbon::setTestNow` 23:59:00 → ada, besok 00:00:01 → tidak ada); `peran yang bisa dipilih adalah lima peran DDD` (`array_keys(AnnouncementAudience::ROLE_LABELS)` = kunci `PermissionSeeder::ROLE_PERMISSIONS`); `describe` untuk tiga kasus di Interfaces; `operator memegang announcements.manage` (seed, `Role::findByName('operator')->hasPermissionTo(...)`). `AnnouncementVisibilityTest` dan `PermissionSeederTest` tetap hijau tanpa diubah.
- [ ] **Step 2: Jalankan** `… pest tests/Feature/Announcement/AnnouncementAudienceTest.php` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces. Jalankan migration hanya ke DB test/dev.
- [ ] **Step 4: Jalankan** `tests/Feature/Announcement`, `tests/Feature/PermissionSeederTest.php`, `tests/Feature/DatabaseSeederPermissionTest.php`, `tests/Feature/Inertia/SharedNavigationPropsTest.php`, `tests/Feature/ModelSchemaDriftTest.php`, `tests/Unit/Architecture` → PASS.
- [ ] **Step 5: Commit** `feat(be): target peran pengumuman dan permission kelola`

### Task 2: `ManageIndexPage` dan `FormPage` (FE)

**Files:**
- Create: `resources/js/types/announcement.ts`, `resources/js/Pages/Announcement/{ManageIndexPage,FormPage}.vue`, `resources/js/Components/Announcement/{ManagedAnnouncementTable,AudienceFields,AttachmentFields,RichTextEditor}.vue`
- Modify: `package.json`, `package-lock.json` (Tiptap, K6)
- Test: `tests/frontend/pages/AnnouncementManageIndexPage.spec.ts`, `tests/frontend/pages/AnnouncementFormPage.spec.ts`

**Interfaces:**
- Produces (TS, dicerminkan `Data` class Task 3 — bentuknya ditetapkan di Interfaces Task 3): `AnnouncementFile`, `ManagedAnnouncementRow`, `AnnouncementForm` (data dari backend), `AnnouncementManageIndexPageProps`, `AnnouncementFormPageProps`; ulang pakai `Paginated`, `FilterOption`.
- `ManagedAnnouncementTable` props `{ rows: ManagedAnnouncementRow[]; filtered: boolean }`, emit `retract(row)`. Kolom: Judul, Sasaran (`audienceLabel`), Berlaku sampai (`formatDate`, atau "Tanpa batas"; badge "Kedaluwarsa" bila `expired`), Dibuat, aksi "Sunting" (→ `admin.announcements.edit`) dan "Hapus". Kosong → `EmptyState` "Belum ada pengumuman." (atau "Tidak ada pengumuman yang cocok." bila `filtered`).
- `ManageIndexPage`: `PageHeader` "Kelola Pengumuman" / "Pengumuman yang diterbitkan untuk pengguna dan tamu.", aksi "Buat Pengumuman" → `admin.announcements.create`; `useFilters('admin.announcements.index', filters)` + `Input` "Cari judul..."; `PaginationBar`; "Hapus" membuka `ConfirmDialog` ("Tarik pengumuman ini? Lampirannya ikut dihapus.") → `router.delete(route('admin.announcements.destroy', id), { preserveScroll: true })`.
- `AudienceFields` props `{ roleOptions: FilterOption[]; errors }`, `v-model:type` + `v-model:targetRoles`. `ui/radio-group` Publik/Privat; checkbox peran hanya tampil untuk Privat, dengan hint "Kosongkan untuk semua pengguna login."; beralih ke Publik mengosongkan `targetRoles`.
- `AttachmentFields` props `{ existing: AnnouncementFile[]; errors }`, `v-model:files` (`File[]`) + `v-model:deletedFiles` (`number[]`). Lampiran lama punya tombol "Lepas"/"Batalkan"; input `accept=".jpg,.png,.pdf" multiple` dengan hint "JPG, PNG, atau PDF, maks. 2 MB per berkas"; galat `files` atau `files.N` tampil di sini.
- `RichTextEditor` props `{ id: string; invalid?: boolean }`, `v-model` string HTML. Tiptap `useEditor` + `EditorContent` dengan `StarterKit` (heading level 2–3; `code`, `codeBlock`, `horizontalRule` dimatikan, supaya editor tidak menghasilkan tag yang dibuang saringan) dan Link (`openOnClick: false`, protokol http/https/mailto). Toolbar `Button variant="ghost" size="icon"` dengan `aria-label` dan `aria-pressed`: Tebal, Miring, Garis bawah, Coret, Judul 2, Judul 3, Daftar berbutir, Daftar bernomor, Kutipan, Tautan, Urungkan, Ulangi. Tautan memakai `Popover` + `Input` URL, bukan `prompt()` native (§2). `editor.destroy()` di `onBeforeUnmount`. Komponen ini wiring presentasi Tiptap: perilakunya dicek di browser (Task 6), bukan Vitest, karena ProseMirror tidak berjalan andal di jsdom.
- K6a — `RichTextEditor` mendapat tombol "Gambar" (Tiptap `Image`, `allowBase64: false`, `inline: false`): memilih berkas lewat `<input type="file" accept="image/jpeg,image/png,image/webp">` tersembunyi, meminta teks alternatif di `Popover` yang sama polanya dengan Tautan, lalu memanggil composable `useInlineImageUpload(routeName)`. Composable itu membungkus `useHttp` (`post` multipart `{ image }`), mengembalikan `{ upload(file): Promise<string | null>, uploading, error }`, dan galat validasi `image` tampil di bawah editor. Gambar disisipkan dengan `src` dari jawaban server, tidak pernah dari URL ketikan.
- `FormPage`: judul "Buat Pengumuman"/"Sunting Pengumuman", back → `admin.announcements.index`; `useForm({ title, content, type, target_roles, expired_at, files, deleted_files })`; isi memakai `RichTextEditor`; "Berlaku sampai" `Input type="date"` dengan hint "Kosongkan bila tanpa batas. Berlaku sampai pukul 23:59."; buat → `form.post(route('admin.announcements.store'))`; sunting → `form.transform(d => ({ ...d, _method: 'put' })).post(route('admin.announcements.update', id))` (multipart PUT tidak dibaca PHP).

- [ ] **Step 1: Tulis Vitest yang gagal.** ManageIndex: `daftar kosong menampilkan "Belum ada pengumuman."`; `baris kedaluwarsa diberi badge Kedaluwarsa`; `hapus meminta konfirmasi lalu router.delete`. Form: `pilihan peran hanya tampil untuk privat`; `beralih ke publik mengosongkan target peran`; `sunting mengirim POST dengan _method put`; `melepas lampiran lama menambah deleted_files`; `galat files.0 tampil di field lampiran`; `isi dari editor ikut terkirim sebagai content` (`RichTextEditor` di-stub, emit `update:modelValue` `<p><strong>A</strong></p>` → payload `content` sama); `galat content tampil di bawah editor`. Composable (`tests/frontend/composables/useInlineImageUpload.spec.ts`, `useHttp` di-mock): `unggahan sukses mengembalikan url dari server`; `galat validasi image ditampilkan dan hasilnya null`.
- [ ] **Step 2: Pasang dependency** `npm install @tiptap/vue-3@3.32.0 @tiptap/starter-kit@3.32.0 @tiptap/pm@3.32.0 @tiptap/extension-image@3.32.0` (3.32.0 = rilis 3.x terbaru yang berumur ≥ 2 minggu pada 2026-11-01). Cek `git diff package.json`: hanya empat baris itu yang bertambah.
- [ ] **Step 3: Jalankan** `npx vitest run tests/frontend/pages/AnnouncementManageIndexPage.spec.ts tests/frontend/pages/AnnouncementFormPage.spec.ts` → FAIL.
- [ ] **Step 4: Implementasi** sesuai Interfaces.
- [ ] **Step 5: Jalankan** `npx vitest run tests/frontend` + `npx vue-tsc --noEmit` + `npx eslint resources/js/Pages/Announcement resources/js/Components/Announcement` + `npm run build` (bundel Tiptap hanya masuk chunk `FormPage`) → PASS.
- [ ] **Step 6: Commit** `feat(fe): layar kelola pengumuman`

### Task 3: Kontrak layar kelola (BE)

**Files:**
- Create (K6a): `app/Http/Controllers/AnnouncementImageController.php`, `app/Http/Requests/Announcement/AnnouncementImageRequest.php`, `app/Actions/Announcement/UploadAnnouncementImage.php`, `app/Support/Announcement/InlineImageSource.php`, `app/Services/Announcement/AnnouncementInlineImageStore.php`
- Create: `app/Queries/Announcement/ManagedAnnouncementList.php`, `app/Data/View/Announcement/{ManagedAnnouncementRowData,AnnouncementFormData,AnnouncementFileData}.php`, `app/Support/Announcement/AnnouncementHtml.php`
- Modify: `composer.json`, `composer.lock` (`ezyang/htmlpurifier` langsung, K6), `routes/web.php`, `app/Http/Controllers/AnnouncementController.php`, `app/Http/Requests/Announcement/AnnouncementRequest.php`, `app/Data/Request/AnnouncementInput.php`, `app/Actions/Announcement/{PublishAnnouncement,ReviseAnnouncement}.php`, `quality-legacy.txt` (hapus 4 baris), `quality-budget.txt` (`max.legacy.files` 73 → 69)
- Delete: `resources/js/Pages/Announcements/{CreatePage,EditPage,IndexPage,ShowPage}.vue` (grep dulu: tidak ada pengimpor lain; `IndexPage` legacy memakai `admin.announcements.show` yang dihapus di sini)
- Test: `tests/Feature/Announcement/AnnouncementManagePagesTest.php`, `tests/Unit/Support/AnnouncementHtmlTest.php`

**Interfaces:**
- Consumes: `Announcement::syncTargetRoles`, `AnnouncementAudience` (Task 1); halaman `Announcement/ManageIndexPage`, `Announcement/FormPage` (Task 2).
- Produces route: `Route::resource('announcements', …)->except(['show'])`, dengan nama `admin.announcements.*` tetap, dipindah dari grup `can:users.manage` ke grup baru `['auth', 'can:announcements.manage']->prefix('admin')->name('admin.')`. Komentar `// fe-v2-design §4.7 K5 — Operator mengelola pengumuman tanpa users.manage.` Method `show` dihapus dari controller (W35).
- Produces request: `target_roles` = `['array', 'prohibited_if:type,public']`, dengan pesan "Pengumuman publik tidak bisa dibatasi per peran."; `target_roles.*` = `['string', 'distinct', Rule::in(array_keys(AnnouncementAudience::ROLE_LABELS))]`; `expired_at` = `nullable|date_format:Y-m-d`. `toInput()` mengubah tanggal menjadi `Y-m-d 23:59:59` (W34).
- Produces: `AnnouncementHtml` (murni, `final`): `sanitize(string $html): string` (HTMLPurifier dengan daftar izin K6, `Cache.DefinitionImpl` null, instance purifier disimpan statis); `plainText(string $html): string` (tag blok diganti spasi, `strip_tags`, `html_entity_decode`, `Str::squish`). Komentar `// fe-v2-design §4.7 K6 — server adalah batas kepercayaan; v-html hanya menerima keluaran ini.`
- Produces request: `content` = `required|string|max:50000`, ditambah hook `after()` yang menolak isi bila `AnnouncementHtml::plainText(content) === ''`, dengan pesan "Isi pengumuman wajib diisi."
- Produces: Publish/Revise menyimpan `AnnouncementHtml::sanitize($input->content)`. Saringan ada di Action, bukan di request, supaya setiap jalur tulis tersaring.
- Produces: `AnnouncementInput` + `list<string> $targetRoles` (sebelum `$files`). Publish/Revise memanggil `syncTargetRoles`, dan audit `new` (serta `old` untuk Revise) memuat `'target_roles' => list<string>`.
- Produces: `AnnouncementFileData(int $id, string $name, string $url, int $size)`. `url` = `Storage::disk(AnnouncementFileStore::DISK)->url($path)`.
- Produces: `ManagedAnnouncementRowData(int $id, string $title, string $audienceLabel, ?string $expiresAt, bool $expired, string $createdAt, ?string $author)` — ISO 8601; `expired` = `expired_at` tidak null dan ≤ sekarang.
- Produces: `ManagedAnnouncementList::run(string $search): LengthAwarePaginator<int, ManagedAnnouncementRowData>` — semua pengumuman termasuk yang kedaluwarsa, `whereLike('title', "%…%")`, terbaru dulu, 15 per halaman, eager `targetRoles` + `announcementCreator` (tanpa N+1); `implements ChangesNoState`.
- Produces: `AnnouncementFormData(int $id, string $title, string $content, string $type, ?string $expiredOn, list<string> $targetRoles, list<AnnouncementFileData> $files)` + `static of(Announcement): self`. `expiredOn` = `Y-m-d`.
- K6a — Produces route `POST admin/announcements/images` → `AnnouncementImageController` (invokable, grup `announcements.manage`, didaftarkan sebelum resource), request `AnnouncementImageRequest` (`image` = `required` + `File::image()->types(['jpg','jpeg','png','webp'])->max(2048)->dimensions(Rule::dimensions()->maxWidth(4096)->maxHeight(4096))`), Action `UploadAnnouncementImage::run(UploadedFile, User): string` (GD re-encode → `Storage::disk('public')->put('announcements/inline/'.Str::random(40).'.'.$ext)`, audit subjek = aktor, `new: ['path' => …]`), jawaban `response()->json(['url' => '/storage/…'])`.
- K6a — `AnnouncementHtml`: izin `img[src|alt]`; filter URI tertanam `InlineImageSource` (Support, murni) dengan pola K6a; `inlineImagePaths(string $html): list<string>` (path disk dari `src` yang lolos). Service `AnnouncementInlineImageStore::purgeUnreferenced(list<string> $paths, int $exceptId)` dipanggil Revise (path lama − path baru) dan Retract (semua path), sesudah commit.
- Props `Announcement/ManageIndexPage`: `{ announcements: Paginated<ManagedAnnouncementRowData>, filters: { search: string } }`. Props `Announcement/FormPage`: `{ announcement: AnnouncementFormData | null, roleOptions: list<{value, label}> }`.

- [ ] **Step 1: Tulis test yang gagal.** `Operator tanpa users.manage membuka Announcement/ManageIndexPage` (peran `operator` hasil seed); `peneliti ditolak di index, create, store, edit, update, destroy` (403); `pengumuman kedaluwarsa tetap tampil dengan tanda` (`where('announcements.data.0.expired', true)`); `audienceLabel mengikuti jenis dan target`; `pencarian judul tidak peka huruf` (`search=SEMINAR` menemukan "Seminar Hibah"); `FormPage sunting membawa target peran dan lampiran`; `FormPage buat membawa lima pilihan peran`; `target peran pada pengumuman publik ditolak` (`assertSessionHasErrors('target_roles')`); `peran di luar lima peran DDD ditolak` (`'Admin'` → error `target_roles.0`); `revisi ke publik melepas target peran` (pivot kosong); `audit penerbitan dan revisi memuat target peran` (`properties.new.target_roles`, `properties.old.target_roles`); `tanggal kedaluwarsa disimpan sampai akhir hari` (`2026-12-31` → `2026-12-31 23:59:59`); `route admin.announcements.show tidak terdaftar` (`Route::has` false); `markup berbahaya dibuang saat disimpan` (`<p>Halo<script>x</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">t</a><span style="color:red">s</span></p>`: kolom tersimpan tanpa `script`, `img`, `onerror`, `javascript:`, `style`, dan teks "Halo" tetap ada); `format yang diizinkan tetap utuh` (`<h2>`, `<ul><li>`, `<strong>`, `<a href="https://itk.ac.id">` tersimpan, tautan mendapat `target="_blank"` dan `rel` berisi `noopener`); `isi tanpa teks ditolak` (`<p></p>` → `assertSessionHasErrors('content')`). K6a: `unggah gambar oleh operator menjawab url relatif` (berkas ada di disk `public`, audit tercatat); `unggah gambar ditolak untuk peneliti` (403); `unggah SVG ditolak`; `unggah berkas teks berekstensi png ditolak`; `unggah gambar terlalu besar dimensinya ditolak`; `metadata EXIF dibuang saat unggah` (JPEG ber-EXIF → hasil tanpa segmen `Exif`); `gambar sisipan milik sendiri lolos saringan`; `gambar luar, data URI, dan path traversal dibuang`; `revisi yang membuang gambar menghapus berkasnya`; `penarikan menghapus gambar sisipan yang tak dirujuk pengumuman lain`. Unit `AnnouncementHtmlTest`: `plainText` mengubah `<p>A</p><p>B &amp; C</p>` menjadi `A B & C`. `AnnouncementLifecycleTest` tetap hijau tanpa diubah.
- [ ] **Step 2: Jalankan** `… pest tests/Feature/Announcement/AnnouncementManagePagesTest.php` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces. Mulai dengan `composer require ezyang/htmlpurifier:^4.19` di container `simpas-ci:8.4`; `git diff composer.lock` hanya boleh memindahkan paket itu ke `require` tanpa mengubah versi paket mana pun. Controller tetap FormRequest → Input → Action → redirect; `index`/`create`/`edit` hanya merender Query/Data.
- [ ] **Step 4: Jalankan** `tests/Feature/Announcement`, `tests/Unit/Support`, `tests/Unit/Audit`, `tests/Unit/Architecture` (termasuk `SupportPurityTest`, `FrontendRouteNamesTest`, `QualityBudgetTest`) → PASS.
- [ ] **Step 5: Commit** `feat(be): kontrak layar kelola pengumuman`

### Task 4: Layar baca, panel login, sidebar (FE)

**Files:**
- Create: `resources/js/Pages/Announcement/{IndexPage,ShowPage}.vue`, `resources/js/Components/Announcement/{AnnouncementList,AttachmentList,PublicAnnouncementPanel,RichTextContent}.vue`
- Modify: `resources/js/types/announcement.ts`, `resources/js/types/auth.ts` (`LoginPageProps.announcements`), `resources/js/types/index.d.ts` (`NavigationFlags.unreadAnnouncements: number`), `resources/js/lib/navigation.ts`, `resources/js/Components/app/{AppSidebar,AppSidebarGroup}.vue`, `resources/js/Layouts/AuthLayout.vue`, `resources/js/Pages/Auth/LoginPage.vue`
- Test: `tests/frontend/pages/AnnouncementShowPage.spec.ts`, `tests/frontend/pages/AnnouncementIndexPage.spec.ts`, `tests/frontend/pages/LoginPage.spec.ts`, `tests/frontend/lib/navigation.spec.ts`

**Interfaces:**
- Produces (TS, dicerminkan `Data` class Task 5): `AnnouncementSummary`, `AnnouncementDetail`, `AnnouncementIndexPageProps`, `AnnouncementShowPageProps`.
- `navigation.ts`: `NavItem.countKey?: 'unreadAnnouncements'`. Item "Pengumuman" (`Megaphone`, `user.announcements.index`, `anyPermission: []`, `countKey`) ditaruh di grup pertama setelah Dashboard. Item "Kelola Pengumuman" (`Megaphone`, `admin.announcements.index`, `activePattern: 'admin.announcements.*'`, `['announcements.manage']`) ditaruh di grup Manajemen setelah "Periode Pengajuan" (W6). `AppSidebarGroup` menerima `flags` dan merender `SidebarMenuBadge` bila hitungannya > 0. Fallback `AppSidebar` menjadi `{ hasApprovedSubmission: false, unreadAnnouncements: 0 }`.
- `AuthLayout`: slot opsional `aside` (bila ada: kontainer `max-w-4xl`, grid dua kolom di `md`, panel di kanan) dan prop `wide?: boolean` (`max-w-2xl`, untuk tamu di `ShowPage`).
- `PublicAnnouncementPanel` props `{ announcements: AnnouncementSummary[] }`: judul "Pengumuman", tiap item menaut `announcements.detail` dengan tanggal; **tidak dirender** bila kosong.
- `ShowPage`: `AppLayout` bila `auth.user` ada, selain itu `AuthLayout wide`. Berisi judul, "Diterbitkan {tanggal} oleh {author}", "Berlaku sampai …" bila ada, isi lewat `RichTextContent`, dan `AttachmentList`. `onMounted`: bila `isRead === false` → `router.post(route('user.announcements.markRead', id), {}, { preserveScroll: true, preserveState: true })`, sekali.
- `RichTextContent` props `{ html: string }`: `<div class="announcement-content" v-html="html" />` dengan gaya tipografi scoped untuk `h2, h3, ul, ol, li, blockquote, a, p` (tanpa plugin typography baru).
- `IndexPage`: `PageHeader` "Pengumuman"; `AnnouncementList` (kartu: judul tautan ke `announcements.detail`, badge "Baru" bila `isRead === false`, kutipan, tanggal, jumlah lampiran); kosong → `EmptyState` "Belum ada pengumuman untuk Anda."; `PaginationBar`.

- [ ] **Step 1: Tulis Vitest yang gagal.** ShowPage: `membuka pengumuman belum dibaca mengirim markRead sekali`; `pengumuman sudah dibaca atau dibuka tamu tidak mengirim markRead`; `isi rich text dirender sebagai format` (`contentHtml` `<p><strong>tebal</strong></p>` → `wrapper.find('.announcement-content strong').text()` = "tebal"); `tamu memakai AuthLayout, pengguna login memakai AppLayout`. Index: `pengumuman belum dibaca diberi badge Baru`; `daftar kosong menampilkan pesan`. Login: `panel pengumuman tersembunyi bila kosong`; `item panel menaut ke announcements.detail`. Navigation: `Kelola Pengumuman hanya untuk announcements.manage`; `Pengumuman tampil untuk semua pengguna login`; `badge hanya tampil bila belum dibaca > 0`.
- [ ] **Step 2: Jalankan** `npx vitest run` pada empat berkas di atas → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces.
- [ ] **Step 4: Jalankan** `npx vitest run tests/frontend` + `npx vue-tsc --noEmit` → PASS.
- [ ] **Step 5: Commit** `feat(fe): layar baca pengumuman dan panel login`

### Task 5: Kontrak layar baca, penanda baca, panel login (BE)

**Files:**
- Create: `app/Queries/Announcement/{ReaderAnnouncementList,UnreadAnnouncementCount,PublicAnnouncementPanel}.php`, `app/Data/View/Announcement/{AnnouncementSummaryData,AnnouncementDetailData}.php`
- Modify: `routes/web.php`, `app/Http/Controllers/AnnouncementController.php` (`userIndex`, `detail`, `markAsRead`), `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, `tests/Feature/AnnouncementMarkReadTest.php`, `tests/Feature/Announcement/AnnouncementVisibilityTest.php`, `quality-legacy.txt` (hapus 2 baris), `quality-budget.txt` (`max.legacy.files` 69 → 67)
- Delete: `resources/js/Pages/AnnouncementDetail.vue`, `resources/js/Pages/User/Announcements/IndexPage.vue` (pemakai terakhir `axios` ke `markRead`)
- Test: `tests/Feature/Announcement/AnnouncementReaderPagesTest.php`

**Interfaces:**
- Consumes: `VisibleAnnouncements` (Task 1), `AnnouncementFileData` dan `AnnouncementHtml` (Task 3); halaman `Announcement/IndexPage`, `Announcement/ShowPage` dan `LoginPageProps.announcements` (Task 4).
- Produces: `AnnouncementSummaryData(int $id, string $title, string $excerpt, string $publishedAt, int $attachmentCount, ?bool $isRead)`. `excerpt` = `Str::limit(AnnouncementHtml::plainText($content), 160)`. `isRead` null untuk tamu.
- Produces: `AnnouncementDetailData(int $id, string $title, string $contentHtml, string $publishedAt, ?string $expiresAt, ?string $author, list<AnnouncementFileData> $files, ?bool $isRead)`.
- Produces: `ReaderAnnouncementList::run(User): LengthAwarePaginator<int, AnnouncementSummaryData>` (10 per halaman, terbaru dulu, `withExists` baca + `withCount` lampiran); `UnreadAnnouncementCount::run(User): int`; `PublicAnnouncementPanel::run(): list<AnnouncementSummaryData>` (paling banyak 5, W6). Ketiganya `ChangesNoState` dan dibangun di atas `VisibleAnnouncements`.
- Produces render: `userIndex` → `Announcement/IndexPage` `{ announcements: Paginated<AnnouncementSummaryData> }`; `detail` → `Announcement/ShowPage` `{ announcement: AnnouncementDetailData }` (404 tetap lewat `VisibleAnnouncements`).
- Produces `markAsRead`: route `user.announcements.markRead` (path sama) dipindah ke grup `['auth']->prefix('user')->name('user.')` yang tanpa `check_biodata` (W33). Isinya `abort_unless(VisibleAnnouncements…->exists(), 404)` → `announcementReader()->syncWithoutDetaching([$user->id])` → `back()`. Komentar: penanda baca bukan state domain, tidak diaudit (preseden BR-NOT-04).
- Produces shared prop: `navigation` = `['hasApprovedSubmission' => …, 'unreadAnnouncements' => UnreadAnnouncementCount::run($user)]` (W32).
- Produces login: `Auth/LoginPage` + prop `announcements: list<AnnouncementSummaryData>`.

- [ ] **Step 1: Tulis test yang gagal.** `ShowPage membawa isi, lampiran, dan isRead` (pengguna login: `isRead` false lalu true setelah markRead; tamu: `isRead` null); `markRead pada pengumuman tak terlihat dijawab 404` (privat bertarget `operator`, dibuka peneliti → 404, tanpa baris); `markRead tidak dihadang gerbang biodata` (fixture biodata `not_submitted` seperti `CheckBiodataGateTest` → `->from(route('announcements.detail', $a))` lalu POST → redirect ke detail itu, bukan `user.biodata.index`, dan barisnya tercatat); `markRead dua kali tidak menggandakan baris`; `jumlah belum dibaca hanya menghitung yang terlihat` (`navigation.unreadAnnouncements`: satu bertarget peran lain dan satu kedaluwarsa tidak dihitung); `halaman login membawa paling banyak lima pengumuman publik yang berlaku` (7 publik + 1 privat + 1 kedaluwarsa → 5, semuanya publik); `baris lama disaring saat dibaca` (baris dibuat langsung lewat `Announcement::create` dengan `<p>a</p><img src=x onerror=alert(1)>` → `announcement.contentHtml` tanpa `onerror`/`img`); `kutipan berupa teks polos` (isi `<h2>Judul</h2><p>Isi &amp; lain</p>` → `excerpt` "Judul Isi & lain"). Ubah test lama: `AnnouncementMarkReadTest` dari `assertOk()->assertJson(…)` menjadi `assertRedirect()` (cek nama tabel tetap); `AnnouncementVisibilityTest` memakai komponen `Announcement/IndexPage` dan kunci `isRead`. Test halaman welcome tidak diubah.
- [ ] **Step 2: Jalankan** `… pest tests/Feature/Announcement tests/Feature/AnnouncementMarkReadTest.php` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces.
- [ ] **Step 4: Jalankan** test Step 2 + `tests/Feature/CheckBiodataGateTest.php` + `tests/Feature/Inertia` + `tests/Feature/Auth` + `tests/Unit` (termasuk `FrontendRouteNamesTest`, `QualityBudgetTest`) → PASS.
- [ ] **Step 5: Commit** `feat(be): kontrak layar baca pengumuman`

### Task 6: Catat keputusan, gate penuh, cek browser

**Files:**
- Modify: `docs/design/fe-v2-design.md` (§4.7 paragraf "Isi" ditulis ulang sesuai K6 dan "rich text" dicoret dari "Di luar cakupan"; §8 A1 → `☑` + catatan rencana; baris F99 + `PublicAnnouncementFeed` bersama `WelcomePage`; §9 baris K6 dan W32–W36)

- [ ] **Step 1: Pastikan sisa legacy.** `git grep -n "Announcements/\|AnnouncementDetail" resources/js` hanya menyisakan tautan `Components/AppSidebar.vue` ke `admin.announcements.index` (route masih terdaftar, dibiarkan sampai F99) dan `WelcomePage` ke `announcements.detail`. `max.legacy.files` = 67.
- [ ] **Step 2: Jalankan gate penuh** `composer run quality` (Pest lewat container) + `npm run quality`. Laporkan hasilnya apa adanya. `FrontendRouteNamesTest` dan `QualityBudgetTest` wajib hijau.
- [ ] **Step 3: Cek browser** (resep di memori proyek: DB `simpas_browser`, `migrate:fresh --seed`, `php -S` dari `public/`). Operator membuat pengumuman privat bertarget "Dosen" dengan lampiran PDF; isi memakai judul, daftar, tebal, dan tautan dari toolbar editor, dan tiap tombol toolbar benar-benar mengubah format; peneliti melihat badge 1 → membuka → badge 0 dan formatnya tampil; tautan terbuka di tab baru; reviewer tidak melihatnya dan URL-nya 404; pengumuman publik muncul di panel login dan terbuka untuk tamu.
- [ ] **Step 4: Perbarui** `fe-v2-design.md` sesuai Files, lalu jalankan `npx prettier --check` hanya pada berkas yang disentuh.
- [ ] **Step 5: Commit** `feat(fe): irisan A1 pengumuman selesai`, lalu sarankan session baru untuk irisan berikutnya (D1; dependensinya S2a, S3a, S4, A1 sudah `☑`).
