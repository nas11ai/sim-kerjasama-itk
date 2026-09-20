# Refactor Gelombang 2 (bagian 1) — `FormSubmission`

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development`
> (disarankan) atau `superpowers:executing-plans`. Langkahnya memakai checkbox (`- [ ]`).
>
> **Seluruh rencana ini sudah dijalankan.** Task 1–14 tinggal sebagai catatan; kotaknya
> sudah tercentang. Yang tersisa sebagai keputusan terbuka ada di *Status pelaksanaan
> bagian 2*.

**Goal:** Mengosongkan `FormSubmission` dari logic yang bukan miliknya. Bagian kedua goal
ini — "sampai ia keluar dari `quality-legacy.txt`" — **terbukti mustahil**; lihat Task 6.

**Architecture:** Buang dulu, runtuhkan kedua, pindahkan terakhir. Seperenam logic-nya
kode mati dan empat method menjawab satu pertanyaan yang sama — memindahkannya lebih
dulu berarti memindahkan sampah ke rumah baru.

**Tech Stack:** Laravel 13 · PHP 8.4 · Pest · PHPStan level 10

**Spec:** [`docs/design/refactor-plan.md`](refactor-plan.md) v1.5 §2.5

**Status:** bagian 1 dieksekusi 20 September 2026 di branch `feat/init-be-development`,
commit `5e44f54`..`98af85b`. Task 1–5 selesai; Task 6 tidak bisa dijalankan.
**Bagian 2 (Task 7–14) selesai** di `29debb0`..`83a4610` — lihat *Status pelaksanaan
bagian 2*.

---

## Status pelaksanaan

`FormSubmission` turun **697 → 373 baris**. `composer run quality` dan `npm run quality`
hijau: 876 test, 1912 assertion.

### Yang berubah dari rencana

| Rencana | Kenyataan | Sebab |
| ------- | --------- | ----- |
| `AssignReviewerSummary::run($submission, $reviewerId)` | butuh argumen ketiga `$notes` | `summary_notes` `text()` tanpa `nullable` di migration `2025_09_09_023253` — kontrak dua argumen akan kena NOT NULL |
| `EvaluationFormsOverview` memuat ringkasan **dan** syaratnya | syaratnya berdiri sendiri sebagai `EvaluationRequirements` | cabang `'No form phase detail found.'` di method lama tak terjangkau, jadi dibuang |
| sepuluh berkas baru di Peta Berkas | **lima dibuat lalu dihapus** | nol pemanggil produksi — lihat di bawah |

### Sensus kode mati Task 1 meleset — ada lima method lagi

Task 1 menemukan 5 method nol rujukan. **Lima lagi lolos** dan terlanjur dipindahkan
oleh Task 3–5:

| Method model | Sempat jadi | Pemanggil produksi |
| ------------ | ----------- | -----------------: |
| `assignReviewer` | `Actions/Review/AssignReviewerSummary` | 0 |
| `removeReviewer` | `Actions/Review/RemoveReviewerSummary` | 0 |
| `autoAssignEvaluationForms` | `Actions/Review/AutoAssignEvaluationForms` | 0 |
| `reviewersWithCompletedEvaluations`, `reviewersWithPendingEvaluations` | `Queries/Review/ReviewersByEvaluationStatus` | 0 |
| `hasOpenReviewThreads` | `Queries/Review/ReviewThreadCount` | 0 |

Kelimanya sudah nol rujukan **sebelum** gelombang ini dimulai — persis yang diperingatkan
di bagian Architecture: memindahkan sampah ke rumah baru. Jalur yang benar-benar hidup
ada di tempat lain: `ReviewController` memakai `autoAssignEvaluationForms()` dan
`getEvaluationDueDate()` miliknya sendiri, dan penugasan reviewer yang sebenarnya sudah
pindah ke `Actions/Review/AssignReviewer` di `24f2e42`. Kelima kelas itu **dihapus**,
bersama `tests/Feature/Review/ReviewerSummaryActionTest.php` dan test `ReviewThreadCount`.

**Sebabnya, supaya tidak berulang:** Task 1 menghitung pemanggil hanya untuk method yang
sudah *dicurigai* mati, sementara Task 3–5 menganggap method yang dipindahkan pasti punya
pemanggil. Task 5 kebetulan punya pagarnya ("kalau pemanggilnya nol, hapus saja"); Task 3
dan Task 4 tidak. **Rencana model berikutnya wajib menghitung pemanggil setiap method
sebelum memindahkannya, bukan hanya yang terlihat mencurigakan.**

### Utang yang ditinggalkan gelombang ini

`Queries/Submission/EvaluationDueDate` kini hanya dipakai test. Ia **tidak** ikut dihapus
karena ia versi benar dari `ReviewController::getEvaluationDueDate()` yang masih hidup dan
masih menebak period lewat pivot `submission_period_phases` — bug yang ADR 0004 dibuat
untuk membunuhnya, plus fallback diam-diam `new DateTime('+7 days')`. Menyambungkan
controller ke Query ini mengubah perilaku (tenggat bisa jadi `null`), jadi ia tugas
tersendiri dengan keputusannya sendiri, bukan sisipan di gelombang model.

---

## Global Constraints

- **Identifier bahasa Inggris**; prosa komentar bahasa Indonesia, **maksimal 3 baris**.
- **Dilarang menyiasati PHPStan** — tanpa `@phpstan-ignore`, baseline, `assert()`, inline
  `@var`, cast, atau pelebaran tipe. (AGENTS.md aturan 5)
- **Status hanya berubah lewat `transitionTo()`.** (AGENTS.md aturan 1)
- **Setiap kueri yang mengembalikan data submission wajib di-scope** `isAccessibleBy()`.
  (AGENTS.md aturan 2, BR-SM-07)
- **Guard clause, bukan `if` bersarang.** Satu Action = satu use case. (AGENTS.md aturan 8)
- **Verifikasi wajib:** `composer run quality` hijau sebelum tugas dinyatakan selesai.
  Suite acuan saat rencana ini ditulis: **867 test**.
- **Container:**
  ```bash
  docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
    -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 <perintah>
  ```
- **Pesan commit: satu baris, tanpa badan, tanpa trailer.**

---

## Hasil pembacaan `FormSubmission` (697 baris)

Dibaca method per method, bukan digolongkan otomatis — percobaan otomatis salah di
dua dari dua yang diperiksa acak.

| Golongan | Jumlah | Keterangan |
| -------- | -----: | ---------- |
| Relasi murni | 21 | `hasMany`/`belongsTo`/`hasManyThrough` — tidak disentuh |
| **Kode mati** | **5** | nol rujukan di seluruh repo |
| **Predikat kembar** | **3** | empat nama menjawab satu pertanyaan |
| → `Actions/` | 3 | mengubah state |
| → `Queries/` | 12 | menjawab untuk ditampilkan |
| Tetap di Model | 7 | invarian domain, spec §2.5 |

### Kode mati — nol rujukan

`reviewerCanParticipate`, `allReviewersApproved`, `hasRevisionsRequested`,
`getActiveReviewThreadsCount`, `getResolvedReviewThreadsCount`

`hasRevisionsRequested` bahkan identik byte-per-byte dengan `hasOpenReviewThreads`.

### Empat nama, satu pertanyaan

```php
allReviewersCompletedEvaluations()  // count(pending) === 0
hasPendingEvaluations()             // exists(pending)
canProceedToDiscussions()           // → allReviewersCompletedEvaluations()
discussionsAllowed()                // → !hasPendingEvaluations()
```

Keempatnya menjawab "masih ada evaluasi tertunda?". Yang bertahan `hasPendingEvaluations()`
— ia yang paling murah (`exists()` berhenti di baris pertama, `count()` memindai semua).

### Tetap di Model (spec §2.5)

`isAccessibleBy` (BR-SM-07), `resolveIsArchived` + `getIsArchivedAttribute` (BR-SM-13),
`canProceed` (BR-MON-02), `needsRevision`, `getFormPhase`, `getFormPhaseDetail`

Empat terakhir murni delegasi ke relasi tanpa kueri — memindahkannya hanya menambah
lompatan tanpa memindahkan tanggung jawab.

---

## Peta Berkas

| Berkas | Tanggung jawab |
| ------ | -------------- |
| `app/Queries/Review/PendingEvaluationCheck.php` | Apakah masih ada evaluasi tertunda |
| `app/Queries/Review/EvaluationProgress.php` | Statistik penyelesaian evaluasi |
| `app/Queries/Review/EvaluationFormsOverview.php` | Ringkasan form evaluasi + syaratnya |
| `app/Queries/Submission/EvaluationDueDate.php` | Tenggat evaluasi dari periodenya |
| `app/Data/View/EvaluationProgressData.php` | Bentuk statistik untuk layar |
| `app/Queries/Review/EvaluationRequirements.php` | Syarat evaluasi untuk layar diskusi |

Lima berkas lain sempat ada di tabel ini dan sudah dihapus — lihat *Status pelaksanaan*.

---

### Task 1: Cabut kode mati

Lima method, nol rujukan, tanpa pemanggilan dinamis di model ini. Ini langkah paling
murah dan paling aman, dan ia mengecilkan permukaan sebelum apa pun dipindahkan.

**Files:**
- Modify: `app/Models/FormSubmission.php`

**Interfaces:**
- Consumes: —
- Produces: `FormSubmission` berkurang 5 method publik

- [x] **Step 1: Buktikan ulang nol rujukan sebelum menghapus**

Run:
```bash
for m in reviewerCanParticipate allReviewersApproved hasRevisionsRequested \
         getActiveReviewThreadsCount getResolvedReviewThreadsCount; do
  printf '%-32s %s\n' "$m" \
    "$(grep -rn "$m" app/ resources/ tests/ database/ routes/ 2>/dev/null \
       | grep -v "public function $m" | wc -l)"
done

grep -nE '\$this->\{|call_user_func|->\$' app/Models/FormSubmission.php
```
Expected: kelima angkanya **0**, dan perintah kedua tidak mengeluarkan apa pun.

Kalau ada yang bukan nol, **jangan hapus method itu** — ia masuk Task 3 atau 4 sesuai
golongannya. Catat selisihnya di pesan commit.

- [x] **Step 2: Hapus kelimanya**

Hapus seluruh badan method beserta komentar di atasnya:
`reviewerCanParticipate`, `allReviewersApproved`, `hasRevisionsRequested`,
`getActiveReviewThreadsCount`, `getResolvedReviewThreadsCount`.

- [x] **Step 3: Jalankan suite penuh**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 composer run quality
```
Expected: **867 test lulus**, jumlahnya tidak berubah. Kalau ada yang gagal, method itu
ternyata dipakai — kembalikan, dan cari tahu kenapa grep melewatkannya.

