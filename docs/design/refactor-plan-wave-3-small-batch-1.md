# Gelombang 3 bagian 4 — Fakultas lama dicabut, Ekspor dan gerbang biodata dirapikan

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development`
> (disarankan) atau `superpowers:executing-plans`. Langkahnya memakai checkbox (`- [ ]`).
>
> **Dokumen ini spec sekaligus rencana.** Temuan diukur pada `a11773a`.

**Goal:** Tiga berkas keluar dari `quality-legacy.txt` (`FacultyController`,
`ExportController`, `CheckBiodata`) beserta enam halaman Vue Fakultas/Prodi yang ikut dicabut.
`max.legacy.files` 118 → 109 (dihitung ulang saat penutupan).

**Architecture:** Strangler (ADR 0008). Layar yang menyunting data mati dicabut; dua berkas
sisanya dibentuk ulang tanpa mengubah perilaku kecuali yang disebut di temuan.

**Spec induk:** [`refactor-plan.md`](refactor-plan.md) §5 gelombang 3

**Status:** belum dikerjakan.

---

## Temuan — diverifikasi di kode

**F1 — Layar Fakultas dan Program Studi menyunting tabel yang sudah tidak dibaca.** DDD
(`ddd/README.md`, `generic/05_system_configuration.md`) menyatakan `faculties` + `study_programs`
digantikan pohon `Organization`. Kode sudah mengikutinya: profil pengguna (`organization_id`,
migration `2026_07_12_134942`), akses form, fase, form builder, dan statistik membaca
`Organization`. `FacultyController` (route `admin.faculties.*` dan `admin.faculties.study-programs*`)
adalah satu-satunya yang masih menulis tabel lama — Operator yang mengubah fakultas di sana
tidak mengubah apa pun. **Keputusan pemilik (4 Oktober 2026): cabut layar lama.** Model dan
tabelnya dibiarkan selama masih dipakai seeder atau test (data historis).

**F2 — Gerbang biodata punya definisi masa tugas reviewer sendiri.** `CheckBiodata::needsBiodataCheck`
melewati pemeriksaan biodata untuk reviewer dengan `end_date` null atau `>= now()` — mengabaikan
`start_date` (reviewer yang belum mulai dianggap sudah). Definisi tunggalnya
`ReviewersWithinTenure` (BR-REV-09, inklusif per hari kalender, slice klaster reviewer).
`form_type_id = 1` juga ditulis sebagai angka ajaib di dua tempat (`CheckBiodata`,
`BiodataController`).

**F3 — `ExportController::index` dan prosa komentarnya melewati ambang.** Pemetaan riwayat
ekspor (termasuk tautan bertanda tangan BR-RPT-03) dirakit di controller; dua blok komentar
4 baris. Murni refactor.

## Keputusan

- **K1** — F1 dicabut seluruhnya: route, controller, item sidebar (`AppSidebar.vue`), halaman
  `Pages/Faculties/*` dan `Pages/StudyPrograms/*`, entri `ziggy.js`, dan test yang hanya menguji
  layar itu. Model `Faculty`/`StudyProgram` dan seeder-nya **tetap** bila masih dipakai
  (`OrganizationSeeder` dan kawan-kawan); bila ternyata tak dipakai siapa pun selain yang dicabut,
  laporkan, jangan dihapus di slice ini.
- **K2** — `CheckBiodata` memakai `ReviewersWithinTenure`. Konsekuensi yang disengaja: reviewer
  yang masa tugasnya belum mulai kini diperiksa biodatanya seperti user biasa. Penentuan status
  biodata dipindah ke `Queries/Identity/BiodataStatus` (ChangesNoState) yang mengembalikan bentuk
  status sesi yang sama; middleware tinggal memutuskan redirect. Angka `1` diganti konstanta yang
  diberi nama (cari padanan yang sudah ada — `FormType` atau sejenisnya — sebelum membuat baru);
  `BiodataController` ikut memakainya.
- **K3** — Riwayat ekspor dirakit `Queries/Reporting/ExportHistory` (ChangesNoState). Bentuk prop
  `jobs` tidak berubah. Komentar dipadatkan **tanpa membuang fakta**: label vs nilai mentah
  (tombol ekspor ulang), tautan bertanda tangan dibuat saat render (BR-RPT-03), dua penolakan
  berbeda di `download` (BR-RPT-03, BR-RPT-12), signed URL bocor tetap tak membuka berkas orang lain.

## Global Constraints

- Identifier bahasa Inggris; prosa komentar bahasa Indonesia, ≤ 3 baris berturut-turut,
  ≤ 93 karakter termasuk indentasi; sebut kode BR; jangan membuang fakta latar.
- Dilarang menyiasati PHPStan (tanpa `@phpstan-ignore`, baseline, `assert()`, inline `@var` di
  `app/`, cast, pelebaran tipe). Setiap Action/Query menulis audit atau `implements ChangesNoState`.
- `quality-budget.txt` hanya turun; `quality-legacy.txt` hanya menyusut. `pending.txt` /
  `uncovered.txt` tidak naik. Nama test menyebut kode BR; jangan mengarang ID skenario.
- Jangan sentuh `docs/requirement-analysis/`. Commit satu baris tanpa trailer.
- Container `CI` dan DB test `simpas_dev_pg` :55432; satu proses test pada satu waktu.

## Definisi selesai

- [ ] `CI composer run quality` dan `npm run quality` hijau
- [ ] Ketiga berkas PHP dan enam halaman Vue keluar dari `quality-legacy.txt`; `max.legacy.files` turun sejumlah baris yang dihapus
- [ ] Test F2 merah sebelum perbaikan

## Yang tidak dikerjakan

- Layar daftar `Organization` (bagian layar v2).
- Penghapusan tabel `faculties`/`study_programs` — butuh migration terhadap data nyata.

---
---

# Rencana Implementasi

### Task 1: Cabut layar Fakultas dan Prodi lama (F1, K1)

- [ ] **Step 1:** grep pemanggil `admin.faculties` dan `study-programs` di `resources/js`
  (tanpa `ziggy.js`), `tests/`, `app/`, dan `docs/requirement-analysis/bdd`. Catat hasilnya.
  `FrontendRouteNamesTest` akan merah bila ada nama yang masih dipakai.
- [ ] **Step 2:** Test `Route::has` dataset untuk `admin.faculties.index` dan
  `admin.faculties.study-programs` → false (**merah** dulu).
- [ ] **Step 3:** Cabut route (resource `faculties` dan enam route `study-programs`), controller,
  dua item sidebar, `Pages/Faculties/*`, `Pages/StudyPrograms/*`, entri `ziggy.js`, test yang hanya
  menguji layar itu (periksa `tests/Feature/Inertia/RepairedPagesTest.php` — buang kasusnya saja
  bila berkasnya juga menguji hal lain). Hapus baris berkas yang dicabut dari `quality-legacy.txt`
  dan turunkan `max.legacy.files` sejumlah itu.
- [ ] **Step 4:** `CI composer run quality`, `npm run quality`; commit
  `refactor(identity): cabut layar Fakultas dan Prodi yang menyunting tabel mati`

### Task 2: Gerbang biodata memakai satu definisi masa tugas (F2, K2)

- [ ] **Step 1:** Test (lewat route yang dijaga `CheckBiodata`):
  1. reviewer yang `end_date`-nya hari ini tidak dialihkan ke biodata (karakterisasi);
  2. reviewer yang `start_date`-nya besok **diperiksa** biodatanya — dialihkan bila belum
     mengisi (**merah**);
  3. status sesi `biodataStatus` untuk biodata belum dikirim / menunggu / disetujui tetap sama
     (karakterisasi; cari test yang sudah ada dulu).
- [ ] **Step 2:** Implementasi K2; middleware keluar dari daftar legacy bila `tools/limits.php`
  bersih (hapus barisnya, turunkan plafon).
- [ ] **Step 3:** Gerbang penuh; commit `fix(identity): gerbang biodata memakai masa tugas reviewer yang sama`

### Task 3: Riwayat ekspor lewat Query (F3, K3)

- [ ] **Step 1:** Pastikan test ekspor yang ada (grep `exports.index`, `Exports/IndexPage`) menegaskan
  bentuk `jobs`; bila belum, tambah satu test karakterisasi untuk satu job selesai (ada
  `download_url`) dan satu job yang berkasnya hilang (`download_url` null).
- [ ] **Step 2:** Implementasi K3; keluarkan dari daftar legacy bila bersih.
- [ ] **Step 3:** Gerbang penuh; commit `refactor(reporting): riwayat ekspor lewat Query`

### Task 4: Penutup

- [ ] `php tools/limits.php` dan `tools/prosa.php` bersih untuk berkas yang disentuh.
- [ ] Status dokumen → `selesai <tanggal>, mendarat di <hash>`; *Yang berbeda dari rencana*.
- [ ] Commit `docs(design): catat hasil gelombang 3 bagian 4`
