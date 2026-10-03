# Gelombang 3 bagian 1 — satu definisi penugasan reviewer aktif

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development`
> (disarankan) atau `superpowers:executing-plans`. Langkahnya memakai checkbox (`- [ ]`).
>
> **Dokumen ini spec sekaligus rencana.** Seluruh temuan di bawah sudah dibuktikan
> lewat test merah terhadap kode `7fb11a1`, bukan ditaksir.

**Goal:** Reviewer yang sudah diganti kehilangan seluruh wewenangnya atas pengajuan,
lewat **satu** definisi "penugasan reviewer yang masih berlaku"; dan endpoint yang
membiarkan reviewer menyetel status pengajuan sendiri dicabut.

**Architecture:** Query baru `Queries/Review/ActiveReviewerAssignment` menggantikan
sebelas pencarian ad-hoc di controller dan model. Satu test arsitektur menolak
pencarian ad-hoc baru. Strangler, bukan bedah controller: yang diubah hanya cara
controller menemukan penugasan, bukan bentuk controllernya.

**Tech Stack:** Laravel 13 · PHP 8.4 · Pest · PHPStan tier strict level 10

**Spec induk:** [`docs/design/refactor-plan.md`](refactor-plan.md) §2.1, §5 gelombang 3

**Status:** belum dikerjakan.

---

## Kenapa ini didahulukan dari pembedahan controller

Sensus gelombang 3 dimulai dari `SubmissionViewController` dan IA v2
(`docs/requirement-analysis/ux/ia/02_submission/index.md`). Di tengah jalan ia
menemukan cacat yang lebih mendesak daripada ukuran berkas: **wewenang reviewer**.

Ada tiga belas pencarian "penugasan reviewer ini atas pengajuan ini" di `app/`. Hanya
dua yang menyaring `status = active`: `ReviewFormResponseController:71` dan gerbang
halaman reviewer yang baru diperbaiki di `7fb11a1`. Sebelas sisanya tidak — padahal
kelas berdisiplin DDD (`BuildReviewerDashboard`, `ReplaceReviewer`,
`EvaluateSubmissionOutcome`) dan definisi resmi BR-SM-07,
`FormSubmission::isAccessibleBy()`, semuanya menyaring.

Reviewer yang diganti (BR-REV-10) **tetap memegang record-nya**, dengan
`status = replaced`. Bila evaluasinya selesai sebelum ia diganti, gerbang "evaluasi
beres" pun meloloskannya. Enam jalur tulis terbukti bisa dipakai:

| Jalur | Akibat |
| ----- | ------ |
| `ReviewController::createReviewThread` | membuka ulir baru — ulir terbuka **menahan persetujuan** (BR-REV-06) |
| `ReviewController::addComment` | berkomentar di ulir orang lain |
| `ReviewController::updateReviewStatus` | membuka atau menutup ulirnya sendiri |
| `ReviewController::assignEvaluationFormsToReviewer` | form evaluasi ditugaskan ke orang yang sudah tidak bertugas |
| `ReviewerFormAssignmentController::bulkAssignForms` | idem, lewat jalur massal |
| `ReviewController::removeReviewer` | **menghapus riwayatnya** beserta ulir dan komentarnya, melanggar BR-REV-10 |

Gerbang halaman `reviewer.submissions.show` sudah ditutup lebih dulu di `7fb11a1`
karena ia kebocoran kerahasiaan yang berlaku hari ini.

**Endpoint yang dicabut.** `PATCH /submissions/{id}/status`
(`ReviewController::updateSubmissionStatus`) membiarkan reviewer mana pun yang
evaluasinya selesai — termasuk yang sudah diganti — menyetel pengajuan ke `approved`
atau `rejected`, tanpa jejak audit. Itu bertentangan dengan BR-REV-05/06 (hasil
**otomatis**) dan BR-REV-07 (penolakan dikonfirmasi manual **oleh Operator**). Pemilik
repo memutuskan pada 3 Oktober 2026: **cabut seluruhnya**, termasuk untuk Operator.
Penolakan tetap tersedia lewat jalur resminya, `RejectSubmission`.

---

## Vonis sebelas tempat

Penanda: `ReviewController` = RC, `SubmissionViewController` = SVC,
`ReviewerFormAssignmentController` = RFAC. Nomor baris diukur pada `7fb11a1`.

| Tempat | Vonis | Task |
| ------ | ----- | ---- |
| RC:117 `removeReviewer` | bug — menghapus riwayat reviewer yang diganti | 4 |
| RC:195 `updateSubmissionStatus` | dicabut bersama endpointnya | 2 |
| RC:294 `createReviewThread` | bug | 3 |
| RC:362 `addComment` | bug | 3 |
| RC:440 `assignEvaluationFormsToReviewer` | bug | 4 |
| RC:566 `updateReviewStatus` | bug | 3 |
| RFAC:131 `bulkAssignForms` | bug | 4 |
| SVC:340 `userShowSubmission` | tampilan; tak terjangkau reviewer yang diganti (gerbang `isAccessibleBy` di depannya) | 5 |
| SVC:803 `adminShowSubmission` | tampilan; route admin | 5 |
| SVC:934 `canUserReview` | tampilan | 5 |
| `ReviewSummary::discussionsAllowed()` | **nol pemanggil** — dicabut, bukan diperbaiki | 5 |

**Tidak diubah:** RC:475 `getAvailableReviewers`. Ia sengaja ikut menghitung reviewer
yang sudah diganti: BR-REV-03 — satu reviewer satu penugasan per siklus — berlaku
juga bagi yang pernah diganti.

---

## Keputusan yang sudah diambil

### K1 — Satu Query, bukan scope di Model

`ActiveReviewerAssignment::run(FormSubmission $submission, int $reviewerId): ?SubmissionReviewer`.
Query di `app/Queries/` otomatis kena tier strict; scope di Model tidak (§2.2).
Parameter reviewer berupa `int` karena `bulkAssignForms` hanya memegang id dari
request; pemanggil lain cukup mengirim `$reviewer->id`.

### K2 — `where('status', 'active')`, bukan `->active()`

`SubmissionReviewer::scopeActive($query)` tidak bertipe. Memanggilnya dari tier strict
membuat kembaliannya `mixed`. Query menulis syaratnya langsung, seperti
`BuildReviewerDashboard`.

### K3 — Endpoint ubah-status dicabut seluruhnya

Keputusan pemilik repo, 3 Oktober 2026. Yang dicabut:
- route `submissions.update-status` (`routes/web.php`, `PATCH /submissions/{submission}/status`)
- `ReviewController::updateSubmissionStatus()`
- kontrol ubah-status di `resources/js/Components/ReviewSystem.vue` — tombol, dialog,
  `canUpdateSubmissionStatus`, `openUpdateSubmissionStatusDialog`, `updateSubmissionStatus`,
  `showUpdateSubmissionStatusDialog`, `selectedSubmissionStatus`
- `EmailNotificationService::notifySubmissionStatusChanged()` — dua pemanggilnya
  hanya ada di method yang dicabut
- `tests/Feature/Review/SubmissionStatusTransitionTest.php` — empat test, tanpa ID
  skenario BDD, seluruhnya menguji endpoint yang dicabut

Yang **tidak** dicabut: prop `submissionStatus` dan `submissionStatusOptions` di
`ReviewSystem.vue` — keduanya dipakai lencana status (`getSubmissionStatusInfo`).

### K4 — `removeReviewer` menjawab 404, bukan 500

Kalau penugasan aktifnya tidak ada, cabang itu kini melempar `\Exception` mentah tanpa
`catch` — setelah Task 4 itu berarti 500 untuk reviewer yang sudah diganti. Diganti
`abort(404, 'Reviewer tidak ditemukan untuk submission ini.')`; `abort()` di dalam
`DB::transaction` me-rollback sebelum respons.

---

## Global Constraints

- **Identifier bahasa Inggris, prosa komentar bahasa Indonesia, maksimal 3 baris prosa
  berturut-turut, lebar baris komentar ≤ 93 karakter.**
- **Dilarang menyiasati PHPStan** — tanpa `@phpstan-ignore`, baseline, `assert()`,
  inline `@var`, atau cast.
- **Status hanya berubah lewat `transitionTo()`.** (AGENTS.md aturan 1)
- **`pending.txt` dan `uncovered.txt` tidak disentuh.**
- **Pesan commit: satu baris, tanpa badan, tanpa trailer.**
- **Database test:** container `simpas_dev_pg` di :55432 — `docker start simpas_dev_pg`
  bila Pest menjawab `Connection refused`.
- **Container PHP**, disingkat **`CI`**:
  ```bash
  docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
    -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 <perintah>
  ```

---

## Definisi selesai

- [ ] `CI composer run quality` hijau, **961 test** (954 + 3 + 1 − 4 + 3 + 3 + 1)
- [ ] `npm run quality` hijau
- [ ] Keenam test di Task 3–4 merah sebelum perbaikan dan hijau sesudahnya
- [ ] Route `submissions.update-status` tidak terdaftar
- [ ] Test penjaga Task 6 hijau; tanpa perubahan apa pun ia menandai sebelas tempat
- [ ] `getAvailableReviewers` tidak berubah

---

## Yang tidak dikerjakan dokumen ini

- **Transfer ownership digerbang permission *lihat*.** `SubmissionDecisionController::transferOwnership`
  dan `adminShowSubmission` memakai `submissions.view-all`. Hari ini hanya `operator`
  dan `admin` yang memegangnya, jadi belum bisa dieksploitasi — tapi peran baca-saja
  yang kelak diberi `view-all` diam-diam ikut bisa memindahkan kepemilikan. Butuh
  keputusan desain permission (permission baru atau ability Policy).
- **Kontrak "Detail Pengajuan" untuk v2.** IA v2 punya satu layar `/submissions/{id}`
  untuk empat peran; legacy merakitnya di tiga aksi (`userShowSubmission`,
  `adminShowSubmission`, `reviewerShowSubmission`). Matriks visibilitas bagian dan
  aksinya — `SubmissionAbilitiesData` baru mencakup 5 dari 9 aksi IA — dikerjakan di
  bagian 2, sesudah wewenang reviewer punya satu definisi.
- **Celah UX:** halaman reviewer tidak memasang `ReviewSystem`, jadi reviewer belum bisa
  membuka ulir dari layarnya sendiri. Ditutup di layar v2.

---
---

# Rencana Implementasi

**Goal:** Sebelas pencarian ad-hoc diganti satu Query; endpoint ubah-status dicabut.

**Architecture:** Query lebih dulu (Task 1), lalu pencabutan endpoint (Task 2) supaya
satu tempat tidak dikerjakan dua kali, lalu jalur tulis (Task 3–4), tampilan (Task 5),
dan penjaga (Task 6) paling akhir — ia baru bisa hijau setelah semuanya beres.

**Spec:** bagian atas dokumen ini.

## Global Constraints

Seluruh *Global Constraints* dan K1–K4 di atas berlaku untuk setiap Task.

---

### Task 1: `ActiveReviewerAssignment`

**Files:**
- Create: `app/Queries/Review/ActiveReviewerAssignment.php`
- Create: `tests/Feature/Review/ActiveReviewerAssignmentTest.php`

**Interfaces:**
- Produces: `ActiveReviewerAssignment::run(FormSubmission $submission, int $reviewerId): ?SubmissionReviewer`

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Feature/Review/ActiveReviewerAssignmentTest.php
//
// BR-SM-07, BR-REV-10 — satu definisi "penugasan reviewer yang masih berlaku".

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Queries\Review\ActiveReviewerAssignment;

function assignmentOf(FormSubmission $submission, Reviewer $reviewer, string $status): SubmissionReviewer
{
    return SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'completed',
        'status' => $status,
    ]);
}

it('menemukan penugasan yang masih berlaku', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);
    $assignment = assignmentOf($submission, $reviewer, 'active');

    expect(ActiveReviewerAssignment::run($submission, $reviewer->id)?->id)->toBe($assignment->id);
});

it('mengabaikan penugasan yang sudah diganti', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);
    assignmentOf($submission, $reviewer, 'replaced');

    expect(ActiveReviewerAssignment::run($submission, $reviewer->id))->toBeNull();
});

it('tidak tertukar dengan penugasan reviewer lain atau pengajuan lain', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);
    $other = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);
    assignmentOf($submission, $other, 'active');
    assignmentOf(FormSubmission::factory()->create(), $reviewer, 'active');

    expect(ActiveReviewerAssignment::run($submission, $reviewer->id))->toBeNull();
});
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `CI ./vendor/bin/pest tests/Feature/Review/ActiveReviewerAssignmentTest.php`
Expected: FAIL — `Class "App\Queries\Review\ActiveReviewerAssignment" not found`.

- [ ] **Step 3: Tulis Query-nya**

```php
<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\SubmissionReviewer;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-07, BR-REV-10 — penugasan reviewer yang masih berlaku atas satu pengajuan.
 * Record reviewer yang sudah diganti disimpan sebagai sejarah, bukan sebagai wewenang.
 *
 * @method static SubmissionReviewer|null run(FormSubmission $submission, int $reviewerId)
 */