- [x] **Step 4: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app/Models/FormSubmission.php
git commit -m "refactor(submission): cabut lima method FormSubmission tanpa pemanggil"
```

---

### Task 2: Runtuhkan empat predikat jadi satu

**Files:**
- Modify: `app/Models/FormSubmission.php`
- Modify: pemanggil `canProceedToDiscussions` dan `discussionsAllowed` (satu masing-masing)
- Test: `tests/Feature/Review/EvaluationPendingTest.php`

**Interfaces:**
- Consumes: —
- Produces: `FormSubmission::hasPendingEvaluations(): bool` satu-satunya yang bertahan

- [x] **Step 1: Temukan pemanggilnya**

Run:
```bash
grep -rn "canProceedToDiscussions\|discussionsAllowed\|allReviewersCompletedEvaluations" \
  app/ resources/ tests/ | grep -v "public function"
```
Catat hasilnya — keempat nama itu akan menyusut jadi satu, dan setiap pemanggil harus
ikut berubah.

- [x] **Step 2: Tulis test yang mengunci perilakunya**

```php
<?php

// tests/Feature/Review/EvaluationPendingTest.php

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;

function submissionWithReviewer(string $evaluationStatus): FormSubmission
{
    $submission = FormSubmission::factory()->create();

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => $evaluationStatus,
        'status' => 'active',
        'assigned_at' => now(),
    ]);

    return $submission->fresh();
}

it('menyatakan ada evaluasi tertunda saat satu reviewer belum selesai', function () {
    expect(submissionWithReviewer('pending')->hasPendingEvaluations())->toBeTrue();
});

it('menyatakan tidak ada evaluasi tertunda saat seluruhnya selesai', function () {
    expect(submissionWithReviewer('completed')->hasPendingEvaluations())->toBeFalse();
});

it('menganggap pengajuan tanpa reviewer tidak punya evaluasi tertunda', function () {
    expect(FormSubmission::factory()->create()->hasPendingEvaluations())->toBeFalse();
});
```

- [x] **Step 3: Jalankan test, pastikan lulus dengan kode yang sekarang**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 \
  ./vendor/bin/pest tests/Feature/Review/EvaluationPendingTest.php
```
Expected: PASS, 3 test. Ini **characterization test** — ia mengunci perilaku sebelum
diubah, bukan mendorong perilaku baru.

- [x] **Step 4: Hapus tiga, sisakan satu**

Hapus `allReviewersCompletedEvaluations()`, `canProceedToDiscussions()`, dan
`discussionsAllowed()`. Ganti setiap pemanggilnya:

```php
// canProceedToDiscussions()  →
!$submission->hasPendingEvaluations()

// discussionsAllowed()       →
!$submission->hasPendingEvaluations()

// allReviewersCompletedEvaluations() →
!$submission->hasPendingEvaluations()
```

- [x] **Step 5: Jalankan suite penuh**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 composer run quality
```
Expected: **870 test lulus** (867 + 3 baru).

- [x] **Step 6: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app tests
git commit -m "refactor(review): runtuhkan empat predikat evaluasi jadi satu"
```

---

### Task 3: Pindahkan tiga Action

Tiga method yang mengubah state. `assignReviewer` dan `removeReviewer` bekerja
berpasangan, tapi ia **dua use case** — AGENTS.md aturan 8, satu Action satu use case.

**Files:**
- Create: `app/Actions/Review/AssignReviewerSummary.php`
- Create: `app/Actions/Review/RemoveReviewerSummary.php`
- Create: `app/Actions/Review/AutoAssignEvaluationForms.php`
- Test: `tests/Feature/Review/ReviewerSummaryActionTest.php`
- Modify: `app/Models/FormSubmission.php`

**Interfaces:**
- Consumes: `FormSubmission`, `EvaluationDueDate` (Task 5)
- Produces: `AssignReviewerSummary::run(FormSubmission $submission, int $reviewerId): ReviewSummary`
- Produces: `RemoveReviewerSummary::run(FormSubmission $submission, int $reviewerId): bool`
- Produces: `AutoAssignEvaluationForms::run(FormSubmission $submission): int` — jumlah form yang ditugaskan

`AutoAssignEvaluationForms` memanggil `EvaluationDueDate` dari Task 5. Kerjakan Task 5
lebih dulu kalau tugas ini dieksekusi sendirian.

- [x] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Feature/Review/ReviewerSummaryActionTest.php

use App\Actions\Review\AssignReviewerSummary;
use App\Actions\Review\RemoveReviewerSummary;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewSummary;

it('membuat ReviewSummary terbuka untuk reviewer', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::factory()->create();

    $summary = AssignReviewerSummary::run($submission, $reviewer->id);

    expect($summary->status)->toBe('open')
        ->and($summary->reviewer_id)->toBe($reviewer->id);
});

it('tidak membuat ReviewSummary kedua untuk reviewer yang sama', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::factory()->create();

    $first = AssignReviewerSummary::run($submission, $reviewer->id);
    $second = AssignReviewerSummary::run($submission->fresh(), $reviewer->id);

    expect($second->id)->toBe($first->id)
        ->and(ReviewSummary::where('form_submission_id', $submission->id)->count())->toBe(1);
});

it('mencabut ReviewSummary milik reviewer', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::factory()->create();
    AssignReviewerSummary::run($submission, $reviewer->id);

    expect(RemoveReviewerSummary::run($submission->fresh(), $reviewer->id))->toBeTrue()
        ->and(ReviewSummary::where('form_submission_id', $submission->id)->exists())->toBeFalse();
});

