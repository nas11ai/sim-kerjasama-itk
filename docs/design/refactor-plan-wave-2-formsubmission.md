# Refactor Gelombang 2 (bagian 1) — `FormSubmission`

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development`
> (disarankan) atau `superpowers:executing-plans`. Langkahnya memakai checkbox (`- [ ]`).

**Goal:** Mengosongkan `FormSubmission` dari logic yang bukan miliknya. Bagian kedua goal
ini — "sampai ia keluar dari `quality-legacy.txt`" — **terbukti mustahil**; lihat Task 6.

**Architecture:** Buang dulu, runtuhkan kedua, pindahkan terakhir. Seperenam logic-nya
kode mati dan empat method menjawab satu pertanyaan yang sama — memindahkannya lebih
dulu berarti memindahkan sampah ke rumah baru.

**Tech Stack:** Laravel 13 · PHP 8.4 · Pest · PHPStan level 10

**Spec:** [`docs/design/refactor-plan.md`](refactor-plan.md) v1.5 §2.5

**Status:** dieksekusi 20 September 2026 di branch `feat/init-be-development`,
commit `5e44f54`..`9c3fb95`, lalu dibersihkan dari lima kelas yang tidak terpakai.
Task 1–5 selesai; Task 6 tidak bisa dijalankan.

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

## Setelah rencana ini

`FormSubmission` selesai sejauh yang bisa dikerjakan: logic yang bukan miliknya sudah
keluar, tapi berkasnya tetap di `quality-legacy.txt` (Task 6). Model berikutnya mengikuti
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