class ActiveReviewerAssignment implements ChangesNoState
{
    use AsAction;

    public function handle(FormSubmission $submission, int $reviewerId): ?SubmissionReviewer
    {
        return SubmissionReviewer::query()
            ->where('form_submission_id', $submission->id)
            ->where('reviewer_id', $reviewerId)
            ->where('status', 'active')
            ->first();
    }
}
```

- [ ] **Step 4: Jalankan, pastikan LULUS**

Run: perintah Step 2. Expected: 3 passed.
Lalu `CI composer run analyse:strict` — `[OK] No errors`.

- [ ] **Step 5: Commit**

```bash
CI ./vendor/bin/pint -q
git add app/Queries/Review/ActiveReviewerAssignment.php tests/Feature/Review/ActiveReviewerAssignmentTest.php
git commit -m "feat(review): satu definisi penugasan reviewer yang masih berlaku"
```

---

### Task 2: Cabut endpoint ubah-status pengajuan (K3)

**Files:**
- Modify: `routes/web.php` — buang route `submissions.update-status`
- Modify: `app/Http/Controllers/ReviewController.php` — buang `updateSubmissionStatus()`
- Modify: `app/Services/EmailNotificationService.php` — buang `notifySubmissionStatusChanged()`
- Modify: `resources/js/Components/ReviewSystem.vue` — buang kontrol ubah-status (K3)
- Delete: `tests/Feature/Review/SubmissionStatusTransitionTest.php`
- Create: `tests/Feature/Review/SubmissionStatusEndpointRemovedTest.php`

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Feature/Review/SubmissionStatusEndpointRemovedTest.php
//
// BR-REV-05/06 — hasil pengajuan dihitung otomatis; BR-REV-07 — penolakan hanya
// lewat aksi eksplisit Operator. Tidak ada jalur untuk menyetel status sendiri.

use Illuminate\Support\Facades\Route;

it('tidak menyediakan jalur menyetel status pengajuan secara langsung', function () {
    expect(Route::has('submissions.update-status'))->toBeFalse();
});
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `CI ./vendor/bin/pest tests/Feature/Review/SubmissionStatusEndpointRemovedTest.php`
Expected: FAIL — route masih terdaftar.

- [ ] **Step 3: Cabut route, method, dan notifikasinya**

Hapus definisi `Route::patch('/submissions/{submission}/status', [ReviewController::class, 'updateSubmissionStatus'])`
beserta `->name('submissions.update-status')`. Hapus `ReviewController::updateSubmissionStatus()`
utuh. Lalu pastikan `notifySubmissionStatusChanged` tak lagi punya pemanggil dan hapus:

```bash
grep -rn "notifySubmissionStatusChanged" app/ | grep -v "function notifySubmissionStatusChanged"   # harus kosong
```

Hapus import yang jadi yatim (Pint `no_unused_imports` membuangnya; periksa
`NeedsRevision`, `Rejected` di `ReviewController`).

- [ ] **Step 4: Cabut kontrol di `ReviewSystem.vue`**

Hapus: `showUpdateSubmissionStatusDialog`, `selectedSubmissionStatus`,
`canUpdateSubmissionStatus`, `openUpdateSubmissionStatusDialog`, `updateSubmissionStatus`,
tombol `v-if="canUpdateSubmissionStatus"`, dan `<Dialog v-model:open="showUpdateSubmissionStatusDialog">`
beserta isinya. **Biarkan** prop `submissionStatus`, `submissionStatusOptions`, dan
`getSubmissionStatusInfo` — lencana status masih memakainya. Buang import yang yatim.

- [ ] **Step 5: Hapus test endpoint lama**

```bash
git rm tests/Feature/Review/SubmissionStatusTransitionTest.php
```

Keempat test-nya tanpa ID skenario BDD; ledger tidak berubah.

- [ ] **Step 6: Jalankan, pastikan LULUS**

Run: perintah Step 2, lalu `npm run quality` dan `CI composer run quality`.
Expected: hijau, **954 test** (957 − 4 + 1).

- [ ] **Step 7: Commit**

```bash
CI ./vendor/bin/pint -q
git add -A routes app resources tests
git commit -m "fix(review): cabut jalur menyetel status pengajuan yang melanggar BR-REV-05 sampai 07"
```

---

### Task 3: Jalur tulis reviewer — ulir, komentar, status ulir

**Files:**
- Create: `tests/Feature/Review/ReplacedReviewerAccessTest.php` (isi lengkap di Lampiran A)
- Modify: `app/Http/Controllers/ReviewController.php` — RC:294, RC:362, RC:566

- [ ] **Step 1: Tulis berkas test dari Lampiran A**

Seluruh enam test-nya ditulis sekarang; tiga yang pertama milik Task ini.

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `CI ./vendor/bin/pest tests/Feature/Review/ReplacedReviewerAccessTest.php`
Expected: **6 failed**. Sudah diverifikasi terhadap `7fb11a1`: ketiga jalur reviewer
menjawab 302 (berhasil) alih-alih 403.

- [ ] **Step 3: Alihkan ketiga pencarian ke Query**

Di `createReviewThread`, `addComment`, dan `updateReviewStatus`, ganti

```php
SubmissionReviewer::where([
    'form_submission_id' => $submission->id,
    'reviewer_id' => $reviewer->id,
])->first()
```

dengan `ActiveReviewerAssignment::run($submission, $reviewer->id)`. Di
`updateReviewStatus` dan `addComment` pengajuannya `$reviewSummary->formSubmission`.
Cabang `null` yang sudah ada menjawab 403 — jangan diubah.

- [ ] **Step 4: Jalankan, pastikan tiga test pertama LULUS**

Run: perintah Step 2. Expected: 3 passed, 3 failed (milik Task 4).

- [ ] **Step 5: Commit hanya controller-nya**

Berkas test belum di-commit: tiga test-nya masih merah sampai Task 4.

```bash
CI ./vendor/bin/pint -q app/Http/Controllers/ReviewController.php
git add app/Http/Controllers/ReviewController.php
git commit -m "fix(review): tolak ulir, komentar, dan status ulir dari reviewer yang sudah diganti"
```

---

### Task 4: Jalur admin — penugasan form dan penghapusan reviewer

**Files:**
- Modify: `app/Http/Controllers/ReviewController.php` — RC:117, RC:440
- Modify: `app/Http/Controllers/ReviewerFormAssignmentController.php` — RFAC:131
- Commit: `tests/Feature/Review/ReplacedReviewerAccessTest.php`

- [ ] **Step 1: Alihkan tiga pencarian ke Query**

`removeReviewer` (RC:117) dan `assignEvaluationFormsToReviewer` (RC:440):
`ActiveReviewerAssignment::run($submission, $reviewer->id)`. `bulkAssignForms` (RFAC:131):
`ActiveReviewerAssignment::run($submission, (int) $reviewerId)` — `$reviewerId` dari
`validated['reviewer_ids']` yang sudah lolos `exists:reviewers,id`; pakai bentuk yang
lolos tier default tanpa cast bila memungkinkan.

- [ ] **Step 2: `removeReviewer` menjawab 404 (K4)**

```php
            if (!$submissionReviewer) {
                abort(404, 'Reviewer tidak ditemukan untuk submission ini.');
            }