it('mengembalikan false saat tidak ada yang dicabut', function () {
    $submission = FormSubmission::factory()->create();

    expect(RemoveReviewerSummary::run($submission, 999999))->toBeFalse();
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 \
  ./vendor/bin/pest tests/Feature/Review/ReviewerSummaryActionTest.php
```
Expected: FAIL — `AssignReviewerSummary` not found

- [x] **Step 3: Tulis `AssignReviewerSummary`**

```php
<?php

namespace App\Actions\Review;

use App\Models\FormSubmission;
use App\Models\ReviewSummary;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Membuka ReviewSummary untuk seorang reviewer pada sebuah pengajuan.
 *
 * Idempoten: reviewer yang sudah punya ulir tidak mendapat ulir kedua.
 */
class AssignReviewerSummary
{
    use AsAction;

    public function handle(FormSubmission $submission, int $reviewerId): ReviewSummary
    {
        /** @var ReviewSummary $summary */
        $summary = $submission->reviewSummaries()->firstOrCreate(
            ['reviewer_id' => $reviewerId],
            ['status' => 'open'],
        );

        return $summary;
    }
}
```

- [x] **Step 4: Tulis `RemoveReviewerSummary`**

```php
<?php

namespace App\Actions\Review;

use App\Models\FormSubmission;
use Lorisleiva\Actions\Concerns\AsAction;

/** Mencabut ReviewSummary seorang reviewer. False kalau tidak ada yang tercabut. */
class RemoveReviewerSummary
{
    use AsAction;

    public function handle(FormSubmission $submission, int $reviewerId): bool
    {
        return $submission->reviewSummaries()
            ->where('reviewer_id', $reviewerId)
            ->delete() > 0;
    }
}
```

- [x] **Step 5: Tulis `AutoAssignEvaluationForms`**

```php
<?php

namespace App\Actions\Review;

use App\Models\FormSubmission;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionReviewer;
use App\Queries\Submission\EvaluationDueDate;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menugaskan seluruh form evaluasi wajib ke reviewer yang belum punya penugasan.
 *
 * Versi lama memuat kueri form wajib di dalam perulangan reviewer; ia kini
 * diambil sekali karena isinya sama untuk setiap reviewer.
 */
class AutoAssignEvaluationForms
{
    use AsAction;

    /** @return int jumlah penugasan yang dibuat */
    public function handle(FormSubmission $submission): int
    {
        $detail = $submission->formPhaseDetail;

        if ($detail === null || !$detail->hasReviewEvaluationForms()) {
            return 0;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, ReviewEvaluationForm> $requiredForms */
        $requiredForms = $detail->requiredReviewEvaluationForms()->get();

        if ($requiredForms->isEmpty()) {
            return 0;
        }

        $dueDate = EvaluationDueDate::run($submission);
        $created = 0;

        /** @var SubmissionReviewer $reviewer */
        foreach ($submission->submissionReviewers()->whereDoesntHave('reviewerFormAssignments')->get() as $reviewer) {
            foreach ($requiredForms as $form) {
                $reviewer->assignForm($form->id, true, $dueDate);
                $created++;
            }
        }

        return $created;
    }
}
```

- [x] **Step 6: Hapus ketiganya dari model dan alihkan pemanggilnya**

```bash
grep -rn "assignReviewer\|removeReviewer\|autoAssignEvaluationForms" app/ resources/ tests/ \
  | grep -v "public function"
```

Ganti tiap pemanggil:
```php
$submission->assignReviewer($id)              → AssignReviewerSummary::run($submission, $id)
$submission->removeReviewer($id)              → RemoveReviewerSummary::run($submission, $id)
$submission->autoAssignEvaluationForms()      → AutoAssignEvaluationForms::run($submission)
```

Lalu hapus ketiga method dari `FormSubmission`, beserta `getEvaluationDueDate()` yang
kini hanya dipakai `AutoAssignEvaluationForms` (ia pindah di Task 5).

- [x] **Step 7: Jalankan suite penuh**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 composer run quality
```
Expected: **874 test lulus** (870 + 4 baru).

- [x] **Step 8: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app tests
git commit -m "refactor(review): pindahkan penugasan reviewer ke Actions"
```

---

### Task 4: Pindahkan Query evaluasi

Lima method yang menjawab pertanyaan tentang keadaan evaluasi. `getEvaluationStats()`
menjalankan **lima kueri COUNT terpisah** di mana satu kueri berkelompok cukup —
diperbaiki sekalian, karena ia dipanggil di layar daftar.

**Files:**
- Create: `app/Queries/Review/PendingEvaluationCheck.php`
- Create: `app/Queries/Review/EvaluationProgress.php`
- Create: `app/Queries/Review/ReviewersByEvaluationStatus.php`
- Create: `app/Data/View/EvaluationProgressData.php`
- Test: `tests/Feature/Review/EvaluationProgressTest.php`
- Modify: `app/Models/FormSubmission.php`

**Interfaces:**
- Consumes: `FormSubmission`
- Produces: `PendingEvaluationCheck::run(FormSubmission $submission): bool`
- Produces: `EvaluationProgress::run(FormSubmission $submission): EvaluationProgressData`
- Produces: `ReviewersByEvaluationStatus::run(FormSubmission $submission, string $status): Collection<int, SubmissionReviewer>`
- Produces: `EvaluationProgressData` readonly: `totalReviewers`, `completed`, `pending`, `notRequired`, `completionPercentage`, `allCompleted`

- [x] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Feature/Review/EvaluationProgressTest.php

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Queries\Review\EvaluationProgress;

function submissionWithStatuses(string ...$statuses): FormSubmission
{
    $submission = FormSubmission::factory()->create();

    foreach ($statuses as $status) {
        SubmissionReviewer::create([
            'form_submission_id' => $submission->id,
            'reviewer_id' => Reviewer::factory()->create()->id,
            'evaluation_status' => $status,
            'status' => 'active',
            'assigned_at' => now(),
        ]);
    }

    return $submission->fresh();
}

it('menghitung sebaran status evaluasi', function () {
    $submission = submissionWithStatuses('completed', 'completed', 'pending', 'not_required');

    $progress = EvaluationProgress::run($submission);

    expect($progress->totalReviewers)->toBe(4)
        ->and($progress->completed)->toBe(2)
        ->and($progress->pending)->toBe(1)
        ->and($progress->notRequired)->toBe(1);
});

it('menghitung persentase dari selesai ditambah tidak diperlukan', function () {
    $progress = EvaluationProgress::run(submissionWithStatuses('completed', 'not_required', 'pending', 'pending'));

    expect($progress->completionPercentage)->toBe(50)
        ->and($progress->allCompleted)->toBeFalse();
});

it('menganggap pengajuan tanpa reviewer sudah seratus persen', function () {
    $progress = EvaluationProgress::run(FormSubmission::factory()->create());

    expect($progress->completionPercentage)->toBe(100)
        ->and($progress->allCompleted)->toBeTrue();
});

it('memakai satu kueri berkelompok, bukan lima COUNT terpisah', function () {
    $submission = submissionWithStatuses('completed', 'pending');

    DB::enableQueryLog();
    EvaluationProgress::run($submission);

    expect(DB::getQueryLog())->toHaveCount(1);
    DB::disableQueryLog();
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 \
  ./vendor/bin/pest tests/Feature/Review/EvaluationProgressTest.php
```
Expected: FAIL — `EvaluationProgress` not found

- [x] **Step 3: Tulis `EvaluationProgressData`**

```php
<?php

namespace App\Data\View;

/** Bentuk statistik evaluasi untuk layar daftar dan detail pengajuan. */
final class EvaluationProgressData
{
    public function __construct(
        public readonly int $totalReviewers,
        public readonly int $completed,
        public readonly int $pending,
        public readonly int $notRequired,
        public readonly int $completionPercentage,
        public readonly bool $allCompleted,
    ) {}

    /** @return array<string, int|bool> */
    public function toArray(): array
    {
        return [
            'total_reviewers' => $this->totalReviewers,
            'completed_evaluations' => $this->completed,
            'pending_evaluations' => $this->pending,
            'no_evaluation_required' => $this->notRequired,
            'completion_percentage' => $this->completionPercentage,
            'all_completed' => $this->allCompleted,
        ];
    }
}
```

Kunci array-nya sengaja sama persis dengan keluaran `getEvaluationStats()` yang lama —
layar yang membacanya tidak ikut berubah di tugas ini.

- [x] **Step 4: Tulis `EvaluationProgress`**

```php
<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\EvaluationProgressData;
use App\Models\FormSubmission;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Sebaran status evaluasi sebuah pengajuan.
 *
 * Satu kueri berkelompok, bukan lima COUNT terpisah seperti versi lama:
 * ia dipanggil per baris di layar daftar.
 */
class EvaluationProgress implements ChangesNoState
{
    use AsAction;

    public function handle(FormSubmission $submission): EvaluationProgressData
    {
        /** @var array<string, int> $counts */
        $counts = $submission->submissionReviewers()
            ->select('evaluation_status', DB::raw('COUNT(*) AS total'))
            ->groupBy('evaluation_status')
            ->pluck('total', 'evaluation_status')
            ->all();

        $completed = $counts['completed'] ?? 0;
        $pending = $counts['pending'] ?? 0;
        $notRequired = $counts['not_required'] ?? 0;
        $total = array_sum($counts);

        return new EvaluationProgressData(
            totalReviewers: $total,
            completed: $completed,
            pending: $pending,
            notRequired: $notRequired,
            completionPercentage: $total > 0 ? (int) round((($completed + $notRequired) / $total) * 100) : 100,
            allCompleted: $pending === 0,
        );
    }
}
```

- [x] **Step 5: Tulis dua Query sisanya**

```php
<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use Lorisleiva\Actions\Concerns\AsAction;

/** Apakah pengajuan ini masih menunggu evaluasi reviewer. */
class PendingEvaluationCheck implements ChangesNoState
{
    use AsAction;

    public function handle(FormSubmission $submission): bool
    {
        return $submission->submissionReviewers()
            ->where('evaluation_status', 'pending')
            ->exists();
    }
}
```

```php
<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\SubmissionReviewer;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/** Reviewer sebuah pengajuan yang evaluasinya berstatus tertentu. */
class ReviewersByEvaluationStatus implements ChangesNoState
{
    use AsAction;

    /** @return Collection<int, SubmissionReviewer> */
    public function handle(FormSubmission $submission, string $status): Collection
    {
        /** @var Collection<int, SubmissionReviewer> $reviewers */
        $reviewers = $submission->submissionReviewers()
            ->where('evaluation_status', $status)
            ->with(['reviewer.user'])
            ->get();

        return $reviewers;
    }
}
```

- [x] **Step 6: Hapus lima method dari model dan alihkan pemanggilnya**

```bash
grep -rn "hasPendingEvaluations\|getEvaluationStats\|reviewersWithCompletedEvaluations\|reviewersWithPendingEvaluations" \
  app/ resources/ tests/ | grep -v "public function"
```

Ganti:
```php
$submission->hasPendingEvaluations()              → PendingEvaluationCheck::run($submission)
$submission->getEvaluationStats()                 → EvaluationProgress::run($submission)->toArray()
$submission->reviewersWithCompletedEvaluations()  → ReviewersByEvaluationStatus::run($submission, 'completed')
$submission->reviewersWithPendingEvaluations()    → ReviewersByEvaluationStatus::run($submission, 'pending')
```

`reviewersWith*` yang lama mengembalikan **builder**, yang baru mengembalikan
**Collection**. Pemanggil yang merantainya dengan `->get()` atau `->count()` harus
disesuaikan — periksa satu per satu, jangan diganti membabi buta.

- [x] **Step 7: Jalankan suite penuh**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 composer run quality
```
Expected: **878 test lulus** (874 + 4 baru).

- [x] **Step 8: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app tests
git commit -m "refactor(review): pindahkan kueri progres evaluasi ke Queries"
```

---

### Task 5: Pindahkan Query sisanya

Tujuh method: tenggat evaluasi, ulir review, dan ringkasan form evaluasi.

**Files:**
- Create: `app/Queries/Submission/EvaluationDueDate.php`
- Create: `app/Queries/Review/ReviewThreadCount.php`
- Create: `app/Queries/Review/EvaluationFormsOverview.php`
- Test: `tests/Feature/Review/EvaluationFormsOverviewTest.php`
- Modify: `app/Models/FormSubmission.php`

**Interfaces:**
- Consumes: `FormSubmission`
- Produces: `EvaluationDueDate::run(FormSubmission $submission): ?DateTimeInterface`
- Produces: `ReviewThreadCount::run(FormSubmission $submission, string $status): int`
- Produces: `EvaluationFormsOverview::run(FormSubmission $submission): array<int, array{id: int, title: string, assigned: int}>`
- Produces: `EvaluationFormsOverview::isRequired(FormSubmission $submission): bool`

- [x] **Step 1: Baca dulu `getEvaluationFormsSummary()` selengkapnya**

Rencana ini memuat badannya hanya sebagian — ia terpotong saat dibaca.

Run:
```bash
sed -n '/function getEvaluationFormsSummary/,/^    }$/p' app/Models/FormSubmission.php
```

Tulis `EvaluationFormsOverview::run()` **mengikuti bentuk keluaran yang terbaca di
sana**, bukan mengikuti contoh di Step 4 kalau keduanya berbeda. Layar yang membacanya
tidak ikut berubah di tugas ini.

- [x] **Step 2: Tulis test yang gagal**

```php
<?php

// tests/Feature/Review/EvaluationFormsOverviewTest.php

use App\Models\FormSubmission;
use App\Models\ReviewSummary;
use App\Queries\Review\ReviewThreadCount;
use App\Queries\Submission\EvaluationDueDate;

it('menghitung ulir review per status', function () {
    $submission = FormSubmission::factory()->create();

    foreach (['open', 'open', 'resolved'] as $status) {
        ReviewSummary::create([
            'form_submission_id' => $submission->id,
            'reviewer_id' => null,
            'status' => $status,
        ]);
    }

    expect(ReviewThreadCount::run($submission->fresh(), 'open'))->toBe(2)
        ->and(ReviewThreadCount::run($submission->fresh(), 'resolved'))->toBe(1);
});

it('mengembalikan null saat pengajuan tidak punya periode', function () {
    $submission = FormSubmission::factory()->create(['submission_period_id' => null]);

    expect(EvaluationDueDate::run($submission))->toBeNull();
});
```

Kalau `ReviewSummary` menuntut `reviewer_id` bukan null, sesuaikan fixture-nya —
periksa kolomnya dengan `grep -n fillable -A 8 app/Models/ReviewSummary.php`.

- [x] **Step 3: Jalankan test, pastikan gagal**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 \
  ./vendor/bin/pest tests/Feature/Review/EvaluationFormsOverviewTest.php
```
Expected: FAIL — kelasnya not found

- [x] **Step 4: Tulis ketiga Query**

```php
<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\SubmissionDate;
use DateTimeInterface;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Tenggat evaluasi sebuah pengajuan: tanggal terakhir di periodenya.
 *
 * Period diturunkan lewat FK submission_period_id, bukan ditebak lewat
 * submission_period_phases — pivot itu tanpa unique constraint (ADR 0004).
 */
class EvaluationDueDate implements ChangesNoState
{
    use AsAction;

    public function handle(FormSubmission $submission): ?DateTimeInterface
    {
        $period = $submission->submissionPeriod;

        if ($period === null) {
            return null;
        }

        /** @var SubmissionDate|null $latest */
        $latest = $period->submissionDates()->orderByDesc('datetime')->first();

        return $latest?->datetime;
    }
}
```

```php
<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use Lorisleiva\Actions\Concerns\AsAction;

/** Jumlah ulir review sebuah pengajuan pada satu status. */
class ReviewThreadCount implements ChangesNoState
{
    use AsAction;

    public function handle(FormSubmission $submission, string $status): int
    {
        return $submission->reviewSummaries()->where('status', $status)->count();
    }
}
```

```php
<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\ReviewEvaluationForm;
use Lorisleiva\Actions\Concerns\AsAction;

/** Form evaluasi yang berlaku bagi sebuah pengajuan, beserta jumlah penugasannya. */
class EvaluationFormsOverview implements ChangesNoState
{
    use AsAction;

    /** @return array<int, array{id: int, title: string, assigned: int}> */
    public function handle(FormSubmission $submission): array
    {
        $detail = $submission->formPhaseDetail;

        if ($detail === null) {
            return [];
        }

        // Jumlah penugasan diambil sekali dan dikelompokkan; versi lama
        // menjalankan satu COUNT per form di dalam perulangan.
        $assigned = $submission->reviewerFormAssignments()
            ->select('review_evaluation_form_id')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('review_evaluation_form_id')
            ->pluck('total', 'review_evaluation_form_id');

        /** @var \Illuminate\Database\Eloquent\Collection<int, ReviewEvaluationForm> $forms */
        $forms = $detail->activeReviewEvaluationForms()->get();

        return $forms->map(static fn (ReviewEvaluationForm $form): array => [
            'id' => $form->id,
            'title' => $form->title,
            'assigned' => (int) ($assigned[$form->id] ?? 0),
        ])->all();
    }

    public static function isRequired(FormSubmission $submission): bool
    {
        $detail = $submission->formPhaseDetail;

        return $detail !== null && $detail->requiresEvaluationCompletion();
    }
}
```

- [x] **Step 5: Hapus tujuh method dari model dan alihkan pemanggilnya**

Yang dihapus: `getEvaluationDueDate`, `hasOpenReviewThreads`, `getEvaluationFormsSummary`,
`hasReviewEvaluationForms`, `requiresReviewEvaluation`, `reviewerCanCreateThreads`,
`getEvaluationRequirements`.

```bash
for m in getEvaluationDueDate hasOpenReviewThreads getEvaluationFormsSummary \
         hasReviewEvaluationForms requiresReviewEvaluation reviewerCanCreateThreads \
         getEvaluationRequirements; do
  printf '%-30s %s\n' "$m" "$(grep -rn "$m" app/ resources/ tests/ | grep -v "public function $m" | wc -l)"
done
```

Yang nol rujukan **dihapus tanpa pengganti**. Yang punya pemanggil dialihkan:
```php
$submission->hasOpenReviewThreads()        → ReviewThreadCount::run($submission, 'open') > 0
$submission->getEvaluationFormsSummary()   → EvaluationFormsOverview::run($submission)
$submission->requiresReviewEvaluation()    → EvaluationFormsOverview::isRequired($submission)
$submission->hasReviewEvaluationForms()    → $submission->formPhaseDetail?->hasReviewEvaluationForms() ?? false
```

`reviewerCanCreateThreads` dan `getEvaluationRequirements` memuat percabangan sendiri —
baca badannya, lalu pindahkan ke `Queries/Review/` mengikuti pola yang sama. Kalau
pemanggilnya nol, hapus saja.

- [x] **Step 6: Jalankan suite penuh**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 composer run quality
```
Expected: **880 test lulus** (878 + 2 baru).

- [x] **Step 7: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app tests
git commit -m "refactor(review): pindahkan kueri ulir dan form evaluasi ke Queries"
```

---

### Task 6: `FormSubmission` tidak bisa keluar dari antrean

**Tidak dijalankan.** Cek ambang Step 1 dijalankan setelah Task 1–5 dan hasilnya:

```
/app/app/Models/FormSubmission.php: masih: ukuran berkas
```

Commit `9c3fb95` membereskan alasan yang satunya (`prosa komentar terlalu panjang`, plafon
`max.comment.overflow` turun 165 → 164). Alasan `ukuran berkas` tidak bisa dibereskan.

#### Kenapa 200 baris mustahil di berkas ini

`hard.php.file.loc` = 200; berkasnya 373 baris. Komposisinya:

| | baris |
| --- | ---: |
| **kode** | **215** |
| komentar (termasuk 25 baris `@property`) | 93 |
| baris kosong | 49 |
| import | 16 |

**215 itu lantainya.** Buang seluruh komentar, baris kosong, dan import — masih 215 > 200.
Yang tersisa di berkas itu: 21 relasi, `isAccessibleBy()` dengan dua helper privatnya
(BR-SM-07), `resolveIsArchived` + `getIsArchivedAttribute` (BR-SM-13), `canProceed`
(BR-MON-02), `needsRevision`, `getFormPhase`, `getFormPhaseDetail` (5 pemanggil produksi),
`scopeActive`, `submissionType`, `$fillable`, `$casts`. Semuanya yang spec §2.5 sebut
**tetap di Model**.

Target "keluar dari `quality-legacy.txt`" karena itu bertabrakan dengan spec yang
melahirkannya. Yang salah goal-nya, bukan eksekusinya.

#### Ambangnya sendiri tidak salah

| | jumlah |
| --- | ---: |
| model seluruhnya | 54 |
| model ≤200 baris | **47** |
| model >200 baris | 7 |

`FormSubmission` (373) yang terjauh, disusul `SubmissionReviewer` (304), `ReviewSummary`
(281), `ReviewFormResponse` (241), `Organization` (221), `FormPhaseDetail` (207),
`ReviewFormFieldResponse` (205). Ambang 200 realistis untuk model pada umumnya — ia
hanya tidak muat untuk satu aggregate root dengan 21 relasi.

#### Tiga jalan yang dipertimbangkan

| | Jalan | Konsekuensi |
| - | ----- | ----------- |
| **A** | Terima `FormSubmission` tetap di `quality-legacy.txt`, tulis alasannya sebagai komentar di atas barisnya | `max.legacy.files` tetap 276. Ekspektasi "7 berkas keluar daftar" di [`refactor-plan.md`](refactor-plan.md) §5 perlu dikoreksi. Antreannya jadi campuran "utang" dan "memang tak muat" — itu yang perlu dibereskan, dan tempatnya §4, bukan di sini. |
| **B** | Tambah ambang per-kategori `hard.model.file.loc`, mengikuti preseden `hard.vue.file.loc: 250` | Satu baris budget. Tapi 47 dari 54 model lulus di 200, jadi melonggarkan ambang demi satu berkas berarti melonggarkannya untuk 53 berkas yang tidak membutuhkannya. |
| **C** | Pecah relasi ke trait per konteks | Angkanya turun, kerumitannya tidak. Menyiasati metrik — senapas dengan larangan AGENTS.md aturan 5. |

#### Keputusan: A

Diambil 20 September 2026. Yang perlu belajar membedakan "utang" dari "lantai" itu
metriknya, bukan kodenya yang perlu dipelintir supaya muat. Yang dikerjakan:

- [x] **Alasannya dicatat di `quality-legacy.txt`**, sebagai komentar lima baris tepat di
      atas `app/Models/FormSubmission.php`. `LedgerFile` melewati baris `#`, jadi
      hitungan entri tidak berubah dan assertion 3 dan 4 tetap hijau.
- [x] **`refactor-plan.md` dinaikkan ke v1.6** — ukuran keberhasilan gelombang 2 jadi
      6 berkas keluar daftar (bukan 7), sasaran akhir `max.legacy.files` jadi 1 (bukan 0),
      dengan catatan kaki di bawah tabel §5 yang menerangkan sebabnya.
- [ ] **Memisahkan "utang" dari "tidak muat" di antrean legacy** — belum dikerjakan,
      tempatnya §4, bukan gelombang model. Selama belum, `quality-legacy.txt` memuat dua
      jenis baris yang artinya berbeda dan hanya komentarnya yang membedakan.

`max.legacy.files` tetap 276 dan `quality-legacy.txt` tetap 276 entri.

---

---

# Bagian 2 — temuan review

**Status:** **selesai** — dieksekusi 20 September 2026 di branch `feat/init-be-development`,
commit `29debb0`..`83a4610`. Task 7–14 seluruhnya dikerjakan. Ditulis 20 September 2026
setelah review kode atas `dd43442`..`98af85b`; hasil pelaksanaannya di bawah.

**Goal:** Membereskan sisa yang ditinggalkan bagian 1 — satu bug yang melanggar BR-REV-05,
satu gerbang yang bolong, satu method mati, dan tiga berkas yang belum selesai dirapikan.

**Architecture:** Pagar dulu, baru isinya. Task 7 menutup lubang di `AuditCoverageTest`
sebelum Task 9–14 menambah kode ke `app/Queries/`, supaya kode baru itu lahir sudah
terjaga, bukan menyusul dijaga.

**Seluruh Global Constraints di bagian 1 tetap berlaku** — termasuk larangan menyiasati
PHPStan, pesan commit satu baris, dan `composer run quality` hijau sebelum tugas dinyatakan
selesai. Suite acuan saat bagian ini ditulis: **876 test**.

## Status pelaksanaan bagian 2

Suite **876 → 884 test** (1912 → 1930 assertion). `composer run quality` dan
`npm run quality` hijau. Tiap task satu commit.

### Yang berubah dari rencana

| Task | Rencana | Kenyataan | Sebab |
| ---- | ------- | --------- | ----- |
| 7 | probe `ProbeSementara`, variabel `$berkas` | `TemporaryProbe`, `$candidate`, `$violations`, `$ambiguous`, `$direct` | Global Constraint "identifier bahasa Inggris"; sekalian tiga variabel lama yang melanggarnya di berkas yang sama |
| 10 | `$berlaku` dipakai dua kali, "clone keduanya" | helper privat `applicableAssignments()` yang membangun ulang builder | membangun ulang lebih jelas daripada `clone`, dan menaruh alasan nama tabel eksplisit di satu tempat |
| 11 | kunci jumlah kueri dapat **3** | apa adanya dapat **5** | `$submission->fresh()` dan lazy-load `formPhaseDetail` ikut masuk jendela log. Keduanya dihangatkan lebih dulu supaya yang terukur memang pemindaian `review_evaluation_forms` — barulah angkanya 3, lalu 1 sesudah diperbaiki |
| 12 | kebocoran log meracuni test sesudahnya | **tidak tereproduksi** | tiap test Pest membangun ulang aplikasi, jadi koneksi beserta flag log-nya ikut baru. `try`/`finally` tetap dipasang sebagai kebersihan, bukan sebagai perbaikan bug |
| 13 | "perubahan yang terlihat pengguna" | **tidak terlihat sama sekali** | lihat di bawah |
| 14 | pilih A/B/C untuk fallback `+7 days` | **A — biarkan `null` mengalir** | kolomnya `nullable`, tiap helper `ReviewerFormAssignment` menjaga null, tiap layar menjaganya, dan `SubmissionEvaluationManager` bahkan sudah punya label "No deadline" |

### Task 13 berdiri di atas premis yang salah

Rencana menyebut dua layar memancarkan `evaluationRequirements` dengan dua bentuk, lalu
menyimpulkan pengguna melihat pesan berbeda tergantung layar. Yang sebenarnya: **prop itu
tidak pernah dibaca.** `ReviewSystem.vue` mendeklarasikannya di `Props` (baris 132) lalu
mengabaikannya — tidak di script, tidak di template. Pesan yang benar-benar tampil adalah
`threadCreationMessage`, dihitung di dalam komponen dari `reviewerAssignments` milik
pemakai. Jadi `message` versi Inggris (`EvaluationRequirements`) **dan** versi Indonesia
(`getEvaluationMessage`) sama-sama mati, begitu pula `total_forms`/`required_forms`.

Keputusannya: cabut pipa matinya — dua controller, dua halaman, dan `Props`
`ReviewSystem.vue`. **Nol perubahan yang dilihat pengguna**, 76 baris hilang.

Sebelum mencabut, diperiksa bahwa tak ada skenario bisnis yang ikut berkurang:

| Yang diperiksa | Hasil |
| -------------- | ----- |
| BR-FE-06 (gerbang ReviewSummary) | ditegakkan `ReviewSummaryGuard`, diuji `[REV-EVAL-04/05/06]` — server-side, tidak lewat prop |
| `discussion.feature`, `evaluation.feature` | menegaskan *hasil*, tidak pernah teks pesan atau jumlah form |
| `pending.txt`, `uncovered.txt` | keduanya `max: 0` — tak ada skenario tertunda yang prop itu layani |
| ID skenario di test terdampak | nol — `BddCoverageTest` tidak kehilangan apa pun |

### Utang yang ditinggalkan bagian ini

**`Queries/Review/EvaluationRequirements` kini nol pemanggil produksi.** Task 13 mencabut
satu-satunya pemanggilnya. Ia **tidak** ikut dihapus: kelasnya benar, punya empat test, dan
gelombang 3 yang mendatangi endpoint `getEvaluationStatus` beserta kontrak DTO-nya adalah
tempat yang tepat untuk memutuskan nasibnya. Aturan "nol pemanggil → hapus" dari bagian 1
berlaku untuk kelas yang lahir dari memindahkan kode mati; yang ini lahir dari kode hidup
yang baru saja kehilangan layarnya. **Keputusannya diserahkan ke gelombang 3.**

**Test `[SM-WIZARD-06]` rapuh — dan sebabnya bukan dari gelombang ini.** Ia gagal sekali
dari enam kali suite penuh dijalankan, lalu tidak pernah lagi. Sebabnya batas detik:
`FormField::isRequiredFor()` membandingkan `$this->created_at > $submission->created_at`,
sedangkan `$submission` di test itu model in-memory (presisi sub-detik) dan `$mitra` dibaca
ulang dari database (`timestamp(0)`, dibulatkan ke detik). Saat keduanya jatuh di detik
yang berbeda, field-nya dianggap lahir setelah submission, tidak jadi wajib, dan
`assertComplete()` tidak melempar. Tidak ada kaitannya dengan reviewer atau evaluasi —
**tugas tersendiri, bukan sisipan di sini.**

## Dari mana temuan ini

Review dijalankan atas rentang `dd43442`..`98af85b`. Yang masuk ke sini **hanya temuan
yang sudah diperiksa ulang langsung di kode**, beserta buktinya. Tiga temuan review
sengaja tidak jadi task dan alasannya ada di bagian *Catatan, bukan task* di bawah.

Satu temuan review **ditolak**: ia menyebut tiga layar memancarkan prop
`evaluationRequirements` dengan tiga bentuk berbeda. Yang benar **dua** —
`userShowSubmission` (baris 415) dan `adminShowSubmission` (baris 846).
`reviewerShowSubmission` (baris 1099) tidak memancarkan prop itu sama sekali. Task 13
memakai angka yang benar.

## Peta Berkas (bagian 2)

| Berkas | Disentuh oleh |
| ------ | ------------- |
| `tests/Unit/Audit/AuditCoverageTest.php` | Task 7 |
| `AGENTS.md` | Task 7 |
| `app/Queries/Review/EvaluationFormsOverview.php` | Task 8, Task 10 |
| `app/Queries/Review/PendingEvaluationCheck.php` | Task 9 |
| `app/Queries/Review/EvaluationProgress.php` | Task 9 |
| `app/Queries/Review/EvaluationRequirements.php` | Task 11 |
| `tests/Feature/Review/EvaluationProgressTest.php` | Task 9, Task 12 |
| `app/Http/Controllers/SubmissionViewController.php` | Task 13 |
| `app/Http/Controllers/ReviewController.php` | Task 14 |

---

### Task 7: Perluas `AuditCoverageTest` ke `app/Queries/`

`AuditCoverageTest` hanya memindai `app_path('Actions')` (baris 24). Seluruh
`app/Queries/` tidak pernah dikunjungi, jadi penanda `ChangesNoState` pada empat Query
yang ada sekarang **tidak ditegakkan apa pun** — ia komentar yang menyamar jadi tipe.
Siapa pun yang kelak menulis perubahan state di `app/Queries/` lolos gerbang BR-RPT-09
dengan suite hijau.

Kerjakan ini **lebih dulu**: Task 9–14 menambah kode ke direktori yang belum terjaga itu.

**Files:**
- Modify: `tests/Unit/Audit/AuditCoverageTest.php`
- Modify: `AGENTS.md`

**Interfaces:**
- Consumes: `App\Actions\Concerns\ChangesNoState`
- Produces: cakupan BR-RPT-09 meluas dari satu direktori jadi dua

- [x] **Step 1: Buktikan lubangnya ada**

Tulis Query sementara yang mengubah state tanpa audit dan tanpa penanda:

```bash
cat > app/Queries/Review/ProbeSementara.php <<'EOF'
<?php

namespace App\Queries\Review;

use App\Models\FormSubmission;
use Lorisleiva\Actions\Concerns\AsAction;

class ProbeSementara
{
    use AsAction;

    public function handle(FormSubmission $submission): void
    {
        $submission->update(['last_completed_step' => 99]);
    }
}
EOF

docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 \
  ./vendor/bin/pest tests/Unit/Audit/AuditCoverageTest.php
```
Expected: **LULUS** — dan justru itu kegagalannya. Berkas yang jelas-jelas mengubah state
tanpa audit lolos tanpa sepatah kata. Jangan hapus probe-nya dulu.

- [x] **Step 2: Generalisasi pemindaiannya**

`actionClasses()` meng-hardcode `app_path('Actions')` dan prefiks namespace
`App\Actions\`. Ganti keduanya jadi peta direktori → namespace, lalu ganti nama
fungsinya karena ia tidak lagi khusus Action:

```php
/**
 * @return array<int, array{class: class-string, path: string, source: string}>
 */
function auditedClasses(): array
{
    $classes = [];

    // BR-RPT-09 — cakupannya direktori tempat use case hidup, bukan satu nama
    // direktori. Query masuk karena ia bisa saja menulis, bukan karena ia
    // diharapkan menulis.
    foreach (['Actions' => 'App\\Actions\\', 'Queries' => 'App\\Queries\\'] as $dir => $namespace) {
        foreach (Finder::create()->files()->in(app_path($dir))->name('*.php')->sortByName() as $file) {
            $relative = str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            $class = $namespace.$relative;

            if (!class_exists($class)) {
                continue;
            }

            $classes[] = [
                'class' => $class,
                'path' => 'app/'.$dir.'/'.$file->getRelativePathname(),
                'source' => (string) file_get_contents($file->getPathname()),
            ];
        }
    }

    return $classes;
}
```

Ganti ketiga pemanggil `actionClasses()` (baris 45, 67, 84) jadi `auditedClasses()`.
Sesuaikan juga kalimat pesan gagalnya — "Action berikut" kini bisa berarti Query.

- [x] **Step 3: Pastikan probe-nya sekarang tertangkap**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 \
  ./vendor/bin/pest tests/Unit/Audit/AuditCoverageTest.php
```
Expected: **GAGAL**, menyebut `app/Queries/Review/ProbeSementara.php`.

- [x] **Step 4: Buang probe-nya, pastikan hijau lagi**

```bash
rm app/Queries/Review/ProbeSementara.php

docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 \
  ./vendor/bin/pest tests/Unit/Audit/AuditCoverageTest.php
```
Expected: LULUS. Keempat Query yang ada sudah `implements ChangesNoState`, jadi tidak ada
yang perlu diubah di `app/Queries/`.

- [x] **Step 5: Tambahkan `app/Queries/` ke tabel AGENTS.md**

Tabel *Di mana meletakkan kode baru* tidak punya baris untuk `app/Queries/`, padahal
direktorinya sudah berisi empat berkas. Tanpa baris itu, agen berikutnya menaruh kueri
di `app/Actions/`. Sisipkan tepat di bawah baris *Business use case*:

```
| Kueri baca (tanpa ubah state) | `app/Queries/`          | `implements ChangesNoState`; otomatis level 9 |
```

- [x] **Step 6: Jalankan gerbang penuh**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 composer run quality
```
Expected: hijau, 876 test (jumlahnya tidak berubah — cakupannya yang melebar, bukan
jumlah test-nya).

- [x] **Step 7: Commit**

```bash
git add tests AGENTS.md
git commit -m "test(audit): perluas cakupan BR-RPT-09 ke app/Queries"
```

---

### Task 8: Cabut `EvaluationFormsOverview::isRequired()`

Nol pemanggil. Ia ditulis sebagai pengganti `FormSubmission::requiresReviewEvaluation()`,
yang ternyata juga sudah mati — jadi penggantinya tidak pernah dibutuhkan. Badannya satu
baris yang membungkus `FormPhaseDetail::requiresEvaluationCompletion()` yang sudah ada.

Ini method mati **keenam** dari gelombang ini; lima yang pertama sudah dihapus di
`2ba1a9a`.

**Files:**
- Modify: `app/Queries/Review/EvaluationFormsOverview.php`

**Interfaces:**
- Consumes: —
- Produces: `EvaluationFormsOverview` tinggal `handle()` dan `countPerForm()`

- [x] **Step 1: Buktikan ulang nol rujukan**

Run:
```bash
grep -rn 'isRequired(' --include='*.php' app/ tests/ routes/ | grep -v 'public static function'
```
Expected: kosong. Kalau ada hasil, **berhenti** — berarti ada pemanggil yang lahir setelah
rencana ini ditulis, dan tugas ini batal.

- [x] **Step 2: Hapus method-nya**

Hapus `isRequired()` beserta docblock-nya. Import `FormSubmission` tetap dipakai
`handle()`, jadi jangan ikut dibuang.

- [x] **Step 3: Jalankan gerbang penuh**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 composer run quality
```
Expected: hijau, 876 test.

- [x] **Step 4: Commit**

```bash
git add app
git commit -m "refactor(review): cabut isRequired yang tidak dipanggil siapa pun"
```

---

### Task 9: Satu definisi "evaluasi selesai" — BR-REV-05

Ini bug, bukan kerapian.

BR-REV-05 menetapkan definisinya sekali: *"setiap `SubmissionReviewer` **aktif** sudah
selesai (`evaluation_status` = `completed` **atau** `not_required`)"*. BR-REV-06 menegaskan
lagi: *"Definisi 'selesai' sama persis dengan BR-REV-05 dan BR-FE-06 — **satu definisi,
tiga pemakai**"*.

Di repo sekarang ada **tiga** definisi yang berbeda:

| Tempat | Definisinya | Sesuai BR-REV-05? |
| ------ | ----------- | ----------------- |
| `FormSubmission::canProceed()` | `status = 'active'` DAN `evaluation_status` ∈ `SETTLED_EVALUATION` | ✅ |
| `PendingEvaluationCheck` | `evaluation_status = 'pending'`, tanpa filter status | ❌ |
| `EvaluationProgress` | `pending === 0`, tanpa filter status | ❌ |

**Akibatnya.** `ReviewerReassignment` di Ubiquitous Language
([`02_ubiquitous_language.md:117`](../requirement-analysis/ddd/02_ubiquitous_language.md))
menyatakan record reviewer lama di-mark `replaced` dan tetap tinggal sebagai sejarah.
`ReplaceReviewer` (baris 48) memanggil `markAsReplaced()`, yang hanya menulis
`status = 'replaced'` — `evaluation_status` dibiarkan apa adanya. Jadi reviewer yang
diganti saat evaluasinya masih `pending` **mengunci diskusi selamanya**: sejarahnya ikut
dihitung sebagai pekerjaan yang belum selesai.

Kedua kueri ini port setia dari method model lama yang juga salah — bugnya warisan, bukan
buatan gelombang 1. Tapi ekstraksi adalah saat yang tepat memperbaikinya, dan
`SETTLED_EVALUATION` sudah ada sebagai satu-satunya definisi yang sah.

**Files:**
- Modify: `app/Queries/Review/PendingEvaluationCheck.php`
- Modify: `app/Queries/Review/EvaluationProgress.php`
- Test: `tests/Feature/Review/EvaluationPendingTest.php`
- Test: `tests/Feature/Review/EvaluationProgressTest.php`

**Interfaces:**
- Consumes: `SubmissionReviewer::SETTLED_EVALUATION`
- Produces: perilaku kedua kueri sama dengan `canProceed()`

- [x] **Step 1: Baca dulu ketiganya berdampingan**

Run:
```bash
sed -n '/public function canProceed/,/^    }/p' app/Models/FormSubmission.php
sed -n '140,150p' app/Models/SubmissionReviewer.php
```
Pastikan `SETTLED_EVALUATION` memang `['completed', 'not_required']` dan `canProceed()`
memang memfilter `status = 'active'`. Kalau salah satunya sudah berubah, sesuaikan langkah
di bawah — jangan ikuti rencana yang sudah basi.

- [x] **Step 2: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Review/EvaluationPendingTest.php`:

```php
it('mengabaikan reviewer yang sudah diganti — BR-REV-05 menghitung yang aktif saja', function () {
    $submission = FormSubmission::factory()->create();

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'completed',
        'status' => 'active',
    ]);

    // Sejarah, bukan pekerjaan: evaluasinya tertinggal 'pending' karena
    // markAsReplaced() hanya menyentuh status.
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'replaced',
    ]);

    expect(PendingEvaluationCheck::run($submission->fresh()))->toBeFalse();
});
```

Dan ke `tests/Feature/Review/EvaluationProgressTest.php` — `submissionWithStatuses()`
selalu menulis `status = 'active'`, jadi tulis reviewer yang diganti secara eksplisit:

```php
it('tidak menghitung reviewer yang diganti sebagai pekerjaan tertunda', function () {
    $submission = submissionWithStatuses('completed', 'completed');

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'replaced',
    ]);

    $progress = EvaluationProgress::run($submission->fresh());

    expect($progress->totalReviewers)->toBe(2)
        ->and($progress->pending)->toBe(0)
        ->and($progress->completionPercentage)->toBe(100)
        ->and($progress->allCompleted)->toBeTrue();
});
```

- [x] **Step 3: Jalankan, pastikan gagal**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 \
  ./vendor/bin/pest tests/Feature/Review/EvaluationPendingTest.php tests/Feature/Review/EvaluationProgressTest.php
```
Expected: dua test baru GAGAL — `toBeFalse()` dapat `true`, dan `totalReviewers` dapat 3.
Kalau salah satunya lulus sejak awal, **berhenti dan baca ulang**: berarti asumsi rencana
ini tentang perilaku sekarang salah.

