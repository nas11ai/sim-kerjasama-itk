# Gelombang 3 bagian 8 — Wizard form builder yang benar-benar bisa menyimpan

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development`
> (disarankan) atau `superpowers:executing-plans`. Langkahnya memakai checkbox (`- [ ]`).
>
> **Dokumen ini spec sekaligus rencana.** Temuan diukur pada `1c7f09a`.

**Goal:** `CompleteFormBuilderController` memenuhi ambang keras dan keluar dari `quality-legacy.txt`
(`max.legacy.files` 105 → 104), dan wizard enam langkah (`admin.form-builder.*`) bisa menyimpan.

**Spec induk:** [`refactor-plan.md`](refactor-plan.md) §5 gelombang 3 · BR-FE-07 (detail wajib
punya tenggat) · BR-FE-17 / ADR 0014 (tenggat milik periode tahap) · ADR 0004.

**Status:** selesai — Task 1–3 dikerjakan; `CompleteFormBuilderController` keluar dari daftar legacy
(plafon 105 → 104).

---

## Pengukuran awal

`store()` loc 195, cc 32; ukuran berkas.

## Temuan — diverifikasi di kode

**W1 — Wizard tidak pernah bisa menyimpan.** `FormPhaseDetail::create` tidak mengisi
`submission_date_id` (NOT NULL, BR-FE-07) → galat SQL, seluruh wizard di-rollback. Periodenya
pun baru diurus **setelah** detail dibuat, jadi tak ada tenggat yang bisa dipakai saat itu.

**W2 — Label tanggal ditulis mati di Vue.** `Step5SubmissionPeriod.vue` mengirim `label_id` 1
("Tanggal Mulai") dan 2 ("Tanggal Selesai") yang ditulis tangan. Id label di basis data belum tentu
itu; label lain tak bisa dipilih. Nama "Tanggal Mulai" juga menyiratkan jendela, padahal setiap
tanggal adalah batas akhir (`SubmissionPeriodGate`).

**W3 — Validasi bersarang tak ada.** `evaluation_forms.*.fields.*` (tipe field, label, opsi) dan
`submission_period.dates.*` (label, tanggal) tidak divalidasi; masukan cacat → 500.

**W4 — Periode yang ada bisa tertaut dua kali** ke tahap yang sama (`submissionPeriodPhases()->create`
tanpa cek), menghasilkan baris ganda.

**W5 — Tanpa audit, galat bocor**, satu method 195 baris.

## Keputusan

- **K1 — Urutan langkah di backend: periode dulu, lalu tahap dan detail.** Periode (yang ada atau
  baru beserta tanggalnya) diselesaikan lebih dulu dan ditautkan ke tahap (tanpa duplikat), baru
  detail dibuat dengan tenggat.
- **K2 — Tenggat detail dari wizard = tanggal terakhir periode yang dipilih/dibuat.** Wizard tidak
  punya langkah memilih tenggat; tanggal terakhir adalah batas akhir periode itu, dan
  Operator dapat memindahkannya di layar Edit Tahap (pemilih tenggat, slice bagian 7). Periode
  tanpa tanggal ditolak (`submission_period` — "periode wajib punya setidaknya satu tanggal").
  Tenggat yang dipakai lolos `AssertPhaseDeadlines` (tahapnya sudah tertaut ke periode itu).
- **K3 — Label tanggal dari backend.** `create()` mengirim `submissionDateLabels` (id, name);
  `Step5SubmissionPeriod.vue` memakainya sebagai pilihan alih-alih daftar tulis tangan. Perubahan Vue
  minimal: ganti sumber daftar dan nilai awal (kosong, Operator memilih).
- **K4 — Validasi lengkap** di `FormBuilderRequest`: field form, opsi, form evaluasi beserta field dan
  opsinya, tanggal periode (`label_id` exists, `date` date, label distinct per periode — selaras
  dengan aturan "satu tanggal per label" slice periode).
- **K5 — Satu Action `Actions/Form/BuildCompleteForm`** (audit `'Form dibangun lewat wizard'`), memakai
  Action yang ada bila cocok (`CreateSubmissionPeriod`, `CreateFormPhase` tidak cocok karena
  mewajibkan tenggat per baris dari layar — boleh dipakai bila bentuknya pas, jangan dipaksakan).
  Dipecah menjadi langkah privat yang masing-masing ≤ 40 baris. Satu transaksi; galat merambat.

## Global Constraints

- Identifier bahasa Inggris; prosa komentar bahasa Indonesia, ≤ 3 baris berturut-turut, ≤ 93
  karakter termasuk indentasi; sebut kode BR.
- Dilarang menyiasati PHPStan. Action menulis audit; Query `ChangesNoState`.
- `quality-budget.txt` hanya turun; `quality-legacy.txt` hanya menyusut. Nama test menyebut kode BR
  atau perilaku — jangan mengarang ID skenario.
- Vue legacy hanya disentuh sebatas K3. Jangan sentuh `docs/requirement-analysis/`.
- Commit satu baris tanpa trailer. Container `CI`, DB test `simpas_dev_pg` :55432; satu proses test.

## Definisi selesai

- [x] Wizard menyimpan form lengkap (periode baru dan periode yang ada) — test HTTP
- [x] Gerbang hijau; controller keluar dari daftar; plafon 105 → 104

## Yang berbeda dari rencana

- Baris controller di `quality-legacy.txt` dan plafon 105 → 104 ikut commit Task 1, bukan Task 3:
  begitu `store()` menjadi cangkang, assertion 2 menolak berkas bersih yang masih terdaftar.
- Hasil ukur: controller 58 baris (`create()` loc 21, `store()` loc 9), `BuildCompleteForm`
  196 baris (method terpanjang loc 29, cc ≤ 4), `FormBuilderRequest` 195 baris.
- Periode tanpa tanggal ditolak `BuildCompleteForm` (galat `submission_period`), bukan aturan
  `required` di request, supaya periode baru dan periode yang ada ditolak dengan galat yang sama.
- `AssertPhaseDeadlines` tidak dipanggil: tenggatnya diambil dari periode yang baru saja
  ditautkan ke tahap, jadi BR-FE-17 terpenuhi karena konstruksi; test HTTP memastikannya.
- `CreateSubmissionPeriod` dan `CreateFormPhase` tidak dipakai: yang pertama mencari label lewat
  nama dan menulis audit sendiri, yang kedua mewajibkan tenggat per baris dari layar.
- Wizard kini memakai penjaga BR-SCH-05 (`SchemeFieldGuard`) seperti pembuatan form biasa.
- `validation_rules` field evaluasi tidak lagi disalin: Step 4 selalu mengirim `{}` dan tak punya
  isian untuknya; menyimpannya hanya menambah `mixed`.
- Baris tanggal awal periode baru kini satu baris kosong (label dipilih Operator), bukan dua
  baris "Tanggal Mulai"/"Tanggal Selesai". Ringkasan Step 6 masih membaca `date.label`, yang tak
  diisi Step 5 — sudah begitu untuk baris tambahan sebelumnya; tidak disentuh (Vue legacy).

---
---

# Rencana Implementasi

### Task 1: Wizard menyimpan (W1, W3, W4, K1, K2, K4)

- [ ] Test merah lewat `admin.form-builder.store`:
  1. periode baru dengan dua tanggal → form, field, akses, tahap, detail (dengan `submission_date_id`
     = tanggal terakhir), form evaluasi tersimpan; periode tertaut ke tahap sekali;
  2. periode yang ada + tahap yang ada yang sudah tertaut → tidak ada tautan ganda;
  3. periode baru tanpa tanggal → ditolak dengan galat sesi, tidak ada yang tersimpan;
  4. field evaluasi tanpa `field_type_id` → galat validasi, bukan 500;
  5. dua tanggal berlabel sama → galat validasi.
- [ ] Implementasi `FormBuilderRequest` + DTO + `BuildCompleteForm`. Controller cangkang.
- [ ] Gerbang; commit `fix(form): wizard form builder menyimpan dengan tenggat dan validasi lengkap`

### Task 2: Label dari backend (W2, K3)

- [ ] Test: prop `submissionDateLabels` ada di `admin.form-builder.create`.
- [ ] Vue minimal (Step5 menerima label lewat prop dari `CreatePage.vue`).
- [ ] `npm run quality`; commit `fix(form): label tanggal wizard dari basis data, bukan id tulis tangan`

### Task 3: Ukur dan keluarkan

- [ ] `tools/limits.php`/`tools/prosa.php` bersih untuk semua berkas PHP yang disentuh; hapus baris
  controller; plafon 105 → 104; status dokumen + *Yang berbeda dari rencana*.
- [ ] Commit `refactor(form): CompleteFormBuilderController keluar dari daftar legacy`