```

- [ ] **Step 3: Jalankan, pastikan seluruh enam LULUS**

Run: `CI ./vendor/bin/pest tests/Feature/Review/ReplacedReviewerAccessTest.php`
Expected: 6 passed.

- [ ] **Step 4: Gerbang penuh**

Run: `CI composer run quality` — Expected: hijau, **960 test**.

- [ ] **Step 5: Commit**

```bash
CI ./vendor/bin/pint -q
git add app tests/Feature/Review/ReplacedReviewerAccessTest.php
git commit -m "fix(review): tolak penugasan form dan penghapusan riwayat reviewer yang sudah diganti"
```

---

### Task 5: Jalur tampilan, dan satu method mati

**Files:**
- Modify: `app/Http/Controllers/SubmissionViewController.php` — SVC:340, SVC:803, SVC:934,
  dan gerbang `reviewerShowSubmission` (SVC:1053, kini `SubmissionReviewer::active()->where(...)`)
- Modify: `app/Http/Controllers/ReviewFormResponseController.php:71` — sudah benar, tapi
  dialihkan juga supaya definisinya benar-benar satu
- Modify: `app/Models/ReviewSummary.php` — buang `discussionsAllowed()`

- [ ] **Step 1: Pastikan `discussionsAllowed()` lolos tiga centang K2**

```bash
grep -rn "discussionsAllowed()\|->discussionsAllowed\b" app/ resources/js tests/   # harus kosong
grep -rn "discussionsAllowed" docs/requirement-analysis/bdd/                         # harus kosong
```

Kecocokan `$discussionsAllowed` di `ReviewController` adalah variabel, bukan pemanggil.
Aturan yang dulu ditanyakannya (BR-FE-06) ditegakkan `ReviewSummaryGuard`.

- [ ] **Step 2: Alihkan kelima pencarian ke Query, hapus method mati**

Bentuk `->first()` menjadi `ActiveReviewerAssignment::run($submission, $reviewer->id)`;
bentuk `->exists()` menjadi `ActiveReviewerAssignment::run($submission, $reviewer->id) !== null`.
Di `reviewerShowSubmission`, pertahankan komentar BR-SM-07 yang sudah ada di atasnya.

- [ ] **Step 3: Gerbang penuh**

Run: `CI composer run quality` — Expected: hijau, 960 test. Test halaman reviewer
(`ReviewerSubmissionPageTest`, termasuk kasus reviewer yang diganti) adalah jaringnya.

- [ ] **Step 4: Commit**

```bash
CI ./vendor/bin/pint -q
git add app
git commit -m "refactor(review): satukan pencarian penugasan reviewer di layar pengajuan"
```

---

### Task 6: Penjaga — tolak pencarian ad-hoc baru

**Files:**
- Create: `tests/Unit/Architecture/ReviewerAssignmentLookupTest.php`

Pola penjaga ini sudah diuji terhadap `7fb11a1`: ia menandai **persis** sebelas tempat di
tabel vonis, tidak menandai `getAvailableReviewers`, dan tidak menandai
`BuildReviewerDashboard` maupun `ReviewerWorkload`.

- [ ] **Step 1: Tulis test-nya**

```php
<?php