- [x] **Step 4: Perbaiki `PendingEvaluationCheck`**

```php
public function handle(FormSubmission $submission): bool
{
    // BR-REV-05 — yang dihitung hanya reviewer aktif, dan "selesai" berarti
    // completed atau not_required. Reviewer yang diganti adalah sejarah.
    return $submission->submissionReviewers()
        ->where('status', 'active')
        ->whereNotIn('evaluation_status', SubmissionReviewer::SETTLED_EVALUATION)
        ->exists();
}
```

Import `App\Models\SubmissionReviewer`. Perhatikan ini bukan sekadar menambah filter:
`= 'pending'` berubah jadi `NOT IN (completed, not_required)`, supaya nilai
`evaluation_status` baru yang kelak ditambahkan ke enum otomatis terhitung belum selesai
alih-alih diam-diam dianggap beres.

- [x] **Step 5: Perbaiki `EvaluationProgress`**

Tambahkan `->where('status', 'active')` pada kueri di `handle()`, dan turunkan
`allCompleted` dari `SETTLED_EVALUATION`, bukan dari `$pending === 0`:

```php
$counts = $submission->submissionReviewers()
    ->where('status', 'active')
    ->get(['evaluation_status'])
    ->countBy('evaluation_status')
    ->all();

$completed = $counts['completed'] ?? 0;
$pending = $counts['pending'] ?? 0;
$notRequired = $counts['not_required'] ?? 0;
$total = array_sum($counts);
$settled = $completed + $notRequired;
```

