# Gelombang 3 bagian 2 — `ReviewController` keluar dari daftar legacy

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development`
> (disarankan) atau `superpowers:executing-plans`. Langkahnya memakai checkbox (`- [ ]`).
>
> **Dokumen ini spec sekaligus rencana.** Temuan di bawah diukur pada `4bbaf19`.

**Goal:** `ReviewController` memenuhi ambang keras `quality-budget.txt` dan keluar dari
`quality-legacy.txt`. Empat cacat bisnis yang ditemukan di sepanjang jalan ditutup lebih
dulu, masing-masing dengan test yang merah sebelum perbaikan.

**Architecture:** Strangler (ADR 0008). Endpoint yang mati atau menduplikasi Action dicabut.
Yang tersisa menjadi cangkang: FormRequest/validasi → Action/Query → respons.

**Tech Stack:** Laravel 13 · PHP 8.4 · Pest · Vue 3 + TS (hanya `ReviewSystem.vue`)

**Spec induk:** [`refactor-plan.md`](refactor-plan.md) §2.1, §5 gelombang 3

**Status:** selesai 4 Oktober 2026, mendarat di 9b7f8c6.

---

## Pengukuran awal

`php tools/limits.php app/Http/Controllers/ReviewController.php` pada `4bbaf19`:

| Pelanggaran | Angka |
| ----------- | ----- |
| ukuran berkas | > 200 baris kode |
| `assignReviewers()` | loc 63 (ambang 40) |
| `addComment()` | loc 59 |
| `updateReviewStatus()` | loc 66 |
| prosa komentar | blok 7 dan 5 baris di `assignReviewers()` (ambang 3) |

## Temuan bisnis — semuanya diverifikasi di kode

**F1 — Tiga endpoint JSON tanpa pemanggil.** `admin.submissions.available-reviewers`,
`admin.submissions.available-evaluation-forms`, `admin.submissions.evaluation-status`.
Nol pemanggil di `resources/js` maupun `tests/`; tidak dirujuk BDD. Daftar reviewer yang
dipakai layar Operator dirakit `SubmissionViewController::adminShowSubmission` sendiri,
dan BR-REV-03 ditegakkan `AssignReviewer`. Lolos tiga centang K2 → dicabut.

Query yang hanya dipakai `getEvaluationStatus` (`EvaluationProgress`,
`EvaluationFormsOverview`) **tidak** ikut dicabut: keduanya bertipe, ber-test, dan
`EvaluationProgress` sudah mengembalikan `Data` — read model yang dibutuhkan layar
"Detail Pengajuan" v2. Presedennya `EvaluationRequirements`, yang sudah dalam keadaan sama.

**F2 — Status ulir punya dua jalur yang tidak sepakat.** `PATCH review-summaries/{id}/status`
(`updateReviewStatus`, tiga route: tanpa prefiks, `admin.`, `reviewer.`) menduplikasi
`ResolveReviewSummary`, dan berbeda darinya di tiga hal:
- Operator boleh menutup ulir. `ResolveReviewSummary` dan `[REV-DISCUSS-04]` menyatakan
  hanya reviewer yang boleh — kalau tidak, ulir yang menahan persetujuan (BR-REV-06) bisa
  ditutup tanpa penilai.
- Ia bisa **membuka kembali** ulir. Domain tidak mengenal aksi itu (BR-REV-13 hanya
  menyebut dua status; `discussion.feature` tidak punya skenarionya).
- Tanpa jejak audit (BR-RPT-09).

Satu-satunya pemanggil adalah dialog status di `ReviewSystem.vue`. Ia dialihkan ke
`review-summaries.resolve`, seperti tombol "Tandai Selesai" di halaman reviewer
(`45131b9`), lalu endpoint lamanya dicabut.

Ikut ditemukan: `ResolveReviewSummary::guardReviewer` hanya membandingkan `reviewer_id`
ulir. Reviewer yang sudah diganti tapi masih tercatat sebagai penulis ulir open
lolos — celah yang sama dengan gelombang 3 bagian 1, kini di jalur resolve. Guard-nya
ditambah syarat penugasan aktif (`ActiveReviewerAssignment`).

**F3 — Unduh lampiran tanpa otorisasi.** `GET /review-attachments/download?path=…`
(`downloadAttachment`) menyajikan **berkas apa pun di disk `public`** kepada pengguna mana
pun yang login — tanpa memeriksa bahwa berkas itu lampiran review, apalagi bahwa
pengunduhnya boleh melihat pengajuannya (BR-SM-07). Perbaikannya: berkas harus tercatat
sebagai `ReviewSummaryAttachment` atau `ReviewCommentAttachment`, dan pengajuan pemiliknya
harus `isAccessibleBy` pengunduh. URL dan nama route tetap, supaya `ReviewSystem.vue` tidak
berubah.

**F4 — Balasan bisa menempel ke ulir lain.** `addComment` memvalidasi `parent_comment_id`
hanya dengan `exists:review_comments,id` — komentar induk dari ulir lain, bahkan dari
pengajuan lain, diterima. Komentar juga tidak diaudit.

---

## Keputusan

### K1 — F1 dicabut, Query-nya dipertahankan

Lihat F1.

### K2 — Status ulir hanya lewat `ResolveReviewSummary`

Dialog status di `ReviewSystem.vue` diganti satu tombol "Tandai Selesai" dengan dialog
konfirmasi. Tombol hanya muncul bila ulir `open` **dan** pengguna reviewer yang bertugas
(`isAssignedReviewer`). Operator kehilangan kontrol itu — sesuai `[REV-DISCUSS-04]` dan
`ResolveReviewSummary`. Fitur "buka kembali" hilang karena domain tidak mengenalnya.

### K3 — Siapa yang boleh berkomentar tidak berubah

Operator (`users.manage`), pengaju (`submitted_by`), dan reviewer dengan penugasan aktif
yang evaluasinya beres (BR-FE-06) — persis perilaku hari ini. Anggota peneliti lain tidak
ditambahkan: `[REV-DISCUSS-02]` hanya menyebut pemilik pengajuan, dan memperluasnya adalah
keputusan bisnis, bukan refactor.

### K4 — Unduh lampiran pindah ke controller invokable

`Review/ReviewAttachmentDownloadController`, route `review-attachments.download` yang sama.
Pencarian pemiliknya `Queries/Review/ReviewAttachmentSubmission`.

### K5 — Penugasan reviewer jadi satu Action batch

`Actions/Review/AssignReviewers::run(FormSubmission, list<int> $reviewerIds, bool $autoAssignForms): list<string>`
— mengembalikan peringatan beban (BR-REV-04). Ia memegang transaksi, `not_required` bagi
fase tanpa form, dan penugasan otomatis form wajib (BR-FE-17). Delapan test
`AssignReviewersHttpTest` adalah jaringnya; perilaku tidak berubah. Audit satu entri per
permintaan, di samping entri per reviewer dari `AssignReviewer` — presedennya
`ResolveReviewSummary` yang memanggil `EvaluateSubmissionOutcome` beraudit.

---

## Global Constraints

- **Identifier bahasa Inggris, prosa komentar bahasa Indonesia, maksimal 3 baris prosa
  berturut-turut, lebar baris komentar ≤ 93 karakter.** Sebut kode BR di komentar.
- **Dilarang menyiasati PHPStan** — tanpa `@phpstan-ignore`, baseline, `assert()`,
  inline `@var` baru, cast, atau pelebaran tipe.
- **Status hanya berubah lewat `transitionTo()`.** Setiap Action menulis `AuditTrail::record`
  atau `implements ChangesNoState` (RPT-AUDIT-07).
- **`quality-budget.txt` hanya turun; `quality-legacy.txt` hanya menyusut.**
  `pending.txt` dan `uncovered.txt` tidak disentuh.
- **Jangan sentuh `docs/requirement-analysis/`.**
- **Pesan commit: satu baris, tanpa badan, tanpa trailer.**
- **Database test:** container `simpas_dev_pg` di :55432.
- **Container PHP**, disingkat **`CI`**:
  ```bash
  docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
    -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 <perintah>
  ```
- **Satu proses test pada satu waktu.**

## Definisi selesai

- [x] `CI composer run quality` dan `npm run quality` hijau — Pest 1000 passed, PHPStan dua
      tier tanpa galat, Vitest 11 passed (gelombang perbaikan akhir)
- [x] `php tools/limits.php app/Http/Controllers/ReviewController.php` tanpa pelanggaran
- [x] `app/Http/Controllers/ReviewController.php` keluar dari `quality-legacy.txt`;
      `max.legacy.files` 121 → 120
- [x] Setiap test F2–F4 merah sebelum perbaikan — F2 4 failed, F3 3 failed lalu 1 failed
      (urutan otorisasi), F4 2 failed; tercatat di laporan Task 2–4

## Yang tidak dikerjakan dokumen ini

- `assignEvaluationFormsToReviewer` masih membocorkan `$e->getMessage()` ke layar. Ia tidak
  melanggar ambang dan tidak ada aturan bisnis yang dilanggarnya; ditinggal untuk
  pembedahan `ReviewerFormAssignmentController`, yang memegang jalur kembarannya.
- Komentar di ulir yang sudah `resolved`: backend menerimanya, layar menyembunyikan
  kotaknya. Domain tidak menyatakan aturannya — pertanyaan untuk pemilik, bukan refactor.

## Yang berbeda dari rencana

- Task 2: putaran perbaikannya membuang email `notifyReviewStatusChanged` (BR-NOT-02:
  RevisionResolved tanpa email) dan tiga route yang metodenya tidak pernah ada
  (`review-summaries.complete` ×2, `admin.review-summaries.destroy`). Notifikasi in-app
  `RevisionResolvedNotification` untuk peneliti utama baru ditambahkan di gelombang perbaikan akhir.
- Task 3: urutan pemeriksaan diubah, otorisasi berjalan sebelum pemeriksaan berkas di disk.
- Task 4: `emailService` yang mati dibuang dari controller, dan test matriks K3 ditambahkan.
- Task 5: `AssignReviewers` memecah satu penugasan ke `assignOne()` (Extract Method) agar
  `handle()` di bawah ambang.
- Task 7 (di luar rencana): delapan route non-review yang menunjuk method tak ada dicabut —
  `roles.show`, `permissions.create`/`show`/`edit`, `users.assign-role`/`revoke-role`/
  `assign-permissions`/`revoke-permissions` — dan `RouteActionsExistTest` menjaganya.
- Gelombang perbaikan akhir: resolve memberi tahu peneliti utama (in-app), resolve ulang tidak
  mengulang evaluasi/audit/notifikasi, "Tandai Selesai" hanya di ulir sendiri dan tanpa kirim
  ganda, `?path[]=` unduh lampiran jadi 404, `addComment` tanpa cast dan try/catch, pesan
  evaluasi tanpa "0 formulir", audit batch "Penugasan reviewer diproses".
- Backend tidak lagi menghalangi resolve selama evaluasi masih tertunda: BR-FE-06 hanya
  menjaga pembuatan ulir. Pertanyaan untuk pemilik: apakah resolve juga harus ditahan?
- Task 8 (di luar rencana): `'reviewer:reviewer_type'` tanpa kunci relasi membuat penulis ulir
  selalu null di tiga layar pengajuan, sehingga "Tandai Selesai" tak pernah muncul; kini
  `reviewer:id,user_id,reviewer_type`. Pola yang sama memperbaiki pengelompokan endpoint JSON
  `admin.submissions.form-assignments`. Deklarasi kanal `RevisionResolved` kini ikut migration
  (`insertOrIgnore` dari `NotificationEventCatalog::defaults()`, `down()` kosong), bukan hanya
  seeder, supaya database yang sudah berjalan tidak menjawab 500 saat resolve.

---
---

# Rencana Implementasi

Urutan: cabut yang mati (Task 1), lalu tutup cacat bisnis (Task 2–4), lalu bentuk (Task 5),
lalu ukur dan keluarkan dari daftar (Task 6). Task 2–4 lebih dulu karena masing-masing
mengubah perilaku dan butuh test merah; Task 5 murni refactor dengan jaring test yang ada.

---

### Task 1: Cabut tiga endpoint JSON yang tak dipanggil (F1)

**Files:**
- Modify: `routes/web.php` — buang `submissions.available-reviewers`,
  `submissions.available-evaluation-forms`, `submissions.evaluation-status` (grup `admin.`)
- Modify: `app/Http/Controllers/ReviewController.php` — buang `getAvailableReviewers`,
  `getAvailableEvaluationForms`, `getEvaluationStatus`, `getSubmissionFormPhase`
- Modify: `resources/js/ziggy.js` — buang tiga entrinya bila ada
- Create: `tests/Feature/Review/DeadReviewEndpointsRemovedTest.php`

- [ ] **Step 1: Pastikan tiga centang K2 masih berlaku**

```bash
for n in available-reviewers available-evaluation-forms evaluation-status; do
  grep -rn "$n" resources/js tests --include=*.vue --include=*.ts --include=*.php | grep -v ziggy.js