// tests/Unit/Architecture/ReviewerAssignmentLookupTest.php
//
// BR-SM-07, BR-REV-10 — tiga belas pencarian ad-hoc dulu lupa menyaring reviewer
// yang sudah diganti. Satu definisi hanya bertahan kalau yang baru tak bisa lahir.

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

uses(TestCase::class);

it('penugasan reviewer atas satu pengajuan dicari lewat ActiveReviewerAssignment', function () {
    $violations = [];

    foreach (Finder::create()->files()->in(app_path())->name('*.php')->sortByName() as $file) {
        $path = 'app/'.$file->getRelativePathname();

        if ($path === 'app/Queries/Review/ActiveReviewerAssignment.php') {
            continue;
        }

        preg_match_all('/SubmissionReviewer::(?:query\(\)\s*->\s*)?where\(.*?;/s', $file->getContents(), $found);

        foreach ($found[0] as $statement) {
            $byReviewer = str_contains($statement, "'reviewer_id' =>")
                || preg_match("/where\(\s*'reviewer_id'/", $statement) === 1;

            // Mencari satu reviewer atas satu pengajuan tanpa menyaring yang sudah
            // diganti. Yang hanya menyaring per reviewer, atau sengaja menghitung
            // semua penugasan (BR-REV-03), tidak termasuk.
            if ($byReviewer && str_contains($statement, 'form_submission_id') && !str_contains($statement, "'active'")) {
                $violations[] = $path;
            }
        }
    }

    expect($violations)->toBe([], "Pakai ActiveReviewerAssignment::run():\n".implode("\n", array_unique($violations)));
});
```

- [ ] **Step 2: Jalankan, pastikan LULUS**

Run: `CI ./vendor/bin/pest tests/Unit/Architecture/ReviewerAssignmentLookupTest.php`
Expected: 1 passed. Kalau merah, ia menyebut berkas yang terlewat di Task 3–5.

- [ ] **Step 3: Buktikan ia tidak lolos secara hampa**

Kembalikan sementara satu berkas ke bentuk `7fb11a1`, jalankan, lalu pulihkan:

```bash
git show 7fb11a1:app/Http/Controllers/ReviewerFormAssignmentController.php \
  > app/Http/Controllers/ReviewerFormAssignmentController.php