lalu `completionPercentage: $total > 0 ? (int) round($settled / $total * 100) : 100` dan
`allCompleted: $settled === $total`. Perbarui juga prosa komentar kelasnya — ia masih
menjelaskan versi lama, dan batasnya tetap 3 baris.

- [x] **Step 6: Jalankan suite penuh**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 composer run quality
```
Expected: hijau, 876 + 2 = **878 test**.

Kalau ada test lain yang ikut merah, **jangan longgarkan test-nya** — ia mungkin mengunci
perilaku lama yang salah. Baca dulu apa yang ia tegaskan, lalu putuskan.

- [x] **Step 7: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app tests
git commit -m "fix(review): hitung hanya reviewer aktif sesuai BR-REV-05"
```

---

### Task 10: Penyebut `EvaluationFormsOverview` ikut menghitung yang sudah tidak berlaku

`reviewerFormAssignments()` adalah `HasManyThrough` tanpa filter apa pun, jadi
`assigned_count` ikut menghitung penugasan milik reviewer yang sudah `replaced` dan
penugasan yang `is_active = false`. Keduanya tidak akan pernah muncul di pembilang, jadi
`completion_percentage` sebuah form bisa mentok di bawah 100% selamanya sesudah satu
pergantian reviewer.

Dua layar di `SubmissionViewController` (baris 810 dan 871) sudah memfilter
`->where('is_active', true)`. Kueri ini port setia dari `getEvaluationFormsSummary()` yang
lama, yang tidak memfilter — sama seperti Task 9, bugnya warisan.

