# Gelombang 3 bagian 3 — klaster reviewer keluar dari daftar legacy

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development`
> (disarankan) atau `superpowers:executing-plans`. Langkahnya memakai checkbox (`- [ ]`).
>
> **Dokumen ini spec sekaligus rencana.** Temuan diukur pada `d15f4b0`.

**Goal:** `ReviewerController` dan `ReviewerFormAssignmentController` memenuhi ambang keras
dan keluar dari `quality-legacy.txt` (`max.legacy.files` 120 → 118). Cacat bisnis di
sepanjang jalan ditutup lebih dulu, masing-masing dengan test yang merah sebelum perbaikan.

**Architecture:** Strangler (ADR 0008). Yang mati dicabut; yang menduplikasi Action dialihkan ke
Action itu; sisanya jadi cangkang validasi → Action/Query → respons.

**Tech Stack:** Laravel 13 · PHP 8.4 · Pest

**Spec induk:** [`refactor-plan.md`](refactor-plan.md) §2.1, §5 gelombang 3

**Status:** belum dikerjakan.

---

## Pengukuran awal

| Berkas | Pelanggaran (`tools/limits.php`) |
| ------ | -------------------------------- |
| `ReviewerController` | ukuran berkas; `index()` loc 65, cc 9 |
| `ReviewerFormAssignmentController` | ukuran berkas; `bulkAssignForms()` loc 52; `getMyAssignments()` loc 95, cc 9 |

## Temuan bisnis — diverifikasi di kode

**R1 — Layar Operator menunjuk reviewer tanpa `AppointReviewer`.** `ReviewerController::store`
membuat `Reviewer` langsung (`Reviewer::create`), dan `update` mengubah `reviewer_type` langsung.
Padahal `Actions/Identity/AppointReviewer` (dipakai `UserAccessController::appointReviewer`)
memegang aturannya:
- BR-IAM-11 — `reviewer_type` menentukan role `reviewer_internal`/`reviewer_external`, dan role
  itulah yang dipakai BR-REV-11 membatasi keterlihatan skor. Jalur legacy **tidak memberi role**;
  mengubah tipe tidak mencabut role lama.
- BR-IAM-11 — penunjukan **idempoten per orang**. Jalur legacy menolak hanya bila ada record
  yang *sedang aktif*; reviewer yang masa tugasnya sudah lewat ditunjuk ulang menjadi **record
  kedua**, yang membuat beban (BR-REV-04) dan keterlihatan skor ambigu.
- BR-IAM-05 — akun nonaktif tidak boleh ditunjuk. Jalur legacy tidak memeriksanya.
- BR-IAM-15 — yang ditunjuk diberi tahu; BR-RPT-09 — diaudit. Jalur legacy tidak keduanya.

**R2 — Empat definisi "masa tugas aktif" yang tidak sepakat.** `AssignReviewer::guardTenure`
(BR-REV-09) menganggap `start_date` null = sudah mulai; `ReviewerController::index`, `create`,
`store`, `show` menuntut `start_date <= now` (null = tidak aktif); `CheckReviewer` sepakat dengan
`AssignReviewer`. Ketiganya membandingkan kolom **tanggal** dengan **waktu sekarang**, sehingga
reviewer yang `end_date`-nya hari ini sudah dianggap habis sejak lewat tengah malam —
bertentangan dengan "hingga end_date" (BR-REV-09).

**R3 — Statistik detail reviewer palsu.** `show()` mengisi `pending_reviews` dan
`completed_reviews` dengan jumlah **seluruh** penugasan (filter-nya dikomentari) — dua angka
selalu sama dengan total.

**R4 — Hapus reviewer meninggalkan role-nya.** `destroy()` menghapus record tapi tidak mencabut
role `reviewer_*`; user itu tetap memegang hak keterlihatan skor (BR-REV-11). Tanpa audit.
Aktivasi/deaktivasi juga tanpa audit.

**R5 — Endpoint form evaluasi yang mati.** Tanpa pemanggil di `resources/js` maupun BDD:
`admin.form-assignments.update` (`updateAssignment`), `admin.submissions.form-assignments`
(`getAssignmentsForSubmission` — hanya `FormAssignmentsPayloadTest`), dan
`admin.maintenance.auto-lock-overdue` (`autoLockOverdueAssignments`). `getAvailableFormsForSubmission`
dan `getSubmissionFormPhase` bahkan tak punya route.

**R6 — Pesan galat membocorkan isi exception.** `removeFormAssignment` dan `bulkAssignForms`
menempelkan `$e->getMessage()` ke layar. Penghapusan penugasan form juga tanpa audit.
`bulkAssignForms` melaporkan hasil dalam bahasa Inggris dan menghitung reviewer yang tidak
bertugas sebagai galat yang tetap dianggap "success".

---

## Keputusan

### K1 — Tambah dan ubah reviewer lewat `AppointReviewer`

`store` dan perubahan tipe di `update` memanggil `AppointReviewer::run`. Konsekuensi yang
disengaja: `end_date` kosong saat menunjuk berarti masa tugas default satu tahun (perilaku
`AppointReviewer`, "Masa tugas wajib ada"), bukan tanpa batas.

### K2 — Masa tugas: satu definisi, inklusif per hari

`Reviewer::isWithinTenure(?CarbonInterface $on = null): bool` — `start_date` null atau
`<= tanggal`, dan `end_date` null atau `>= tanggal`, dibandingkan **per tanggal kalender**
(BR-REV-09 "hingga end_date"). Versi query-nya `Queries/Review/ReviewersWithinTenure` (atau
scope bertipe bila tier mengizinkan — pilih yang lolos tier strict tanpa penyiasatan).
Pemakainya: `AssignReviewer::guardTenure`, `CheckReviewer`, daftar/kandidat/detail di
`ReviewerController`. Deaktivasi menyetel `end_date` ke **kemarin**, supaya reviewer keluar
masa tugas hari itu juga di bawah definisi inklusif.

### K3 — Masa tugas diubah lewat satu Action beraudit

`Actions/Identity/ChangeReviewerTenure::run(Reviewer, string $start, ?string $end): Reviewer` —
dipakai `update` (tanggal), `deactivate`, `activate`. Audit `'Masa tugas reviewer diubah'`.

### K4 — Penghapusan reviewer mencabut role-nya

`Actions/Identity/DismissReviewer::run(Reviewer): void` — tetap menolak bila reviewer punya
penugasan (riwayat BR-REV-10), mencabut role `reviewer_internal`/`reviewer_external`, menghapus
record, audit `'Reviewer dihapus'`. Pesan penolakan diluruskan: "masih memiliki riwayat
penugasan" (bukan "review yang belum selesai" — penugasan yang selesai pun menahan).

### K5 — Statistik detail dari `evaluation_status`

`total` = semua penugasan; `completed` = `evaluation_status` beres
(`SubmissionReviewer::SETTLED_EVALUATION`); `pending` = penugasan **aktif** yang belum beres.

### K6 — Endpoint R5 dicabut; `LockEvaluationForm` dipertahankan

Tiga route dan lima method R5 dicabut beserta `FormAssignmentsPayloadTest`. Action
`LockEvaluationForm` tetap (ber-test, `EvaluationFormLifecycleTest`): penguncian otomatis form
yang lewat tenggat tidak punya aturan di DDD — pertanyaan pemilik, bukan refactor.

### K7 — Form evaluasi: hapus dan massal lewat Action

`Actions/Review/RemoveEvaluationFormAssignment` (audit, menolak bila sudah dikirim) dan
`Actions/Review/AssignEvaluationFormsInBulk` (mengembalikan jumlah sukses dan daftar reviewer
yang dilewati). Galat tak terduga tidak lagi ditempel ke layar — biarkan merambat (Laravel
mencatatnya) atau tampilkan pesan umum. Pesan hasil dalam bahasa Indonesia.

### K8 — Daftar tugas reviewer lewat Query

`Queries/Review/ReviewerAssignmentList` memegang filter status, pencarian, urutan, dan
statistik. Statistik dihitung di SQL (bukan memuat seluruh penugasan ke memori). Bentuk prop
Inertia (`assignments`, `stats`, `filters`) tidak berubah.

---

## Global Constraints

- **Identifier bahasa Inggris, prosa komentar bahasa Indonesia, maksimal 3 baris prosa
  berturut-turut, lebar baris komentar ≤ 93 karakter** (termasuk indentasi). Sebut kode BR.
  Saat memindahkan atau memadatkan komentar, **jangan membuang fakta latar**.
- **Dilarang menyiasati PHPStan** — tanpa `@phpstan-ignore`, baseline, `assert()`, inline
  `@var` baru di `app/`, cast, atau pelebaran tipe.
- **Setiap Action menulis `AuditTrail::record` atau `implements ChangesNoState`** (RPT-AUDIT-07).
- **`quality-budget.txt` hanya turun; `quality-legacy.txt` hanya menyusut.**
  `pending.txt` / `uncovered.txt` hanya menyusut (kalau mengimplementasikan skenario BDD, sebut
  ID-nya di nama test lalu hapus dari `pending.txt` dan turunkan plafonnya).
- **Jangan sentuh `docs/requirement-analysis/`.** Jangan menjalankan migration terhadap data nyata.
- **Pesan commit: satu baris, tanpa badan, tanpa trailer.**
- **Container PHP `CI`** dan DB test `simpas_dev_pg` :55432 seperti rencana sebelumnya.
  Satu proses test pada satu waktu.

## Definisi selesai

- [ ] `CI composer run quality` dan `npm run quality` hijau
- [ ] `php tools/limits.php` bersih untuk kedua controller
- [ ] Kedua berkas keluar dari `quality-legacy.txt`; `max.legacy.files` 120 → 118
- [ ] Setiap test R1–R4, R6 merah sebelum perbaikan

## Yang tidak dikerjakan dokumen ini

- Penguncian otomatis form evaluasi yang lewat tenggat (K6) — tidak ada aturannya di DDD.
- Siapa yang boleh menjadi kandidat reviewer di layar "Tambah Reviewer" (hari ini: role
  "Tenaga Kependidikan"). BR-IAM-11 hanya menyebut "user yang sudah terdaftar"; daftar
  kandidatnya tetap, hanya filter masa tugasnya yang mengikuti K2.

---
---

# Rencana Implementasi

Urutan: definisi masa tugas dulu (Task 1) karena Task 2–3 memakainya; lalu cacat bisnis
reviewer (Task 2–3); lalu form evaluasi (Task 4–5); lalu ukur dan keluarkan (Task 6).

---

### Task 1: Satu definisi masa tugas (R2, K2)

**Files:** `app/Models/Reviewer.php`, Query/scope baru, `app/Actions/Review/AssignReviewer.php`,
`app/Http/Middleware/CheckReviewer.php`, `tests/Feature/Review/ReviewerTenureTest.php`

- [ ] **Step 1: Test yang gagal** — `isWithinTenure` dan Query/scope-nya:
  1. `end_date` = hari ini, jam 15:00 → masih bertugas (**merah**: hari ini `AssignReviewer`
     menolak);
  2. `start_date` = besok → belum bertugas;
  3. `end_date` = kemarin → sudah habis;
  4. `start_date` null, `end_date` null → bertugas;
  5. Query/scope mengembalikan himpunan yang sama dengan `isWithinTenure` untuk keempat kasus.
  Pakai `$this->travelTo(...)` untuk jam. Tambah satu test HTTP: Operator menugaskan reviewer
  yang `end_date`-nya hari ini lewat `admin.submissions.assign-reviewers` → berhasil
  (**merah**). `[REV-ASSIGN-08]` yang ada harus tetap hijau.
- [ ] **Step 2: Implementasi** sesuai K2; `AssignReviewer::guardTenure` dan `CheckReviewer`
  memakai definisi itu. Komentar BR-REV-09 menyebut "inklusif per hari".
- [ ] **Step 3: Gerbang penuh; commit** `fix(review): satu definisi masa tugas reviewer, inklusif per hari`

---

### Task 2: Tambah, ubah, aktif/nonaktif reviewer lewat Action (R1, K1, K3)

**Files:** `app/Http/Controllers/ReviewerController.php`,
`app/Actions/Identity/ChangeReviewerTenure.php`, `tests/Feature/Identity/ReviewerManagementTest.php`

- [ ] **Step 1: Test yang gagal** (lewat route `admin.reviewers.*`):
  1. `store` memberi role `reviewer_internal` (**merah**);
  2. `store` untuk user yang record reviewer-nya sudah habis masa tugas → tetap satu record,
     masa tugas diperbarui (**merah**: hari ini dua record);
  3. `store` untuk akun nonaktif → ditolak `assertSessionHasErrors('user_id')` (**merah**);
  4. `store` mengaudit `'Reviewer ditunjuk'` dan memberi tahu user (**merah**);
  5. `update` mengubah tipe internal → external: role lama dicabut, role baru diberikan (**merah**);
  6. `deactivate` → `end_date` = kemarin, reviewer tak lagi `isWithinTenure()`, diaudit (**merah**);
  7. `activate` → `end_date` null, diaudit (**merah** untuk audit).
- [ ] **Step 2: Implementasi** — `store` → `AppointReviewer::run($user, $type, $start, $end)`;
  `update` → `AppointReviewer` bila tipe berubah, `ChangeReviewerTenure` untuk tanggal;
  `deactivate`/`activate` → `ChangeReviewerTenure`. Validasi `store` tetap
  (`after_or_equal:today` untuk `start_date`). `ValidationException` dari Action mengalir sebagai
  error sesi biasa.
- [ ] **Step 3: Gerbang penuh; commit** `fix(review): kelola reviewer lewat AppointReviewer dan Action masa tugas`

---

### Task 3: Hapus reviewer dan statistik detail (R3, R4, K4, K5)

**Files:** `app/Actions/Identity/DismissReviewer.php`, `app/Http/Controllers/ReviewerController.php`,
test di berkas Task 2

- [ ] **Step 1: Test yang gagal**:
  1. `destroy` reviewer tanpa penugasan → record hilang, role `reviewer_*` dicabut, diaudit (**merah**);
  2. `destroy` reviewer berpenugasan (termasuk yang `replaced`) → ditolak, pesan "riwayat
     penugasan", record utuh;
  3. `show` → `completed_reviews` = penugasan beres, `pending_reviews` = penugasan aktif belum
     beres, `total_reviews` = semua (**merah**).
- [ ] **Step 2: Implementasi** sesuai K4, K5. `index`/`create`/`show` memakai definisi Task 1
  untuk `is_active` dan filter. `index` dipindah ke `Queries/Review/ReviewerDirectory` bila
  masih melanggar ambang (bentuk prop tetap).
- [ ] **Step 3: Gerbang penuh; commit** `fix(review): hapus reviewer mencabut role-nya dan statistik detail jujur`

---

### Task 4: Cabut endpoint form evaluasi yang mati (R5, K6)

**Files:** `routes/web.php`, `resources/js/ziggy.js`, `ReviewerFormAssignmentController`,
`tests/Feature/Review/FormAssignmentsPayloadTest.php` (hapus),
`tests/Feature/Review/DeadFormAssignmentEndpointsRemovedTest.php` (baru)

- [ ] **Step 1:** grep ulang pemanggil (resources/js tanpa ziggy.js, tests, docs/requirement-analysis/bdd)
  untuk `admin.form-assignments.update`, `admin.submissions.form-assignments`,
  `admin.maintenance.auto-lock-overdue` — harus kosong kecuali `FormAssignmentsPayloadTest`.
- [ ] **Step 2:** Test dataset `Route::has(...)` → false, **merah** dulu.
- [ ] **Step 3:** Cabut route, `updateAssignment`, `getAssignmentsForSubmission`,
  `getAvailableFormsForSubmission`, `getSubmissionFormPhase`, `autoLockOverdueAssignments`,
  entri ziggy.js, `FormAssignmentsPayloadTest`. `LockEvaluationForm` tetap.
- [ ] **Step 4: Gerbang penuh; commit** `refactor(review): cabut endpoint form evaluasi yang tak dipanggil`

---

### Task 5: Form evaluasi lewat Action dan Query (R6, K7, K8)

**Files:** `app/Actions/Review/RemoveEvaluationFormAssignment.php`,
`app/Actions/Review/AssignEvaluationFormsInBulk.php`, `app/Queries/Review/ReviewerAssignmentList.php`,
`ReviewerFormAssignmentController`, test terkait

- [ ] **Step 1: Test**:
  1. hapus penugasan form ber-draft → penugasan dan draft hilang, status evaluasi dihitung ulang,
     diaudit (audit **merah**);
  2. hapus penugasan form yang sudah dikirim → ditolak, utuh (karakterisasi);
  3. massal ke dua reviewer, satu tak bertugas → satu sukses, pesan bahasa Indonesia menyebut
     yang dilewati (**merah** untuk bahasa pesan); `BulkFormAssignmentTest` dan
     `ReplacedReviewerAccessTest` tetap hijau;
  4. daftar tugas reviewer: filter `pending`/`completed`/`overdue`, pencarian `ilike`, dan
     statistik — karakterisasi terhadap kode lama (hijau), lalu tetap hijau setelah pindah.
- [ ] **Step 2: Implementasi** sesuai K7, K8; controller jadi cangkang.
- [ ] **Step 3: Gerbang penuh; commit** `refactor(review): form evaluasi lewat Action dan Query beraudit`

---

### Task 6: Ukur dan keluarkan dari daftar

- [ ] `php tools/limits.php` dan `php tools/prosa.php` bersih untuk kedua controller dan semua
  berkas baru. Perbaiki dengan teknik yang disebut namanya (Extract Method / Move Method).
- [ ] Hapus kedua baris dari `quality-legacy.txt`; `max.legacy.files` 120 → 118.
- [ ] Gerbang penuh, dua-duanya. Status dokumen → `selesai <tanggal>, mendarat di <hash>`;
  catat penyimpangan di *Yang berbeda dari rencana*.
- [ ] Commit `refactor(review): klaster reviewer keluar dari daftar legacy`