CI ./vendor/bin/pest tests/Unit/Architecture/ReviewerAssignmentLookupTest.php   # harus MERAH, menyebut RFAC
git checkout -- app/Http/Controllers/ReviewerFormAssignmentController.php
git status --short                                                              # hanya berkas test baru
```

- [ ] **Step 4: Gerbang penuh, dua-duanya**

Run: `CI composer run quality` — Expected: hijau, **961 test**. Run: `npm run quality` — hijau.

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/Architecture/ReviewerAssignmentLookupTest.php
git commit -m "test(architecture): tolak pencarian penugasan reviewer di luar satu definisinya"
```

---

### Task 7: Penutup

- [ ] **Step 1:** Ubah **Status** di atas jadi `selesai <tanggal>, mendarat di <hash>`.
- [ ] **Step 2:** Catat penyimpangan dari rencana, bila ada, di bagian baru
  *Yang berbeda dari rencana*.
- [ ] **Step 3: Commit**

```bash
git add docs/design/refactor-plan-wave-3-reviewer-access.md
git commit -m "docs(design): catat hasil gelombang 3 bagian 1"
```

---

## Lampiran A — `tests/Feature/Review/ReplacedReviewerAccessTest.php`

Diverifikasi merah terhadap `7fb11a1`: **6 failed**. Fixture-nya menyertakan reviewer
aktif lain dengan sengaja — tanpa itu `removeReviewer` gagal di transisi balik ke
SUBMITTED dan me-rollback semuanya, sehingga test lolos karena kebetulan.