**Files:**
- Modify: `app/Queries/Review/EvaluationFormsOverview.php`
- Test: `tests/Feature/Review/EvaluationFormsOverviewTest.php`

**Interfaces:**
- Consumes: hasil Task 9 (definisi "aktif" yang sama)
- Produces: `assigned_count` hanya menghitung penugasan yang masih berlaku

- [x] **Step 1: Tulis test yang gagal**

`overviewAssignment()` di berkas test itu selalu membuat reviewer `status = 'active'` dan
penugasan `is_active = true`. Tambahkan test yang membuat keduanya sebaliknya:

```php
it('tidak menghitung penugasan yang sudah tidak berlaku', function () {
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);
    $form = overviewEvaluationForm($detail, 1);

    overviewAssignment($submission, $form, 'submitted');

    // Reviewer yang diganti: penugasannya sejarah, bukan pekerjaan tertunda.
    $diganti = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'replaced',
    ]);
    ReviewerFormAssignment::create([
        'submission_reviewer_id' => $diganti->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
        'assigned_at' => now(),
        'is_active' => true,
    ]);

    $rows = EvaluationFormsOverview::run($submission->fresh());

    expect($rows[0]['assigned_count'])->toBe(1)
        ->and($rows[0]['completion_percentage'])->toBe(100);
});
```

- [x] **Step 2: Jalankan, pastikan gagal**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 \
  ./vendor/bin/pest tests/Feature/Review/EvaluationFormsOverviewTest.php
```
Expected: GAGAL — `assigned_count` dapat 2, `completion_percentage` dapat 50.

- [x] **Step 3: Saring di `countPerForm`**

Tambahkan filter pada kedua kueri, di `handle()`:

```php
$berlaku = $submission->reviewerFormAssignments()
    ->where('reviewer_form_assignments.is_active', true)
    ->where('submission_reviewers.status', 'active');
