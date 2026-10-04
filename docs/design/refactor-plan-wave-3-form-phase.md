# Gelombang 3 bagian 7 — Tahap formulir tanpa kehilangan data

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development`
> (disarankan) atau `superpowers:executing-plans`. Langkahnya memakai checkbox (`- [ ]`).
>
> **Dokumen ini spec sekaligus rencana.** Temuan diukur pada `a21216e`.

**Goal:** `FormPhaseController` memenuhi ambang keras dan keluar dari `quality-legacy.txt`
(`max.legacy.files` 106 → 105). Lebih penting dari itu: menyunting tahap formulir berhenti
menghancurkan data.

**Spec induk:** [`refactor-plan.md`](refactor-plan.md) §5 gelombang 3 · BR-FE-20 (penghapusan tahap
dijaga) · ADR 0004 (`form_submissions.form_phase_detail_id`).

**Status:** selesai — Task 1–4 dikerjakan; `FormPhaseController` keluar dari daftar legacy (plafon 106 → 105).

---

## Pengukuran awal

`FormPhaseController`: ukuran berkas; `index()` loc 52; `store()` loc 69; `update()` loc 73.

## Temuan — diverifikasi di kode dan skema

**F1 — Menyunting tahap menghapus lalu membuat ulang seluruh detailnya (kehilangan data).**
`update()` memanggil `$formPhase->formPhaseDetails()->delete()` (query massal — **melewati**
penjaga BR-FE-20 di `FormPhaseDetail::booted()` yang menolak detail yang masih dipakai pengajuan),
lalu membuat detail baru. Akibat menurut skema:
- `review_evaluation_forms.form_phase_detail_id` → `ON DELETE CASCADE`: **seluruh form evaluasi
  reviewer tahap itu terhapus** (beserta field dan penugasannya).
- `form_submissions.form_phase_detail_id` → `nullOnDelete`: **pengajuan kehilangan tahapnya**.
- `submission_date_id` tidak ikut disalin ke detail baru: **tautan tenggat hilang**.
Halaman Edit sudah mengirim `id` untuk detail yang ada (`FormPhases/EditPage.vue` ~132), jadi
sinkronisasi per id mungkin tanpa mengubah layar.

**F2 — Hapus tunggal melewati Action.** `destroy()` menghapus detail lewat query massal lalu
tahapnya, tanpa `DeleteFormPhase` (BR-FE-20) yang dipakai `bulkDelete`. Akibatnya sama dengan F1
untuk tahap yang dihapus: form evaluasi ikut terhapus, pengajuan kehilangan tahap, tanpa audit.

**F3 — Filter "aktif" di daftar tahap.** `where('is_active', 1|0)` pada kolom `boolean` PostgreSQL —
perbandingan `boolean = integer`. Diverifikasi dengan test; perbaiki bila gagal.

**F4 — Tombol hapus massal rusak.** `FormPhases/IndexPage.vue` memanggil
`route('admin.forms-phases.bulk-delete')` (salah eja; tercatat di `KNOWN_BROKEN_FRONTEND_ROUTES`).

**F5 — Parameter daftar tak divalidasi.** `sort_by`/`sort_order`/`per_page` langsung ke
`orderBy`/`paginate`: kolom tak dikenal atau arah selain asc/desc → 500; `per_page` tanpa batas.

**F6 — Tanpa audit, galat bocor.** Buat, ubah, ubah status, dan hapus tunggal tidak diaudit
(BR-RPT-09); `store`/`update`/`destroy`/`updateStatus` menempel `$e->getMessage()`.

**F7 — Endpoint JSON tanpa pemanggil.** `admin.api.form-access-controls`
(`getFormAccessControls`) — nol pemanggil di `resources/js` dan `tests`.

**F8 — Normalisasi urutan disalin dua kali** dan melakukan satu query per detail.

## Keputusan

- **K1 — `Actions/Form/ReviseFormPhase`** menyinkronkan detail **per id**: detail dengan `id` milik
  tahap ini diperbarui di tempat (kolom yang dikirim saja; `submission_date_id` dibiarkan); detail
  tanpa `id` dibuat; detail lama yang tidak dikirim dihapus **satu per satu lewat model** sehingga
  penjaga BR-FE-20 berlaku (yang masih dipakai pengajuan → `ValidationException`, seluruh revisi
  di-rollback). `id` yang bukan milik tahap ini ditolak. Satu transaksi; audit
  `'Tahap formulir direvisi'`.
- **K2 — `Actions/Form/CreateFormPhase`** (audit) dan `destroy()` memakai `DeleteFormPhase`.
  `updateStatus` lewat `Actions/Form/SetFormPhaseActive` (audit); responsnya JSON tetap
  (`IndexPage.vue` memanggilnya), tanpa pesan exception.
- **K3 — Normalisasi urutan** (detail berform sama mendapat urutan sama, berurutan menurut
  kemunculan) jadi satu fungsi milik Action, dengan satu query untuk seluruh `form_access_control`.
- **K4 — Daftar tahap lewat `Queries/Form/FormPhaseDirectory`** dengan whitelist `sort_by`
  (`created_at`, `title`, `is_active`), `sort_order` (asc/desc), `per_page` (maks. 100), filter aktif
  bertipe boolean. Bentuk prop tetap.
- **K5 — F4 diperbaiki** (nama route di Vue), entri `KNOWN_BROKEN_FRONTEND_ROUTES` dihapus.
- **K6 — F7 dicabut** beserta entri `ziggy.js`.
- **K7 — Pemilih tenggat per detail** (keputusan pemilik, 4 Oktober 2026). BR-FE-07 mewajibkan
  `submission_date_id`, tetapi layar Tambah/Edit Tahap tidak pernah menyediakannya — membuat tahap
  atau menambah detail dari layar selalu gagal. Halaman `FormPhases/CreatePage.vue` dan
  `EditPage.vue` mendapat satu pilihan "Tenggat" per detail (opsi: tanggal periode pengajuan —
  nama periode, label, tanggal) beserta tampilan galat validasinya. Backend menyediakan opsi itu
  sebagai prop. Ini pengecualian sadar atas aturan "Vue legacy tidak direfactor": fiturnya mati
  tanpa itu.

## Global Constraints

- Identifier bahasa Inggris; prosa komentar bahasa Indonesia, ≤ 3 baris berturut-turut, ≤ 93
  karakter termasuk indentasi; sebut kode BR; jangan membuang fakta latar.
- Dilarang menyiasati PHPStan. Setiap Action menulis audit; Query `implements ChangesNoState`.
- `quality-budget.txt` hanya turun; `quality-legacy.txt` hanya menyusut. Nama test menyebut kode
  BR atau perilaku — jangan mengarang ID skenario.
- Vue legacy hanya disentuh sebatas F4. Jangan sentuh `docs/requirement-analysis/`.
- Commit satu baris tanpa trailer. Container `CI`, DB test `simpas_dev_pg` :55432; satu proses test.

## Definisi selesai

- [ ] Tahap baru dan detail baru bisa dibuat dari layar (dengan tenggat)

- [ ] Gerbang hijau; `FormPhaseController` keluar dari daftar; plafon 106 → 105
- [ ] Test F1, F2, F3 (bila gagal), F5 merah sebelum perbaikan

## Yang berbeda dari rencana

- Task 1 menemukan `submission_date_id` tidak pernah diisi di mana pun, sehingga membuat dan
  menyunting tahap selalu gagal di SQL dan kehilangan data F1 sebenarnya tidak pernah ter-commit.
- Task 4 ditambahkan atas keputusan pemilik, supaya tenggat bisa dipilih dari layar.
- Tenggat detail yang sudah ada kini ikut tersimpan bila dikirim (null berarti dipertahankan),
  berbeda dari K1 yang membiarkan `submission_date_id` detail lama.
- Pilihan tenggat memuat semua `SubmissionDate`, bukan hanya milik periode tertentu.
- F3 ternyata bukan galat: filter aktif/nonaktif sudah lolos di PostgreSQL. Tetap dipindah ke
  `FormPhaseDirectory` dengan pembanding boolean, dan test-nya dipertahankan sebagai penjaga.

---
---

# Rencana Implementasi

### Task 1: Revisi tahap tanpa kehilangan data (F1, F8, K1, K3)

- [ ] Test merah (lewat `admin.form-phases.update`):
  1. revisi yang mempertahankan detail (mengirim `id`-nya) **tidak** menghapus form evaluasi detail itu;
  2. pengajuan yang menunjuk detail itu tetap menunjuknya (`form_phase_detail_id` tidak null);
  3. `submission_date_id` detail tetap;
  4. detail yang dibuang dan masih dipakai pengajuan → ditolak, tidak ada yang berubah;
  5. detail yang dibuang dan tak dipakai → terhapus; detail baru tanpa `id` → dibuat;
  6. `id` milik tahap lain → ditolak;
  7. urutan ternormalisasi (dua detail form sama → urutan sama); audit `'Tahap formulir direvisi'`.
- [ ] Implementasi K1, K3. Gerbang; commit `fix(form): revisi tahap formulir menyinkronkan detail, bukan menghapus ulang`

### Task 2: Buat, hapus, status lewat Action (F2, F6, K2)

- [ ] Test merah: hapus tunggal tahap yang dipakai pengajuan → ditolak dan form evaluasinya utuh;
  hapus tunggal diaudit; buat diaudit; ubah status diaudit dan JSON-nya tanpa pesan exception.
- [ ] Implementasi K2. Gerbang; commit `fix(form): hapus tunggal tahap lewat penjaga BR-FE-20 dan semua aksi beraudit`

### Task 3: Daftar, tautan, endpoint mati, keluar dari daftar (F3, F4, F5, F7, K4–K6)

- [ ] Test: filter aktif/nonaktif mengembalikan yang benar (merah bila PostgreSQL menolak);
  `sort_by` tak dikenal dan `per_page=100000` tidak 500 dan dibatasi (**merah**);
  `Route::has('admin.api.form-access-controls')` false (**merah**).
- [ ] Implementasi K4–K6; `npm run quality` hijau; `FrontendRouteNamesTest` hijau dengan daftar menyusut.
- [ ] `tools/limits.php` dan `tools/prosa.php` bersih; hapus baris controller dari daftar; plafon
  106 → 105. Status dokumen + *Yang berbeda dari rencana*.
- [ ] Commit `refactor(form): daftar tahap lewat Query dan FormPhaseController keluar dari daftar legacy`

### Task 4: Pemilih tenggat per detail (K7)

- [ ] Backend: `create`/`edit` mengirim prop `submissionDates` (id, label tampilan
  "Periode — Label — tanggal") dari `SubmissionDate` beserta periode dan labelnya; `store` mewajibkan
  `submission_date_id` untuk setiap detail (seperti detail baru di revisi). Test: prop ada; `store`
  tanpa tenggat → `assertSessionHasErrors`; `store` dengan tenggat → tahap dan detailnya tersimpan
  dengan `submission_date_id` (**merah** dulu bila hari ini gagal SQL).
- [ ] Vue: satu `Select` "Tenggat" per detail di Create dan Edit (pakai komponen Select yang sudah
  dipakai berkas itu), terikat ke `submission_date_id`; tampilkan `form.errors` untuk
  `phase_details.N.submission_date_id`, `phase_details`, dan `id`. Detail lama di Edit memuat nilai
  awalnya dari `form_phase_details[].submission_date_id`. Test frontend kecil bila ada pola di
  `tests/frontend` (opsional).
- [ ] `npm run quality` dan `CI composer run quality` hijau. Commit
  `feat(form): pilih tenggat per detail tahap formulir (BR-FE-07)`