done   # harus kosong
grep -rn "available-reviewers\|evaluation-status" docs/requirement-analysis/bdd   # harus kosong
```

- [ ] **Step 2: Tulis test yang gagal**

```php
<?php

// tests/Feature/Review/DeadReviewEndpointsRemovedTest.php
//
// Tiga endpoint JSON tanpa pemanggil. Daftar reviewer layar Operator dirakit
// adminShowSubmission, dan BR-REV-03 ditegakkan AssignReviewer.

use Illuminate\Support\Facades\Route;

it('tidak menyediakan endpoint JSON review yang tak dipanggil', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with([
    'admin.submissions.available-reviewers',
    'admin.submissions.available-evaluation-forms',
    'admin.submissions.evaluation-status',
]);
```

- [ ] **Step 3: Jalankan, pastikan GAGAL** (3 failed), **cabut**, jalankan lagi (3 passed).
  Buang import yang yatim (`FormPhase`, `EvaluationProgress`, `EvaluationFormsOverview`,
  `PendingEvaluationCheck` di controller). Query-nya **tidak** dihapus (K1).

- [ ] **Step 4: Commit**

```bash
CI ./vendor/bin/pint -q
git add -A routes app resources tests
git commit -m "refactor(review): cabut tiga endpoint JSON review yang tak dipanggil"
```

---

### Task 2: Status ulir hanya lewat resolve (F2, K2)

**Files:**
- Modify: `app/Actions/Review/ResolveReviewSummary.php` — guard menuntut penugasan aktif
- Modify: `routes/web.php` — buang ketiga `review-summaries.update-status`
- Modify: `app/Http/Controllers/ReviewController.php` — buang `updateReviewStatus`
- Modify: `resources/js/Components/ReviewSystem.vue` — dialog status → tombol resolve
- Modify: `resources/js/ziggy.js` — buang entri `*.review-summaries.update-status`
- Modify: `tests/Feature/Review/ReplacedReviewerAccessTest.php` — test "mengubah status ulir"
  pindah ke route `review-summaries.resolve`
- Create: `tests/Feature/Review/ReviewSummaryStatusEndpointRemovedTest.php`

- [ ] **Step 1: Pindahkan test reviewer-yang-diganti ke route resolve**

Di `ReplacedReviewerAccessTest`, test `'menolak reviewer yang sudah diganti mengubah status ulir'`
kini memanggil `->post(route('review-summaries.resolve', $summary))` (tanpa body) dan tetap
menegaskan `assertForbidden()` serta status ulir tetap `open`. Fixture-nya tidak berubah:
ulir open yang `reviewer_id`-nya masih milik reviewer yang sudah diganti.

- [ ] **Step 2: Tulis test route tercabut**

```php
<?php

// tests/Feature/Review/ReviewSummaryStatusEndpointRemovedTest.php
//
// BR-REV-13, [REV-DISCUSS-04] — ulir hanya ditutup reviewer lewat
// ResolveReviewSummary. Jalur kedua mengizinkan Operator menutup dan siapa pun
// membuka kembali, dua hal yang tidak dikenal domain.

use Illuminate\Support\Facades\Route;

it('tidak menyediakan jalur kedua untuk mengubah status ulir', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with([
    'review-summaries.update-status',
    'admin.review-summaries.update-status',
    'reviewer.review-summaries.update-status',
]);
```

- [ ] **Step 3: Jalankan keduanya, pastikan GAGAL** — test reviewer-diganti merah karena
  `ResolveReviewSummary` meloloskannya (302), tiga test route merah.

- [ ] **Step 4: Perbaiki guard**

Di `ResolveReviewSummary::guardReviewer`, syarat lolos menjadi: reviewer ada,
`$summary->reviewer_id === $reviewer->id`, **dan**
`ActiveReviewerAssignment::run($summary->formSubmission, $reviewer->id) !== null`.
Tambahkan satu baris komentar `BR-SM-07, BR-REV-10`. Pesan pengecualian tetap.

- [ ] **Step 5: Cabut route dan method; alihkan `ReviewSystem.vue`**

Di `ReviewSystem.vue`:
- `updateReviewSummaryStatus` memanggil
  `router.post(route('review-summaries.resolve', selectedReviewSummary.value.id), {}, { preserveScroll: true, onSuccess: … })`.
- `canUpdateReviewStatus(reviewSummary)` → `reviewSummary.status === 'open' && isAssignedReviewer.value && !props.hasPendingEvaluations`
  (cabang `isAdmin` dibuang).
- Dialog status kehilangan pilihan status dan catatan: jadi dialog konfirmasi
  "Tandai ulir ini selesai?" dengan tombol "Tandai Selesai". Buang `reviewStatusOptions`,
  `selectedReviewStatus`, `statusUpdateNotes` bila tak terpakai lagi; **pertahankan**
  `getReviewStatusInfo` atau padanannya bila dipakai lencana status ulir.
- Label tombol pemicu jadi "Tandai Selesai".

`route` diimpor dari `ziggy-js` seperti berkas lain; `FrontendRouteNamesTest` memastikan
nama itu terdaftar.

- [ ] **Step 6: Jalankan, pastikan LULUS**, lalu `npm run quality` dan
  `CI ./vendor/bin/pest tests/Feature/Review tests/Feature/Http`.

- [ ] **Step 7: Commit**

```bash
CI ./vendor/bin/pint -q
git add -A routes app resources tests
git commit -m "fix(review): status ulir hanya lewat resolve oleh reviewer yang bertugas"
```

---

### Task 3: Unduh lampiran review ter-scope (F3, K4)

**Files:**
- Create: `app/Queries/Review/ReviewAttachmentSubmission.php`
- Create: `app/Http/Controllers/Review/ReviewAttachmentDownloadController.php`
- Modify: `routes/web.php` — route `review-attachments.download` menunjuk controller baru
- Modify: `app/Http/Controllers/ReviewController.php` — buang `downloadAttachment`
- Create: `tests/Feature/Review/ReviewAttachmentDownloadTest.php`

- [ ] **Step 1: Tulis test yang gagal**

Pakai `Storage::fake('public')`. Fixture: pengajuan `UnderReview` milik pengaju, satu
reviewer aktif, satu `ReviewSummary` ber-`ReviewSummaryAttachment` dengan
`file_path = 'review-attachments/{id}/catatan.pdf'` yang ditulis ke disk palsu. Test:

1. pengaju mengunduh lampiran ulir pengajuannya → `assertOk()`;
2. pengguna lain tanpa akses → `assertForbidden()`;
3. berkas yang ada di disk tapi **bukan** lampiran review (mis. `avatars/x.png`) →
   `assertNotFound()`;
4. lampiran komentar (`ReviewCommentAttachment`) mengikuti aturan yang sama — pengaju
   `assertOk()`.

Test 2 dan 3 harus merah terhadap kode lama (keduanya 200).

- [ ] **Step 2: Query**

```php
/**
 * BR-SM-07 — pengajuan pemilik satu lampiran review. `null` bila berkas itu bukan
 * lampiran ulir atau komentar mana pun.
 *
 * @method static FormSubmission|null run(string $path)
 */
class ReviewAttachmentSubmission implements ChangesNoState
```

Cari `ReviewSummaryAttachment::where('file_path', $path)` lalu
`ReviewCommentAttachment::where('file_path', $path)`; kembalikan
`->reviewSummary->formSubmission` (komentar: `->reviewComment->reviewSummary->formSubmission`).
Sesuaikan nama relasi dengan model; jangan menambah relasi baru tanpa perlu.

- [ ] **Step 3: Controller invokable**

```php
public function __invoke(Request $request): BinaryFileResponse
{
    $path = (string) $request->query('path');
    $submission = ReviewAttachmentSubmission::run($path);

    abort_if($submission === null || !Storage::disk('public')->exists($path), 404, 'File tidak ditemukan');
    abort_unless($submission->isAccessibleBy($request->user()), 403);

    return response()->download(Storage::disk('public')->path($path));
}
```

Sesuaikan dengan tier PHPStan (user bisa `null` di tipe; pakai guard `abort_unless($user !== null, 403)`
seperti `ReviewSummaryResolutionController`).

- [ ] **Step 4: Jalankan, pastikan LULUS; commit**

```bash
CI ./vendor/bin/pint -q
git add -A routes app tests
git commit -m "fix(review): unduh lampiran review hanya bagi yang boleh melihat pengajuannya"
```

---

### Task 4: Komentar ulir lewat Action (F4, K3)

**Files:**
- Create: `app/Actions/Review/AddReviewComment.php`
- Modify: `app/Http/Controllers/ReviewController.php` — `addComment` jadi cangkang
- Create: `tests/Feature/Review/ReviewCommentTest.php`

- [ ] **Step 1: Tulis test**

Lewat HTTP (`review-comments.store`, `admin.review-comments.store`,
`reviewer.review-comments.store` — pilih yang sesuai peran):

1. `[REV-DISCUSS-02]` pengaju membalas ulir open → redirect sukses, komentar tersimpan
   dengan `user_id` pengaju dan `reviewer_id` null (karakterisasi, hijau terhadap kode lama);
2. reviewer aktif yang evaluasinya beres berkomentar → tersimpan dengan `reviewer_id`-nya
   (karakterisasi);
3. reviewer aktif yang evaluasinya **belum** beres → 403 (karakterisasi);
4. `parent_comment_id` milik ulir lain → ditolak (`assertSessionHasErrors('parent_comment_id')`),
   tidak ada komentar baru — **merah** terhadap kode lama;
5. komentar tercatat di audit (`Activity::inLog(AuditTrail::LOG_NAME)` berdeskripsi
   `'Komentar review ditambahkan'`) — **merah** terhadap kode lama.

- [ ] **Step 2: Action**

```php
/**
 * BR-REV-06 — satu komentar pada ulir diskusi.
 *
 * @method static ReviewComment run(ReviewSummary $summary, User $author, string $text, ?int $parentId = null)
 */
class AddReviewComment
```

- Otorisasi (K3) di Action, melempar `AuthorizationException` dengan pesan yang sama seperti
  hari ini (termasuk pesan jumlah form tertunda). Reviewer dicari lewat
  `ActiveReviewerAssignment`.
- Induk komentar: bila `$parentId` tidak null dan bukan komentar di `$summary`, lempar
  `ValidationException::withMessages(['parent_comment_id' => …])`.
- Simpan dan kirim `notifyReviewComment` di dalam satu `DB::transaction`, lalu
  `AuditTrail::record('Komentar review ditambahkan', $comment, $author)`.

Controller: validasi tetap, lalu `AddReviewComment::run(...)`, `AuthorizationException`
dipetakan ke `abort(403, $e->getMessage())`, redirect sukses tetap.

- [ ] **Step 3: Jalankan, pastikan LULUS; gerbang penuh; commit**

```bash
CI ./vendor/bin/pint -q
git add -A app tests
git commit -m "refactor(review): komentar ulir lewat Action beraudit dan induk satu ulir"
```

---

### Task 5: Penugasan reviewer jadi Action batch (K5)

**Files:**
- Create: `app/Actions/Review/AssignReviewers.php`
- Modify: `app/Http/Controllers/ReviewController.php` — `assignReviewers` jadi cangkang;
  buang `autoAssignEvaluationForms`

- [ ] **Step 1: Jaring** — `CI ./vendor/bin/pest tests/Feature/Review/AssignReviewersHttpTest.php tests/Feature/EndToEnd/SubmissionReviewFlowTest.php`
  hijau sebelum mulai.

- [ ] **Step 2: Pindahkan** isi `DB::transaction` beserta `autoAssignEvaluationForms` ke
  `AssignReviewers::handle(FormSubmission $submission, array $reviewerIds, bool $autoAssignForms): array`
  (`@param list<int> $reviewerIds`, `@return list<string>` peringatan). Komentar BR yang ada
  ikut pindah, dipadatkan ke ≤ 3 baris prosa per blok tanpa membuang fakta latarnya
  (BR-REV-01/02/03/04/09, BR-FE-06, BR-FE-17, REV-ASSIGN-09). Satu
  `AuditTrail::record('Reviewer ditugaskan', $submission)` setelah transaksi.
  Tambah `integer` pada validasi `reviewer_ids.*` agar tipe `list<int>` jujur.

- [ ] **Step 3: Jaring hijau lagi, gerbang penuh, commit**

```bash
CI ./vendor/bin/pint -q
git add -A app
git commit -m "refactor(review): penugasan reviewer lewat satu Action batch"
```

---

### Task 6: Ukur dan keluarkan dari daftar

- [ ] **Step 1:** `php tools/limits.php app/Http/Controllers/ReviewController.php` — harus
  bersih. Bila masih ada pelanggaran, perbaiki dengan teknik yang disebut namanya
  (Extract Method / Move Method), bukan dengan memadatkan kode.
- [ ] **Step 2:** Hapus baris `app/Http/Controllers/ReviewController.php` dari
  `quality-legacy.txt`; `max.legacy.files` 121 → 120 di `quality-budget.txt`.
- [ ] **Step 3:** Gerbang penuh, dua-duanya.
- [ ] **Step 4:** Status dokumen ini → `selesai <tanggal>, mendarat di <hash>`; catat
  penyimpangan di *Yang berbeda dari rencana*.
- [ ] **Step 5: Commit**

```bash
git add quality-legacy.txt quality-budget.txt docs/design/refactor-plan-wave-3-review-controller.md
git commit -m "refactor(review): ReviewController keluar dari daftar legacy"
```