```

Nama tabel wajib disebut eksplisit — `HasManyThrough` menggabung dua tabel yang
sama-sama punya kolom `status` dan `is_active`, jadi kolom tanpa prefiks ambigu dan
PostgreSQL akan menolaknya. Nama tabelnya sudah dipastikan mengikuti konvensi Laravel
(tidak ada `protected $table` di kedua model): `submission_reviewers` dan
`reviewer_form_assignments`.

Karena `$berlaku` dipakai dua kali dan builder Eloquent **bersifat mutable**, bangun ulang
untuk kueri kedua alih-alih memakai ulang objek yang sama — atau `clone` keduanya.

- [x] **Step 4: Jalankan suite penuh**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 composer run quality
```
Expected: hijau, 878 + 1 = **879 test**.

- [x] **Step 5: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app tests
git commit -m "fix(review): saring penugasan yang sudah tidak berlaku dari penyebut"
```

---

### Task 11: `EvaluationRequirements` — test dulu, baru satukan kueri

`EvaluationRequirements` adalah **satu-satunya kelas dari gelombang 1 yang benar-benar
dilalui pengguna** (`SubmissionViewController::adminShowSubmission` baris 846 → prop
`evaluationRequirements` → `Pages/Submissions/ShowSubmission.vue` → `ReviewSystem.vue`).
Ia juga satu-satunya yang tidak punya test. Setiap kelas lain dari gelombang itu dapat
test karakterisasi; yang dipakai admin setiap hari justru tidak.

Ia juga menjalankan **tiga round trip** untuk satu jawaban, karena dua "kolom" yang
dibacanya sebenarnya accessor berisi kueri:

| Baris | Yang dibaca | Yang terjadi |
| ----- | ----------- | ------------ |
| 23 | `$detail->hasReviewEvaluationForms()` | `activeReviewEvaluationForms()->exists()` |
| 34 | `$detail->review_evaluation_forms_count` | `FormPhaseDetail::getReviewEvaluationFormsCountAttribute()` → `->count()` |
| 35 | `$detail->required_review_evaluation_forms_count` | `getRequiredReviewEvaluationFormsCountAttribute()` → `->count()` |

Ketiganya memindai `review_evaluation_forms` yang sama.

**Kerjakan test lebih dulu, lalu optimasi** — tanpa test, optimasi ini mengubah satu-satunya
layar hidup tanpa jaring.

**Files:**
- Test: `tests/Feature/Review/EvaluationRequirementsTest.php` (baru)
- Modify: `app/Queries/Review/EvaluationRequirements.php`

**Interfaces:**
- Consumes: `FormPhaseDetail`
- Produces: bentuk keluaran **tidak berubah** — kuncinya tetap `required`, `has_forms`,
  `total_forms`, `required_forms`, `message`

- [x] **Step 1: Tulis test karakterisasi**

Kunci ketiga cabangnya apa adanya, termasuk kalimat pesannya yang berbahasa Inggris —
jangan diperbaiki di sini, layar membacanya:

```php
<?php

// tests/Feature/Review/EvaluationRequirementsTest.php

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ReviewEvaluationForm;
use App\Queries\Review\EvaluationRequirements;

it('menyatakan tidak ada syarat saat pengajuan tanpa form phase detail', function () {
    $hasil = EvaluationRequirements::run(FormSubmission::factory()->create());

    expect($hasil['required'])->toBeFalse()
        ->and($hasil['has_forms'])->toBeFalse()
        ->and($hasil)->not->toHaveKey('total_forms');
});

it('menyatakan tidak ada syarat saat fase-nya tanpa form evaluasi', function () {
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    expect(EvaluationRequirements::run($submission)['has_forms'])->toBeFalse();
});

it('menghitung form seluruhnya dan yang wajib saja', function () {
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    // dua aktif, satu di antaranya wajib
    ReviewEvaluationForm::create([
        'title' => 'Substansi', 'form_phase_detail_id' => $detail->id,
        'is_active' => true, 'is_required' => true, 'order' => 1,
    ]);
    ReviewEvaluationForm::create([
        'title' => 'Anggaran', 'form_phase_detail_id' => $detail->id,
        'is_active' => true, 'is_required' => false, 'order' => 2,
    ]);

    $hasil = EvaluationRequirements::run($submission->fresh());

    expect($hasil['required'])->toBeTrue()
        ->and($hasil['total_forms'])->toBe(2)
        ->and($hasil['required_forms'])->toBe(1);
});
```

Kolomnya sudah dipastikan: `review_evaluation_forms.is_required` adalah `boolean` dengan
default `true` (migration `2025_09_17_041545`), dan kolom fase-nya `form_phase_detail_id`
sejak migration `2025_10_22_070354` mengganti `form_phase_id`.

- [x] **Step 2: Jalankan, pastikan LULUS**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 \
  ./vendor/bin/pest tests/Feature/Review/EvaluationRequirementsTest.php
```
Expected: LULUS. Ini test karakterisasi — ia merekam yang sudah ada, bukan menuntut yang
baru. Kalau ada yang merah, perbaiki **test-nya**, karena perilaku sekarang yang jadi
acuan.

- [x] **Step 3: Kunci jumlah kueri**

```php
it('menjawab dengan satu kueri, bukan tiga', function () {
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);
    ReviewEvaluationForm::create([
        'title' => 'Substansi', 'form_phase_detail_id' => $detail->id,
        'is_active' => true, 'is_required' => true, 'order' => 1,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        EvaluationRequirements::run($submission->fresh());
        expect(DB::getQueryLog())->toHaveCount(1);
    } finally {
        DB::disableQueryLog();
    }
});
```

`try`/`finally` bukan hiasan — lihat Task 12.

Expected saat dijalankan sekarang: **GAGAL**, dapat 3.

- [x] **Step 4: Ambil formnya sekali**

Ganti ketiga pembacaan itu dengan satu koleksi, lalu hitung di PHP:

```php
public function handle(FormSubmission $submission): array
{
    $forms = $submission->formPhaseDetail?->activeReviewEvaluationForms()->get();

    if ($forms === null || $forms->isEmpty()) {
        return [
            'required' => false,
            'has_forms' => false,
            'message' => 'No evaluation forms required for this submission.',
        ];
    }

    return [
        'required' => true,
        'has_forms' => true,
        'total_forms' => $forms->count(),
        'required_forms' => $forms->where('is_required', true)->count(),
        'message' => 'This submission requires evaluation forms to be completed before creating review threads.',
    ];
}
```

Penyaringan di PHP itu sah, dan sudah dipastikan: `FormPhaseDetail` (baris 116 dan 125)
mendefinisikan `activeReviewEvaluationForms()` sebagai `reviewEvaluationForms()->active()->ordered()`
dan `requiredReviewEvaluationForms()` sebagai `reviewEvaluationForms()->required()->active()->ordered()`.
Yang wajib memang himpunan bagian dari yang aktif, jadi `$forms->where('is_required', true)`
menghasilkan angka yang sama dengan kueri keduanya.

- [x] **Step 5: Jalankan gerbang penuh**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 composer run quality
```
Expected: hijau, 879 + 4 = **883 test**.

- [x] **Step 6: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app tests
git commit -m "perf(review): jawab syarat evaluasi dengan satu kueri, beserta testnya"
```

---

### Task 12: Query log di `EvaluationProgressTest` bocor kalau assertion gagal

```php
DB::enableQueryLog();
EvaluationProgress::run($submission);

expect(DB::getQueryLog())->toHaveCount(1);   // ← melempar saat gagal
DB::disableQueryLog();                        // ← tidak pernah jalan
```

Saat assertion-nya gagal, `disableQueryLog()` terlewat dan log tetap menyala untuk seluruh
test sesudahnya di proses yang sama. Akibatnya kegagalan aslinya terkubur di bawah
kegagalan susulan yang menghitung kueri milik test lain. `enableQueryLog()` juga tidak
mengosongkan log yang sudah terisi, jadi test ini bisa salah hitung kalau ada test lain
yang bocor lebih dulu.

**Files:**
- Modify: `tests/Feature/Review/EvaluationProgressTest.php`

- [x] **Step 1: Buktikan bocornya**

Ubah sementara assertion-nya jadi `->toHaveCount(99)` supaya ia gagal, jalankan berkasnya,
dan perhatikan test apa saja yang ikut merah sesudahnya. Kembalikan ke `1` setelah selesai
melihat.

- [x] **Step 2: Bungkus dengan `try`/`finally` dan kosongkan lebih dulu**

```php
it('memakai satu kueri, bukan lima COUNT terpisah', function () {
    $submission = submissionWithStatuses('completed', 'pending');

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        EvaluationProgress::run($submission);
        expect(DB::getQueryLog())->toHaveCount(1);
    } finally {
        DB::disableQueryLog();
    }
});
```

- [x] **Step 3: Ulangi Step 1, pastikan tidak ada lagi kegagalan susulan**

- [x] **Step 4: Jalankan gerbang penuh dan commit**

```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 composer run quality

git add tests
git commit -m "test(review): jangan biarkan query log menyala saat assertion gagal"
```
Expected: hijau, 883 test (jumlahnya tetap).

---

### Task 13: Dua layar, dua bentuk untuk satu prop

Prop `evaluationRequirements` dipancarkan dua tempat dengan bentuk yang berbeda, dan
keduanya bermuara ke komponen yang sama (`Components/ReviewSystem.vue`):

| Sumber | Kunci | Bahasa pesan |
| ------ | ----- | ------------ |
| `adminShowSubmission:846` → `EvaluationRequirements::run()` | `required`, `has_forms`, `total_forms`, `required_forms`, `message` | Inggris |
| `userShowSubmission:415` → array manual + `getEvaluationMessage()` | `required`, `has_forms`, `message` | Indonesia |

Jadi pengajuan yang sama menampilkan pesan berbeda dan kehilangan jumlah form tergantung
layar mana yang membukanya, dan perubahan berikutnya harus ditulis dua kali.

**Ini bukan sekadar merapikan.** `getEvaluationMessage()` menerima `$isAssignedReviewer`
dan `$assignmentsCount` — konteks per-pemakai yang **tidak** dimiliki
`EvaluationRequirements`. Menyatukan keduanya berarti memutuskan dulu: apakah pesannya
milik pengajuan (sama untuk semua orang) atau milik pemakai (berbeda per reviewer).
Rencana ini **tidak** memutuskannya.

- [x] **Step 1: Putuskan dulu pemilik pesannya**

Baca `getEvaluationMessage()` (`SubmissionViewController:1333`) dan `ReviewSystem.vue`
baris 132 dan sekitarnya. Jawab satu pertanyaan: apakah layar memakai `message` itu sebagai
keterangan pengajuan, atau sebagai instruksi untuk pembacanya?