```php
<?php

// tests/Feature/Review/ReplacedReviewerAccessTest.php
//
// BR-SM-07, BR-REV-10 — record reviewer yang sudah diganti disimpan sebagai
// sejarah, bukan sebagai wewenang. Fixture-nya sengaja realistis: evaluasinya
// selesai sebelum diganti, jadi gerbang "evaluasi beres" saja tidak menolaknya.

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use Spatie\Permission\Models\Permission;

/** @return array{0: User, 1: Reviewer, 2: FormSubmission, 3: SubmissionReviewer} */
function replacedReviewerFixture(): array
{
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);

    $submission = FormSubmission::factory()->create([
        'form_phase_detail_id' => $detail->id,
        'submitted_by' => User::factory()->create()->id,
        'status' => UnderReview::class,
    ]);

    $user = User::factory()->create();
    $reviewer = Reviewer::factory()->create([
        'user_id' => $user->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);

    $assignment = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'completed',
        'status' => 'replaced',
    ]);

    return [$user, $reviewer, $submission, $assignment];
}

function replacedReviewerOperator(): User
{
    Permission::findOrCreate('users.manage', 'web');

    $operator = User::factory()->create();
    $operator->givePermissionTo('users.manage');

    return $operator;
}

/** Ulir milik reviewer lain yang masih aktif. */
function replacedReviewerOpenThread(FormSubmission $submission): ReviewSummary
{
    $other = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $other->id,
        'evaluation_status' => 'completed',
        'status' => 'active',
    ]);

    return ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $other->id,
        'status' => 'open',
        'summary_notes' => 'Perkuat metodologi.',
    ]);
}

it('menolak reviewer yang sudah diganti membuka ulir baru', function () {
    [$user, , $submission] = replacedReviewerFixture();

    $this->actingAs($user)
        ->post(route('reviewer.review-threads.store', $submission), ['summary_notes' => 'Catatan.'])
        ->assertForbidden();

    expect(ReviewSummary::count())->toBe(0);
});

it('menolak reviewer yang sudah diganti berkomentar di ulir', function () {
    [$user, , $submission] = replacedReviewerFixture();
    $thread = replacedReviewerOpenThread($submission);

    $this->actingAs($user)
        ->post(route('reviewer.review-comments.store', $thread), ['comment_text' => 'Saya setuju.'])
        ->assertForbidden();
});

it('menolak reviewer yang sudah diganti mengubah status ulir', function () {
    [$user, $reviewer, $submission] = replacedReviewerFixture();

    // Ulirnya sendiri yang belum sempat diwariskan (BR-REV-15): yang diuji
    // penugasannya, bukan kepemilikan ulir.
    $thread = ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'open',
        'summary_notes' => 'Catatan lama.',
    ]);

    $this->actingAs($user)
        ->patch(route('reviewer.review-summaries.update-status', $thread), ['status' => 'resolved'])
        ->assertForbidden();

    expect($thread->fresh()?->status)->toBe('open');
});

it('menolak menugaskan form evaluasi kepada reviewer yang sudah diganti', function () {
    [, $reviewer, $submission] = replacedReviewerFixture();
    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $submission->form_phase_detail_id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    $this->actingAs(replacedReviewerOperator())
        ->post(route('admin.submissions.assign-evaluation-forms', [$submission, $reviewer]), [
            'form_assignments' => [['form_id' => $form->id, 'is_required' => true, 'due_date' => null]],
        ])
        ->assertSessionHasErrors('error');

    expect(ReviewerFormAssignment::count())->toBe(0);
});

it('menolak penugasan massal kepada reviewer yang sudah diganti', function () {
    [, $reviewer, $submission] = replacedReviewerFixture();
    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $submission->form_phase_detail_id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    $this->actingAs(replacedReviewerOperator())
        ->post(route('admin.submissions.assign-forms-bulk', $submission), [
            'reviewer_ids' => [$reviewer->id],
            'form_assignments' => [['form_id' => $form->id, 'is_required' => true, 'due_date' => null]],
        ]);

    expect(ReviewerFormAssignment::count())->toBe(0);
});

it('tidak menghapus record reviewer yang sudah diganti — BR-REV-10', function () {
    [, $reviewer, $submission, $assignment] = replacedReviewerFixture();

    // Penggantinya masih bertugas. Tanpa reviewer aktif lain, transisi balik ke
    // SUBMITTED gagal dan me-rollback semuanya — test akan lolos karena kebetulan.
    replacedReviewerOpenThread($submission);

    $this->actingAs(replacedReviewerOperator())
        ->delete(route('admin.submissions.remove-reviewer', [$submission, $reviewer]))
        ->assertNotFound();

    expect(SubmissionReviewer::query()->whereKey($assignment->id)->exists())->toBeTrue();
});
```