- Kalau **instruksi per-pemakai** → `EvaluationRequirements` tidak boleh menghasilkan
  `message` sama sekali; pindahkan pesannya ke Query terpisah yang menerima `User`, dan
  `adminShowSubmission` ikut memakainya.
- Kalau **keterangan pengajuan** → `userShowSubmission` ikut memakai
  `EvaluationRequirements::run()`, dan `getEvaluationMessage()` dicabut.

**Bawa jawabannya ke pemilik produk sebelum menulis kode** — ini perubahan yang terlihat
pengguna, bukan refactor.

- [x] **Step 2: Setelah diputuskan, tulis test karakterisasi untuk kedua layar lebih dulu**

Test HTTP yang menegaskan bentuk prop yang dikirim tiap layar, **sebelum** disatukan.
Tanpa itu penyatuannya tidak punya acuan.

- [x] **Step 3: Satukan, jalankan gerbang penuh, commit**

Pesan commit menyusul isinya — kalau pesannya jadi milik pemakai, ia `refactor`; kalau
salah satu layar berubah tampilannya, ia `fix` atau `feat`.

---

### Task 14: Tenggat evaluasi di `ReviewController` masih menebak period (ADR 0004)

`Queries/Submission/EvaluationDueDate` sudah menyimpan versi yang benar, lengkap dengan dua
test regresi di `tests/Unit/EvaluationDueDateTest.php` — tapi **nol pemanggil produksi**.
Jalur yang hidup masih memakai `ReviewController::getEvaluationDueDate()` (baris 576), yang
menebak period lewat `submissionPeriodPhases...->first()`.

Itu persis bug yang ADR 0004 dibuat untuk membunuhnya: `submission_period_phases` tanpa
unique constraint, jadi satu `FormPhase` boleh dimiliki banyak period dan `->first()` bisa
mengambil period yang salah — lalu menstempel reviewer dengan tenggat milik period lain.

Ia juga mengarang tenggat: `new \DateTime('+7 days')` saat tidak ada period yang cocok,
sedangkan `EvaluationDueDate` mengembalikan `null`.

**Itu yang membuat ini bukan sekadar menyambungkan kabel.** Menukarnya mengubah perilaku:
penugasan yang selama ini selalu punya tenggat bisa mulai punya tenggat kosong.

**Files:**
- Modify: `app/Http/Controllers/ReviewController.php`
- Test: `tests/Feature/Review/AssignReviewersHttpTest.php`

- [x] **Step 1: Cari tahu apa yang terjadi kalau tenggatnya `null`**

Run:
```bash
grep -n 'function assignForm' -A 20 app/Models/SubmissionReviewer.php
grep -rn 'due_date\|evaluation_deadline' app/Models/ReviewerFormAssignment.php database/migrations/ | head
grep -rn 'due_date' resources/js/Components/ReviewSystem.vue resources/js/Components/AssignReviewerDialog.vue
```
Jawab: apakah kolomnya `nullable`? Apakah ada layar yang memformatnya tanpa penjaga null?
Apakah ada notifikasi yang menyebut tenggat?

- [x] **Step 2: Putuskan nasib fallback `+7 days`**

Tiga kemungkinan, pilih berdasar temuan Step 1:

| | Pilihan | Kapan ini yang benar |
| - | ------- | -------------------- |
| A | Biarkan `null` mengalir | Kolomnya nullable dan layarnya sudah tahan null |
| B | Pertahankan `+7 days`, tapi di pemanggil, bukan di dalam Query | Layar butuh tenggat dan `null` akan merusaknya |
| C | Tolak penugasan saat period tidak diketahui | Tenggat karangan lebih berbahaya daripada gagal terang-terangan |

Jangan menaruh fallback-nya di dalam `EvaluationDueDate` — Query itu menjawab "kapan
tenggatnya", dan "tidak tahu" adalah jawaban yang sah. Mengarang tanggal di sana
menyembunyikan ketidaktahuan dari setiap pemanggil berikutnya.

- [x] **Step 3: Tulis test yang gagal — kasus ADR 0004 lewat HTTP**

`tests/Unit/EvaluationDueDateTest.php:80` sudah mengunci kasus dua period berbagi satu
`FormPhase` di level Query. Tulis pasangannya di level HTTP: `POST` penugasan reviewer pada
pengajuan yang period-nya berbagi `FormPhase` dengan period lain, lalu tegaskan
`ReviewerFormAssignment` yang lahir memakai tenggat period **miliknya sendiri**.

Expected: GAGAL — ia mengambil tenggat period yang salah.

- [x] **Step 4: Sambungkan, dan cabut method lamanya**

Ganti `$this->getEvaluationDueDate($submissionModel)` (baris 551) jadi
`EvaluationDueDate::run($submissionModel)`, lalu hapus `getEvaluationDueDate()` (baris 576)
beserta import yang jadi tak terpakai. Terapkan keputusan Step 2 di pemanggilnya.

- [x] **Step 5: Jalankan gerbang penuh**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 composer run quality
```
Expected: hijau. `ReviewController` mengecil, dan `EvaluationDueDate` akhirnya punya
pemanggil produksi.

- [x] **Step 6: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app tests
git commit -m "fix(review): ambil tenggat evaluasi dari period pengajuan sendiri"
```

---

## Catatan, bukan task

**`ReviewController::getEvaluationStatus` tidak punya klien — dan jangan dihapus di sini.**
Rutenya terdaftar (`routes/web.php:350`), tapi `admin.submissions.evaluation-status` hanya
muncul di `resources/js/ziggy.js`, peta rute hasil generate. Tak ada komponen Vue, axios,
atau halaman Inertia yang memanggilnya. Artinya `EvaluationProgress`,
`EvaluationFormsOverview`, `PendingEvaluationCheck`, dan `EvaluationProgressData` menempel
ke endpoint yang belum dipakai siapa pun — klaim payoff di bagian 1 ("dipanggil di layar
daftar") memang berlebihan.

Tapi ini **endpoint JSON, dan assertion 10 mewajibkan setiap `response()->json()` melewati
DTO `Data\Contract`** ([`refactor-plan.md`](refactor-plan.md) §2.4). Itu pekerjaan
gelombang 3, yang justru mendatangi endpoint semacam ini. Menghapusnya sekarang membuang
calon kontrak API; membiarkannya tidak merugikan apa pun. **Serahkan ke gelombang 3.**

**`countPerForm` memakai `countBy()` di PHP, bukan `GROUP BY`.** Rencana bagian 1 (baris
983–985) menulis `selectRaw('COUNT(*) AS total')->groupBy(...)->pluck(...)`; yang
diimplementasikan menghidrasi model lalu menghitung di PHP. Deviasinya **disengaja dan
beralasan** — komentar di `EvaluationProgress` menyebutnya: `pluck()` atas agregat mentah
mengembalikan skalar tanpa tipe, dan menaikkannya jadi `int` butuh cast, sementara
AGENTS.md aturan 5 melarang cast untuk memuaskan PHPStan. Biayanya nyata tapi kecil:
8 reviewer × 5 form = 40 model untuk menghasilkan 5 bilangan. Layak ditinjau ulang kalau
ada pengajuan dengan puluhan reviewer; tidak layak diutak-atik sekarang.

**`EvaluationProgressData` menyimpan dua field yang bisa diturunkan.**
`completionPercentage` dan `allCompleted` bisa dihitung di `toArray()` dari empat field
lainnya. Menyimpannya membuka peluang dua sumber kebenaran kalau kelak ada pemanggil kedua.
Tapi selama pemanggilnya cuma satu dan langsung di-`toArray()`, ini belum masalah — dan
Task 9 tetap mengubah cara `allCompleted` diturunkan. Tinjau ulang kalau DTO ini mulai
dipakai lebih dari satu tempat.

---

## Setelah rencana ini

`FormSubmission` selesai sejauh yang bisa dikerjakan: logic yang bukan miliknya sudah
keluar, tapi berkasnya tetap di `quality-legacy.txt` (Task 6). **Bagian 2 (Task 7–14)
harus tuntas lebih dulu** sebelum model berikutnya dimulai — Task 7 di dalamnya menutup
lubang gerbang yang akan menjaga seluruh Query yang lahir sesudahnya. Model berikutnya mengikuti
bentuk yang sama — **baca dulu, golongkan, baru pindahkan** — dan masing-masing mendapat
rencananya sendiri:

| Model | Method non-relasi | Baris |
| ----- | ----------------: | ----: |
| `SubmissionReviewer` | ~18 | 305 |
| `ReviewFormResponse` | ~13 | 242 |
| `ReviewerFormAssignment` | ~9 | 176 |
| `ReviewFormFieldResponse` | ~9 | 206 |
| `FormPhaseDetail` | ~7 | 208 |
| `ReviewSummary` | ~6 | 282 |
| `Organization` | ~6 | 222 |

Angka "~" itu perkiraan dari penggolongan otomatis yang **terbukti meleset** — ia
menunjukkan ukuran pekerjaan, bukan isinya. Tiap rencana berikutnya dimulai dengan
membaca modelnya, seperti rencana ini dimulai.

Tiga temuan dari `FormSubmission` yang mungkin berulang di model lain, dan layak dicari
sejak awal:

1. **Method tanpa pemanggil** — **10 dari 30** di sini, bukan 5. Lima ditemukan Task 1,
   lima lagi baru ketahuan setelah dipindahkan dan harus dihapus lagi. Karena itu:
   **hitung pemanggil setiap method sebelum memindahkannya**, bukan hanya method yang
   terlihat mencurigakan. Satu `grep -c` per method jauh lebih murah daripada satu kelas
   plus test-nya yang harus dibatalkan.
2. **Beberapa nama untuk satu pertanyaan** — 4 dari 30. Lebih murah diruntuhkan daripada
   dipindahkan berempat.
3. **Duplikat yang hidup di controller.** Method model yang mati sering punya kembaran
   yang masih dipakai di `Http/Controllers/`, kadang versi yang lebih buruk. Periksa
   controller-nya sebelum menyimpulkan sebuah method "tidak dipakai lagi" — yang mati
   modelnya, bukan perilakunya.

**Target ukuran berkas.** `SubmissionReviewer` (304), `ReviewSummary` (281),
`ReviewFormResponse` (241), `Organization` (221), `FormPhaseDetail` (207) dan
`ReviewFormFieldResponse` (205) semuanya >200 baris. Sebelum menjanjikan "keluar dari
antrean" di rencananya, **ukur dulu lantainya** — baris kode tanpa komentar, baris kosong,
dan import. Kalau lantainya sudah >200, target itu mustahil dan rencananya harus
mengatakan begitu sejak awal, seperti Task 6 di sini.
