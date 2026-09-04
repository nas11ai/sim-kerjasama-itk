# M1 — Irisan Vertikal HTTP: Submit, State, Access

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membuktikan 27 skenario M1 yang sudah tertulis tapi belum punya test —
`SM-ACCESS`, `SM-STATE`, `SM-SUBMIT` — sampai keluar dari `pending.txt` (114 → 87),
sekaligus memperbaiki tiga cacat yang dibongkar skenario itu.

**Architecture:** Delapan task berurutan, tiap task satu tema yang bisa direview sekali
duduk. Tiap skenario dibuktikan di **lapis terendah yang masih membuktikan seluruh
kalimatnya** — `403` menuntut test HTTP, `status menjadi SUBMITTED` cukup di Action.
Controller warisan `submitForm()` **tidak** ditulis ulang; aturan domain mendarat di
`SubmitFormSubmission` dan controller mendelegasikan seperlunya.

**Tech Stack:** Laravel 13 · PHP 8.4 · PostgreSQL · Pest · `spatie/laravel-model-states` ·
`lorisleiva/laravel-actions` · `spatie/laravel-permission` · `behat/gherkin` (parser guard)

**Spec:** [`docs/design/bdd-coverage/m1-http-slice.md`](../design/bdd-coverage/m1-http-slice.md)
— plan ini berargumen dari spec itu; keduanya dibaca bersama. Skenarionya sudah ada di
`docs/requirement-analysis/bdd/core/submission/`.

## Global Constraints

- **Setiap task berakhir dengan `composer run quality` hijau sebelum commit.** Pint +
  PHPStan level 5 + PHPStan level 9 (`phpstan-strict.neon`) + seluruh Pest.
- **Commit memakai Conventional Commits.**
- **Dilarang menambah dependency.** Semua yang dibutuhkan sudah terpasang.
- **Dilarang mengubah `docs/requirement-analysis/ddd/`.** Kalau implementasi mengungkap
  aturan yang salah, hasilnya entri di `open-questions.md` — bukan sunting sepihak.
- **Dilarang mengubah berkas `.feature`.** Skenarionya sudah dikunci di Fase A. Kalau
  sebuah skenario ternyata tidak bisa diimplementasikan, itu entri `open-questions.md`,
  bukan alasan melunakkan kalimatnya.
- **Status hanya berubah lewat `transitionTo()`**, tidak pernah `update(['status' => …])`.
- **Setiap query yang mengembalikan data submission wajib di-scope** `isAccessibleBy()`.
- **Business logic tidak di Controller** — Controller hanya FormRequest → DTO → Action → response.
- **PostgreSQL, bukan MySQL.** Tidak ada `YEAR()`, tidak ada `LIKE` untuk pencarian
  case-insensitive (pakai `whereLike`/`ILIKE`).
- **Dilarang menyiasati PHPStan** — tidak ada `@phpstan-ignore`, entri baseline,
  `assert()`, inline `@var`, atau cast untuk menghilangkan error.
- **Dilarang percabangan khusus testing di production code** (`app()->environment('testing')`).
- **Guard clause, bukan `if` bersarang.** Satu Action = satu use case.
- **Gaya negasi `!$x`, bukan `! $x`** — `pint.json` menyetel
  `not_operator_with_successor_space=false`. Jalankan Pint sebelum commit.
- **Sebut kode BR di komentar** saat mengimplementasikan aturan
  (mis. `// BR-SM-04 — hanya submitted_by yang boleh submit`).
- **Sebut ID skenario di deskripsi test** (`it('[SM-ACCESS-03] ...')`), lalu hapus ID itu
  dari `pending.txt` dan turunkan plafonnya di task yang sama.

### Nama fungsi global Pest yang SUDAH terpakai

Pest memuat seluruh berkas test, jadi fungsi global bertabrakan lintas berkas dan
menghasilkan fatal error. Nama berikut **tidak boleh** dipakai ulang:

```
actingFormAdmin actingResearcher bddIdsClaimedByTests bddNonBehavioral bddPending
bddRuleCodes bddScenarios bddUncovered budgetFixture budgetSuperAdmin
buildAccessibleFormPhaseDetail concreteModelClasses createActivePeriod
createFormWithAccess createSubmissionDate createUserWithOrganization draftPayload
enrolResearchMember invokeGetEvaluationDueDate invokeMigrationMethod lineItemAttributes
makeSubmissionReviewer plainSubmission setUpBiodataForm submissionFor submissionOwnedBy
submissionWithStatus superAdmin writeLedger
```

### Menjalankan perintah

Host PHP 8.5 tidak bisa dipakai (`composer install` gagal di `phpoffice/phpspreadsheet`,
dan `pdo_pgsql` tidak ada). Container wajib.

```bash
# Postgres — sekali saja
docker run -d --name simpas_dev_pg -e POSTGRES_DB=simpas_test \
  -e POSTGRES_USER=postgres -e POSTGRES_PASSWORD=postgres \
  -p 55432:5432 postgres:18-alpine
# kalau sudah pernah dibuat: docker start simpas_dev_pg

# Image proyek — sekali saja. php:8.4-cli polos TIDAK punya pdo_pgsql maupun composer.
docker build -t simpas-php84:local - <<'DOCKERFILE'
FROM php:8.4-cli
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev unzip git \
 && docker-php-ext-install pdo_pgsql \
 && apt-get purge -y --auto-remove \
 && rm -rf /var/lib/apt/lists/*
DOCKERFILE
```

Alias yang dipakai sepanjang plan ini:

```bash
# PEST — satu berkas atau seluruh suite
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest <args>

# QUALITY — gate penuh
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  -e COMPOSER_HOME=/tmp/composer simpas-php84:local composer run quality
```

---

## File Structure

| Berkas | Tanggung jawab |
| ------ | -------------- |
| `tests/Feature/SubmissionStateTest.php` | **Modify.** Sisipkan `[SM-STATE-02]` ke enam test transisi terlarang yang sudah ada |
| `tests/Unit/Actions/SubmitFormSubmissionTest.php` | **Modify.** Sisipkan `[SM-STATE-01]`; jadi rumah test BR-SM-04 dan BR-SM-02 |
| `app/Http/Controllers/SubmissionViewController.php` | **Modify.** `userShowSubmission()` memakai `isAccessibleBy()` |
| `tests/Feature/Submission/SubmissionAccessHttpTest.php` | **Create.** `[SM-ACCESS-01..06]` di lapis HTTP |
| `tests/Feature/Submission/SubmissionEditLockHttpTest.php` | **Create.** `[SM-STATE-04]` — penguncian isi pada status terminal |
| `tests/Feature/Submission/SubmitAuthorizationTest.php` | **Create.** `[SM-SUBMIT-09, 10, 11, 13, 14]` |
| `app/Policies/FormSubmissionPolicy.php` | **Modify.** Tambah `resubmit()` |
| `app/Actions/FormSubmission/ResubmitFormSubmission.php` | **Create.** Satu use case: NEEDS_REVISION → RESUBMITTED |
| `tests/Feature/Submission/ResubmitTest.php` | **Create.** `[SM-SUBMIT-12]`, `[SM-STATE-03]` |
| `app/Actions/FormSubmission/SubmitFormSubmission.php` | **Modify.** Gerbang proposal PDF (BR-SM-02), keunikan (BR-SM-01), pesan manusiawi (BR-SM-16) |
| `tests/Feature/Submission/SubmitProposalRequirementTest.php` | **Create.** `[SM-SUBMIT-07, 08]` |
| `tests/Feature/Submission/SubmitUniquenessTest.php` | **Create.** `[SM-SUBMIT-01..06]` |
| `tests/Feature/Submission/SubmitRejectionMessageTest.php` | **Create.** `[SM-SUBMIT-15, 16, 17]` |

Ledger yang disentuh **setiap** task: `docs/requirement-analysis/bdd/pending.txt`.
`uncovered.txt` **tidak disentuh sama sekali** — kedelapan BR di sini sudah punya
skenario sejak Fase A.

### Aritmetika ledger — WAJIB berurutan

Kondisi awal: `pending` 114/114, `uncovered` 59/59.

| Task | ID yang keluar | `pending` |
| ---- | -------------- | --------- |
| 1 | `SM-STATE-01`, `SM-STATE-02` | 114 → **112** |
| 2 | `SM-ACCESS-01..06` | 112 → **106** |
| 3 | `SM-STATE-04` | 106 → **105** |
| 4 | `SM-SUBMIT-09, 10, 11, 13, 14` | 105 → **100** |
| 5 | `SM-SUBMIT-12`, `SM-STATE-03` | 100 → **98** |
| 6 | `SM-SUBMIT-07, 08` | 98 → **96** |
| 7 | `SM-SUBMIT-01..06` | 96 → **90** |
| 8 | `SM-SUBMIT-15, 16, 17` | 90 → **87** |

`[SM-STATE-05]` **sengaja tetap di `pending`** — lihat spec §6.

---

## Task 1: Sambungkan test state machine yang sudah ada ke ID skenario

**Files:**
- Modify: `tests/Feature/SubmissionStateTest.php`
- Modify: `tests/Unit/Actions/SubmitFormSubmissionTest.php`
- Modify: `docs/requirement-analysis/bdd/pending.txt`

**Interfaces:**
- Produces: tidak ada kode baru. Task ini hanya membuat ketertelusuran yang sudah
  dibuktikan jadi terbaca mesin.

`SubmissionStateTest` sudah membuktikan keenam transisi terlarang di `Examples`
`[SM-STATE-02]`, dan `SubmitFormSubmissionTest` sudah membuktikan `[SM-STATE-01]`.
Yang hilang cuma ID-nya. **Jangan mengubah isi test-nya.**

- [ ] **Step 1: Sisipkan `[SM-STATE-02]` ke enam test transisi terlarang**

Ubah keenam deskripsi ini di `tests/Feature/SubmissionStateTest.php` — hanya
deskripsinya, badan test tidak disentuh:

| Semula | Menjadi |
| ------ | ------- |
| `it('prevents draft to approved transition'` | `it('[SM-STATE-02] prevents draft to approved transition'` |
| `it('prevents approved to draft transition'` | `it('[SM-STATE-02] prevents approved to draft transition'` |
| `it('prevents rejected to approved transition'` | `it('[SM-STATE-02] prevents rejected to approved transition'` |
| `it('prevents withdrawn to approved transition'` | `it('[SM-STATE-02] prevents withdrawn to approved transition'` |
| `it('prevents draft to resubmitted transition'` | `it('[SM-STATE-02] prevents draft to resubmitted transition'` |
| `it('prevents rejected to resubmitted transition'` | `it('[SM-STATE-02] prevents rejected to resubmitted transition'` |

Keenamnya memetakan tepat ke enam baris `Examples` di `state.feature`:
DRAFT→APPROVED, APPROVED→DRAFT, REJECTED→APPROVED, WITHDRAWN→APPROVED,
DRAFT→RESUBMITTED, REJECTED→RESUBMITTED.

- [ ] **Step 2: Sisipkan `[SM-STATE-01]`**

Di `tests/Unit/Actions/SubmitFormSubmissionTest.php`, ubah:

```php
it('updates submission status to submitted', function () {
```

menjadi:

```php
it('[SM-STATE-01] updates submission status to submitted', function () {
```

- [ ] **Step 3: Jalankan test**

Run PEST `tests/Feature/SubmissionStateTest.php tests/Unit/Actions/SubmitFormSubmissionTest.php`.
Expected: PASS, 15 test. Tidak ada yang berubah selain namanya.

- [ ] **Step 4: Keluarkan ID dari pending**

Di `docs/requirement-analysis/bdd/pending.txt`: hapus baris `SM-STATE-01` dan
`SM-STATE-02`, lalu setel plafon menjadi `# max: 112`.

Verifikasi:

```bash
grep -vcE '^\s*(#|$)' docs/requirement-analysis/bdd/pending.txt   # harus 112
```

- [ ] **Step 5: Jalankan guard**

Run PEST `tests/Unit/Bdd/BddCoverageTest.php`. Expected: PASS, 9 test.

Kalau assertion 5 gagal ("pending memuat skenario yang sudah diimplementasikan"),
berarti Step 4 belum dijalankan.

- [ ] **Step 6: Gate penuh lalu commit**

Run QUALITY.

```bash
git add tests docs/requirement-analysis/bdd
git commit -m "test(submission): tie state machine tests to SM-STATE-01 and SM-STATE-02"
```

---

## Task 2: `userShowSubmission` memakai `isAccessibleBy()`

**Files:**
- Create: `tests/Feature/Submission/SubmissionAccessHttpTest.php`
- Modify: `app/Http/Controllers/SubmissionViewController.php`
- Modify: `docs/requirement-analysis/bdd/pending.txt`

**Interfaces:**
- Consumes: `FormSubmission::isAccessibleBy(User $user): bool` — sudah ada, sudah
  bertest unit di `tests/Unit/FormSubmissionIsAccessibleByTest.php`
- Produces: pola test HTTP yang dipakai Task 3 dan 4

Cacat yang diperbaiki: `userShowSubmission()` memakai cek buatan sendiri yang
**melewatkan anggota penelitian dan pemegang `submissions.view-all`**. Dua dari enam
test di Step 1 akan gagal karena itu — itu bukti cacatnya, bukan test yang salah.

`TestCase::setUp()` sudah memanggil `withoutVite()`, jadi halaman Inertia bisa dirender
di test tanpa manifest. Middleware `check_biodata` hanya menggigit peran `Mahasiswa` dan
`Tenaga Kependidikan`, jadi `User::factory()` polos lolos.

- [ ] **Step 1: Tulis test HTTP yang gagal**

```php
<?php

// tests/Feature/Submission/SubmissionAccessHttpTest.php
//
// BR-SM-07 — lapis akses kedua di luar FormAccessControl. Test unit di
// tests/Unit/FormSubmissionIsAccessibleByTest.php sudah membuktikan aturannya;
// berkas ini membuktikan controller benar-benar memakainya.

use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\UnderReview;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('submissions.view-all', 'web');
});

/** @return array{0: User, 1: FormSubmission} */
function accessSliceSubmission(): array
{
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => UnderReview::class,
    ]);

    return [$owner, $submission];
}

/** ResearchMember menunjuk UserProfile, bukan User. */
function accessSliceEnrol(FormSubmission $submission, User $user, string $role): void
{
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $user->id])->id,
        'role' => $role,
        'status' => 'active',
    ]);
}

function accessSliceAssignReviewer(FormSubmission $submission, User $user): void
{
    $reviewer = Reviewer::factory()->create(['user_id' => $user->id]);
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);
}

it('[SM-ACCESS-01] pemilik melihat pengajuannya', function () {
    [$owner, $submission] = accessSliceSubmission();

    $this->actingAs($owner)
        ->get(route('user.submissions.show', $submission))
        ->assertOk();
});

it('[SM-ACCESS-02] anggota penelitian melihat pengajuan yang ia ikuti', function () {
    [, $submission] = accessSliceSubmission();
    $member = User::factory()->create();
    accessSliceEnrol($submission, $member, ResearchMember::ROLE_CO_INVESTIGATOR);

    $this->actingAs($member)
        ->get(route('user.submissions.show', $submission))
        ->assertOk();
});

it('[SM-ACCESS-03] researcher lain di organisasi yang sama ditolak', function () {
    [, $submission] = accessSliceSubmission();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('user.submissions.show', $submission))
        ->assertStatus(403);
});

it('[SM-ACCESS-04] operator melihat semua lewat submissions.view-all', function () {
    [, $submission] = accessSliceSubmission();
    $operator = User::factory()->create();
    $operator->givePermissionTo('submissions.view-all');

    $this->actingAs($operator)
        ->get(route('user.submissions.show', $submission))
        ->assertOk();
});

it('[SM-ACCESS-05] reviewer yang ditugaskan melihat submission itu', function () {
    [, $submission] = accessSliceSubmission();
    $reviewerUser = User::factory()->create();
    accessSliceAssignReviewer($submission, $reviewerUser);

    $this->actingAs($reviewerUser)
        ->get(route('user.submissions.show', $submission))
        ->assertOk();
});

it('[SM-ACCESS-06] reviewer yang tidak ditugaskan ditolak', function () {
    [, $submission] = accessSliceSubmission();
    $reviewerUser = User::factory()->create();
    Reviewer::factory()->create(['user_id' => $reviewerUser->id]);

    $this->actingAs($reviewerUser)
        ->get(route('user.submissions.show', $submission))
        ->assertStatus(403);
});
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run PEST `tests/Feature/Submission/SubmissionAccessHttpTest.php`.
Expected: FAIL pada `[SM-ACCESS-02]` dan `[SM-ACCESS-04]` — keduanya mendapat 403
padahal seharusnya 200. Empat sisanya sudah lolos.

Kalau `[SM-ACCESS-05]` ikut gagal, periksa `Reviewer::factory()` — jangan melonggarkan
test, perbaiki fixture-nya.

- [ ] **Step 3: Ganti cek buatan sendiri dengan `isAccessibleBy()`**

Di `app/Http/Controllers/SubmissionViewController.php`, di dalam `userShowSubmission()`,
ganti blok ini:

```php
        // Check permission
        $canView = $submission->submitted_by === $user->id ||
            $this->canUserReview($submission, $user);

        if (!$canView) {
            abort(403, 'Akses tidak sah terhadap pengajuan.');
        }
```

menjadi:

```php
        // BR-SM-07 — akses level submission adalah lapis terpisah dari
        // FormAccessControl. Cek buatan sendiri di sini dulu melewatkan anggota
        // penelitian dan pemegang submissions.view-all.
        abort_unless($submission->isAccessibleBy($user), 403, 'Akses tidak sah terhadap pengajuan.');
```

`$this->canUserReview()` masih dipakai di tempat lain pada method yang sama
(`$isAssignedReviewer`) — **jangan menghapusnya**.

- [ ] **Step 4: Jalankan test sampai lulus**

Run PEST `tests/Feature/Submission/SubmissionAccessHttpTest.php`. Expected: PASS, 6 test.

- [ ] **Step 5: Keluarkan ID dari pending**

Hapus `SM-ACCESS-01` sampai `SM-ACCESS-06` dari `pending.txt`; setel `# max: 106`.

```bash
grep -vcE '^\s*(#|$)' docs/requirement-analysis/bdd/pending.txt   # harus 106
```

- [ ] **Step 6: Gate penuh lalu commit**

Run QUALITY. Expected: Pint PASS, PHPStan L5 dan L9 `[OK] No errors`, seluruh Pest hijau.

```bash
git add app tests docs/requirement-analysis/bdd
git commit -m "fix(submission): scope detail view with isAccessibleBy for BR-SM-07"
```

---

## Task 3: Penguncian isi pengajuan pada status terminal

**Files:**
- Create: `tests/Feature/Submission/SubmissionEditLockHttpTest.php`
- Modify: `app/Http/Controllers/UserFormController.php:372`
- Modify: `docs/requirement-analysis/bdd/pending.txt`

**Interfaces:**
- Consumes: `FormSubmissionPolicy::update(User $user, FormSubmission $submission): bool`
  — sudah ada, sudah mengembalikan `false` untuk APPROVED/REJECTED/WITHDRAWN
- Produces: gerbang 403 di `saveDraft()`

`FormSubmissionPolicy::update()` sudah menegakkan BR-SM-05, tapi `saveDraft()` tidak
pernah menanyakannya. Task ini menyambungkannya.

Fungsi global `actingResearcher()`, `buildAccessibleFormPhaseDetail()`, dan
`draftPayload()` sudah ada di `tests/Feature/UserFormControllerFormPhaseDetailTest.php`
dan bisa dipakai langsung — Pest memuat seluruh berkas test.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Feature/Submission/SubmissionEditLockHttpTest.php
//
// BR-SM-05 — isi pengajuan terkunci pada status terminal. Policy sudah
// menegakkannya; berkas ini membuktikan saveDraft() benar-benar menanyakannya.

use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\States\Submission\Approved;
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;

it('[SM-STATE-04] isi pengajuan terkunci pada status terminal', function (string $state) {
    [$user, $organization] = actingResearcher();

    $form = Form::factory()->create();
    $accessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $formPhase = FormPhase::create(['title' => 'Fase Uji', 'is_active' => true]);
    $built = buildAccessibleFormPhaseDetail($user, $organization, $accessControl, $formPhase);

    // Baris submission dibuat langsung dengan status terminal. Status di sini
    // disetel saat pembuatan, bukan lewat transisi — DRAFT→APPROVED memang
    // transisi terlarang ([SM-STATE-02]).
    FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
        'submission_period_id' => $built['period']->id,
        'form_phase_detail_id' => $built['detail']->id,
        'status' => $state,
    ]);

    $this->actingAs($user)
        ->post(route('user.form-submission.save-draft'), draftPayload($form, $built['period'], $formPhase))
        ->assertStatus(403);
})->with([Approved::class, Rejected::class, Withdrawn::class]);
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run PEST `tests/Feature/Submission/SubmissionEditLockHttpTest.php`.
Expected: FAIL — `saveDraft()` mengembalikan redirect (302), bukan 403.

- [ ] **Step 3: Tambahkan gerbang di `saveDraft()`**

Di `app/Http/Controllers/UserFormController.php`, tepat **setelah** blok
`FormSubmission::firstOrCreate([...])` di baris 372 dan **sebelum**
`$submission->update([...])`, sisipkan:

```php
            // BR-SM-05 — isi pengajuan terkunci pada status terminal. abort() di
            // dalam DB::transaction membatalkan transaksinya, jadi tidak ada
            // response yang terlanjur tertulis.
            abort_unless($user->can('update', $submission), 403, 'Pengajuan ini sudah terkunci.');
```

- [ ] **Step 4: Jalankan test sampai lulus**

Run PEST `tests/Feature/Submission/SubmissionEditLockHttpTest.php`.
Expected: PASS, 3 test (satu per dataset).

Jalankan juga `tests/Feature/UserFormControllerFormPhaseDetailTest.php` — keempat test
di sana menyimpan draft pada submission DRAFT dan **harus tetap hijau**. Kalau salah
satunya berubah jadi 403, gerbangnya terlalu ketat.

- [ ] **Step 5: Keluarkan ID dari pending**

Hapus `SM-STATE-04` dari `pending.txt`; setel `# max: 105`.

- [ ] **Step 6: Gate penuh lalu commit**

Run QUALITY.

```bash
git add app tests docs/requirement-analysis/bdd
git commit -m "fix(submission): lock draft editing on terminal states for BR-SM-05"
```

---

## Task 4: Siapa yang boleh submit

**Files:**
- Create: `tests/Feature/Submission/SubmitAuthorizationTest.php`
- Modify: `docs/requirement-analysis/bdd/pending.txt`

**Interfaces:**
- Consumes: `SubmitFormSubmission::run(FormSubmission $submission, User $user): FormSubmission`
  — sudah ada, sudah melempar `AuthorizationException` kalau `submitted_by` tidak cocok
- Consumes: `FormSubmissionPolicy::update()` dan `withdraw()` untuk `[SM-SUBMIT-14]`

Aturannya (BR-SM-04) **sudah** ditegakkan Action dan sudah dikunci ADR 0015. Task ini
membuktikan keempat aktor berperilaku seperti yang skenario nyatakan. Tidak ada kode
produksi yang berubah — kalau ada test yang gagal, itu temuan, bukan pekerjaan rutin.

`[SM-SUBMIT-14]` menyentuh HTTP karena kalimatnya `Then detail terbuka`. Super Admin
lolos `isAccessibleBy()` karena `Gate::before` membuat `$user->can('submissions.view-all')`
bernilai true untuknya.

- [ ] **Step 1: Tulis test**

```php
<?php

// tests/Feature/Submission/SubmitAuthorizationTest.php
//
// BR-SM-04 — hanya submitted_by yang boleh submit. Ditegakkan
// SubmitFormSubmission dan dikunci ADR 0015 (Gate::before tidak melewatinya).

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Role;

/** @return array{0: User, 1: FormSubmission} */
function submitSliceDraft(string $state = Draft::class): array
{
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => $state,
        'scheme_id' => null,
    ]);

    return [$owner, $submission];
}

function submitSliceEnrol(FormSubmission $submission, User $user, string $role): void
{
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $user->id])->id,
        'role' => $role,
        'status' => 'active',
    ]);
}

function submitSliceSuperAdmin(): User
{
    Role::findOrCreate('Super Admin');
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    return $admin;
}

it('[SM-SUBMIT-09] ketua pengaju submit pengajuannya sendiri', function () {
    [$owner, $submission] = submitSliceDraft();

    SubmitFormSubmission::run($submission, $owner);

    expect($submission->fresh()->status)->toBeInstanceOf(Submitted::class);
});

it('[SM-SUBMIT-10] co-investigator tidak bisa submit', function () {
    [, $submission] = submitSliceDraft();
    $co = User::factory()->create();
    submitSliceEnrol($submission, $co, ResearchMember::ROLE_CO_INVESTIGATOR);

    expect(fn () => SubmitFormSubmission::run($submission, $co))
        ->toThrow(AuthorizationException::class);
    expect($submission->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('[SM-SUBMIT-11] anggota biasa tidak bisa submit', function () {
    [, $submission] = submitSliceDraft();
    $member = User::factory()->create();
    submitSliceEnrol($submission, $member, ResearchMember::ROLE_MEMBER);

    expect(fn () => SubmitFormSubmission::run($submission, $member))
        ->toThrow(AuthorizationException::class);
    expect($submission->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('[SM-SUBMIT-13] super admin bukan pemilik tidak bisa submit', function () {
    [, $submission] = submitSliceDraft();
    $admin = submitSliceSuperAdmin();

    expect($admin->can('submit', $submission))->toBeFalse();
    expect(fn () => SubmitFormSubmission::run($submission, $admin))
        ->toThrow(AuthorizationException::class);
    expect($submission->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('[SM-SUBMIT-14] super admin tetap bisa melihat, menyunting, dan menarik', function () {
    [, $submission] = submitSliceDraft(Approved::class);
    $admin = submitSliceSuperAdmin();

    $this->actingAs($admin)
        ->get(route('user.submissions.show', $submission))
        ->assertOk();

    expect($admin->can('update', $submission))->toBeTrue()
        ->and($admin->can('withdraw', $submission))->toBeTrue();
});
```

- [ ] **Step 2: Jalankan test**

Run PEST `tests/Feature/Submission/SubmitAuthorizationTest.php`.
Expected: PASS, 5 test — tanpa perubahan kode produksi.

Kalau `[SM-SUBMIT-13]` gagal, `Gate::before` bocor: periksa `INVARIANT_ABILITIES` memuat
`submit`. Kalau `[SM-SUBMIT-14]` gagal pada `withdraw`, baca
`FormSubmissionPolicy::withdraw()` — jangan melonggarkan test, catat temuannya.

- [ ] **Step 3: Keluarkan ID dari pending**

Hapus `SM-SUBMIT-09`, `SM-SUBMIT-10`, `SM-SUBMIT-11`, `SM-SUBMIT-13`, `SM-SUBMIT-14`
dari `pending.txt`; setel `# max: 100`.

- [ ] **Step 4: Gate penuh lalu commit**

Run QUALITY.

```bash
git add tests docs/requirement-analysis/bdd
git commit -m "test(submission): prove BR-SM-04 across all four actors"
```

---

## Task 5: `resubmit` — Policy yang hilang

**Files:**
- Modify: `app/Policies/FormSubmissionPolicy.php`
- Create: `app/Actions/FormSubmission/ResubmitFormSubmission.php`
- Create: `tests/Feature/Submission/ResubmitTest.php`
- Modify: `docs/requirement-analysis/bdd/pending.txt`

**Interfaces:**
- Produces: `FormSubmissionPolicy::resubmit(User $user, FormSubmission $submission): bool`
- Produces: `ResubmitFormSubmission::run(FormSubmission $submission, User $user): FormSubmission`

Cacat yang diperbaiki: `INVARIANT_ABILITIES` memuat `resubmit`, sehingga `Gate::before`
sengaja tidak melewatinya — tapi `FormSubmissionPolicy` tidak punya method `resubmit`,
dan ability tanpa method Policy selalu ditolak. Terbukti:
`$owner->can('resubmit', $s)` bernilai `false` pada submission NEEDS_REVISION miliknya
sendiri. Artinya **pemilik tidak bisa merevisi pengajuannya**.

`app/Policies/` dan `app/Actions/` otomatis kena PHPStan level 9.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Feature/Submission/ResubmitTest.php
//
// BR-SM-04 mencakup submit DAN resubmit. BR-SM-03 menetapkan
// NEEDS_REVISION → RESUBMITTED → UNDER_REVIEW.

use App\Actions\FormSubmission\ResubmitFormSubmission;
use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Resubmitted;
use Illuminate\Auth\Access\AuthorizationException;

/** @return array{0: User, 1: FormSubmission} */
function resubmitSliceSubmission(string $state = NeedsRevision::class): array
{
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => $state,
        'scheme_id' => null,
    ]);

    return [$owner, $submission];
}

it('[SM-SUBMIT-12] ketua pengaju resubmit dari NEEDS_REVISION', function () {
    [$owner, $submission] = resubmitSliceSubmission();

    expect($owner->can('resubmit', $submission))->toBeTrue();

    ResubmitFormSubmission::run($submission, $owner);

    expect($submission->fresh()->status)->toBeInstanceOf(Resubmitted::class);
});

it('[SM-SUBMIT-12] bukan pemilik tidak bisa resubmit', function () {
    [, $submission] = resubmitSliceSubmission();
    $orang = User::factory()->create();

    expect($orang->can('resubmit', $submission))->toBeFalse();
    expect(fn () => ResubmitFormSubmission::run($submission, $orang))
        ->toThrow(AuthorizationException::class);
});

it('[SM-STATE-03] revisi kembali masuk antrean review', function () {
    [$owner, $submission] = resubmitSliceSubmission();

    ResubmitFormSubmission::run($submission, $owner);

    // RESUBMITTED → UNDER_REVIEW adalah transisi yang sah (SubmissionStateTest
    // membuktikannya); skenario menuntut keduanya terjadi berurutan.
    $submission->fresh()->status->transitionTo(App\States\Submission\UnderReview::class);

    expect($submission->fresh()->status)
        ->toBeInstanceOf(App\States\Submission\UnderReview::class);
});

it('[SM-SUBMIT-12] resubmit dari DRAFT ditolak', function () {
    [$owner, $submission] = resubmitSliceSubmission(Draft::class);

    expect($owner->can('resubmit', $submission))->toBeFalse();
});
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run PEST `tests/Feature/Submission/ResubmitTest.php`.
Expected: FAIL — "Class App\Actions\FormSubmission\ResubmitFormSubmission not found",
dan `can('resubmit')` mengembalikan `false` bahkan untuk pemilik.

- [ ] **Step 3: Tambahkan method Policy**

Di `app/Policies/FormSubmissionPolicy.php`, tepat setelah `submit()`, tambahkan:

```php
    public function resubmit(User $user, FormSubmission $submission): bool
    {
        // BR-SM-04 — resubmit sama seperti submit: tindakan submitted_by mengikat
        // dirinya sendiri. ADR 0015 memasukkannya ke INVARIANT_ABILITIES, jadi
        // Gate::before tidak melewatinya dan method ini yang menentukan.
        if ($submission->submitted_by !== $user->id) {
            return false;
        }

        // BR-SM-03 — hanya NEEDS_REVISION yang bisa di-resubmit.
        return $submission->status instanceof NeedsRevision;
    }
```

Pastikan blok `use` memuat `App\States\Submission\NeedsRevision` — ia sudah ada di
berkas itu.

- [ ] **Step 4: Tulis Action**

```php
<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Resubmitted;
use App\States\Submission\SubmissionStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

class ResubmitFormSubmission
{
    use AsAction;

    /**
     * @throws AuthorizationException
     */
    public function handle(FormSubmission $submission, User $user): FormSubmission
    {
        // BR-SM-04 — dijaga Policy; dipanggil di sini supaya aturannya juga
        // berlaku di jalur yang tidak lewat HTTP.
        if (!$user->can('resubmit', $submission)) {
            throw new AuthorizationException(
                'Hanya lead researcher yang bisa resubmit pengajuan ini.'
            );
        }

        $status = $submission->status;

        if (!$status instanceof SubmissionStatus) {
            throw new RuntimeException(
                "FormSubmission {$submission->id} tidak punya status — tidak bisa di-resubmit."
            );
        }

        $status->transitionTo(Resubmitted::class);

        $submission->refresh();

        return $submission;
    }
}
```

- [ ] **Step 5: Jalankan test sampai lulus**

Run PEST `tests/Feature/Submission/ResubmitTest.php`. Expected: PASS, 4 test.

- [ ] **Step 6: Keluarkan ID dari pending**

Hapus `SM-SUBMIT-12` dan `SM-STATE-03` dari `pending.txt`; setel `# max: 98`.

- [ ] **Step 7: Gate penuh lalu commit**

Run QUALITY.

```bash
git add app tests docs/requirement-analysis/bdd
git commit -m "fix(submission): add missing resubmit policy and action for BR-SM-04"
```

---

## Task 6: Proposal PDF wajib sebelum submit

**Files:**
- Modify: `app/Actions/FormSubmission/SubmitFormSubmission.php`
- Create: `tests/Feature/Submission/SubmitProposalRequirementTest.php`
- Modify: `docs/requirement-analysis/bdd/pending.txt`

**Interfaces:**
- Modifies: `SubmitFormSubmission::handle()` — kini juga melempar `ValidationException`
- Consumes: `Form::formFields()`, `FormField::fieldType`, `FormSubmission::formFieldResponses()`

"Proposal PDF" tidak punya entitas sendiri. Secara konkret ia adalah **FormField wajib
bertipe `file`** yang belum punya response berisi. Itu cara `submitForm()` warisan
mengenalinya (`$field->fieldType->name === 'file'`), dan aturannya dipindahkan ke Action
supaya berlaku juga di jalur non-HTTP.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Feature/Submission/SubmitProposalRequirementTest.php
//
// BR-SM-02 — proposal PDF wajib ada sebelum submit.

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;
use Illuminate\Validation\ValidationException;

/** @return array{0: User, 1: FormSubmission, 2: FormField} */
function proposalSliceFixture(): array
{
    $owner = User::factory()->create();
    $form = Form::factory()->create();
    // field_types.name unik — firstOrCreate supaya fixture aman dipakai ulang.
    $fileType = FieldType::firstOrCreate(['name' => 'file']);
    $field = FormField::create([
        'form_id' => $form->id,
        'label' => 'Proposal PDF',
        'is_required' => true,
        'field_type_id' => $fileType->id,
        'order' => 1,
    ]);
    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $owner->id,
        'status' => Draft::class,
        'scheme_id' => null,
    ]);

    return [$owner, $submission, $field];
}

it('[SM-SUBMIT-07] submit tanpa proposal PDF ditolak', function () {
    [$owner, $submission, $field] = proposalSliceFixture();

    try {
        SubmitFormSubmission::run($submission, $owner);
        $this->fail('Submit seharusnya ditolak karena proposal PDF belum diunggah.');
    } catch (ValidationException $e) {
        // Error harus per field, dan menyebut label yang dilihat peneliti.
        expect($e->errors())->toHaveKey('field_'.$field->id)
            ->and($e->errors()['field_'.$field->id][0])->toContain('Proposal PDF');
    }

    expect($submission->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('[SM-SUBMIT-08] submit dengan proposal PDF lolos', function () {
    [$owner, $submission, $field] = proposalSliceFixture();
    $submission->formFieldResponses()->create([
        'form_field_id' => $field->id,
        'value' => 'form-uploads/proposal.pdf',
    ]);

    SubmitFormSubmission::run($submission->fresh(), $owner);

    expect($submission->fresh()->status)->toBeInstanceOf(Submitted::class);
});
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run PEST `tests/Feature/Submission/SubmitProposalRequirementTest.php`.
Expected: FAIL pada `[SM-SUBMIT-07]` — tidak ada `ValidationException` yang dilempar,
submission justru berpindah ke SUBMITTED. `[SM-SUBMIT-08]` sudah lolos.

- [ ] **Step 3: Tambahkan gerbang di Action**

Di `app/Actions/FormSubmission/SubmitFormSubmission.php`, tambahkan pemanggilan di
`handle()` **setelah** pengecekan `$status instanceof SubmissionStatus` dan **sebelum**
`$submission->update(['is_submitted' => true])`:

```php
        $this->guardRequiredDocuments($submission);
```

Lalu tambahkan method privat ini di kelas yang sama:

```php
    /**
     * BR-SM-02 — proposal PDF wajib ada sebelum submit. "Proposal PDF" tidak punya
     * entitas sendiri: ia FormField wajib bertipe `file` yang belum punya response
     * berisi.
     *
     * @throws ValidationException
     */
    private function guardRequiredDocuments(FormSubmission $submission): void
    {
        $requiredFileFields = $submission->form->formFields()
            ->where('is_required', true)
            ->whereHas('fieldType', function (Builder $query): void {
                $query->where('name', 'file');
            })
            ->get();

        if ($requiredFileFields->isEmpty()) {
            return;
        }

        $filled = $submission->formFieldResponses()
            ->whereIn('form_field_id', $requiredFileFields->pluck('id'))
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->pluck('form_field_id')
            ->all();

        $missing = $requiredFileFields->reject(
            fn (FormField $field): bool => in_array($field->id, $filled, true)
        );

        if ($missing->isEmpty()) {
            return;
        }

        // Error per field supaya wizard bisa menyorot baris yang salah.
        throw ValidationException::withMessages(
            $missing->mapWithKeys(fn (FormField $field): array => [
                'field_'.$field->id => "Field '{$field->label}' wajib diunggah sebelum submit.",
            ])->all()
        );
    }
```

Tambahkan ke blok `use` di berkas itu:

```php
use App\Models\FormField;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
```

- [ ] **Step 4: Jalankan test sampai lulus**

Run PEST `tests/Feature/Submission/SubmitProposalRequirementTest.php`. Expected: PASS, 2 test.

Jalankan juga `tests/Unit/Actions/SubmitFormSubmissionTest.php` dan
`tests/Feature/ProposalSubmittedEventTest.php` — keduanya memanggil Action ini dengan
Form tanpa field wajib bertipe `file`, jadi gerbangnya harus lolos diam-diam.

- [ ] **Step 5: Keluarkan ID dari pending**

Hapus `SM-SUBMIT-07` dan `SM-SUBMIT-08` dari `pending.txt`; setel `# max: 96`.

- [ ] **Step 6: Gate penuh lalu commit**

Run QUALITY.

```bash
git add app tests docs/requirement-analysis/bdd
git commit -m "feat(submission): require proposal document before submit for BR-SM-02"
```

---

## Task 7: Satu pengajuan aktif per periode per skema

**Files:**
- Modify: `app/Actions/FormSubmission/SubmitFormSubmission.php`
- Create: `tests/Feature/Submission/SubmitUniquenessTest.php`
- Modify: `docs/requirement-analysis/bdd/pending.txt`

**Interfaces:**
- Modifies: `SubmitFormSubmission::handle()` — melempar `ValidationException` saat sudah
  ada pengajuan aktif
- Produces: `SubmitFormSubmission::activeSubmissionClash(FormSubmission $submission): ?FormSubmission`
  — dipakai Task 8 untuk menyusun pesan penolakan

Index `one_active_submission_per_period_scheme` sudah menjaga ini di lapis database.
Gerbang di Action **mencerminkan predikatnya persis** supaya penolakannya sampai ke
peneliti sebagai pesan validasi, bukan galat 500. Index tetap ada sebagai jaring
pengaman lomba-balap (Task 8).

Predikat index yang harus dicerminkan:

```sql
WHERE parent_submission_id IS NULL
  AND is_submitted = true
  AND status NOT IN ('rejected', 'withdrawn')
```

di-key pada `(submitted_by, submission_period_id, scheme_id)`. `NULL` pada `scheme_id`
maupun `submission_period_id` **tidak pernah** dianggap duplikat — itu semantik bawaan
unique index PostgreSQL, dan BR-SM-01 memang mengizinkannya.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Feature/Submission/SubmitUniquenessTest.php
//
// BR-SM-01 — satu submission AKTIF per (peneliti, period, scheme).
// Gerbang di Action mencerminkan predikat index
// one_active_submission_per_period_scheme.

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\States\Submission\Draft;
use App\States\Submission\Rejected;
use App\States\Submission\Submitted;
use Illuminate\Validation\ValidationException;

/** @return array{0: User, 1: SubmissionPeriod, 2: Scheme, 3: Scheme} */
function uniquenessSliceContext(): array
{
    return [
        User::factory()->create(),
        SubmissionPeriod::factory()->create(),
        Scheme::factory()->create(),
        Scheme::factory()->create(),
    ];
}

function uniquenessSliceSubmission(
    User $owner,
    SubmissionPeriod $period,
    ?Scheme $scheme,
    string $state,
    bool $isSubmitted
): FormSubmission {
    return FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'scheme_id' => $scheme?->id,
        'status' => $state,
        'is_submitted' => $isSubmitted,
    ]);
}

it('[SM-SUBMIT-01] submit pertama ke sebuah skema berhasil', function () {
    [$owner, $period, $internal] = uniquenessSliceContext();
    $draft = uniquenessSliceSubmission($owner, $period, $internal, Draft::class, false);

    SubmitFormSubmission::run($draft, $owner);

    expect($draft->fresh()->status)->toBeInstanceOf(Submitted::class);
});

it('[SM-SUBMIT-02] submit kedua ke skema yang sama ditolak', function () {
    [$owner, $period, $internal] = uniquenessSliceContext();
    uniquenessSliceSubmission($owner, $period, $internal, Submitted::class, true);
    $draft = uniquenessSliceSubmission($owner, $period, $internal, Draft::class, false);

    expect(fn () => SubmitFormSubmission::run($draft, $owner))
        ->toThrow(ValidationException::class);
    expect($draft->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('[SM-SUBMIT-03] dua draft ke skema berbeda boleh hidup bersamaan', function () {
    [$owner, $period, $internal, $eksternal] = uniquenessSliceContext();
    $a = uniquenessSliceSubmission($owner, $period, $internal, Draft::class, false);
    $b = uniquenessSliceSubmission($owner, $period, $eksternal, Draft::class, false);

    expect(FormSubmission::whereKey([$a->id, $b->id])->count())->toBe(2);
});

it('[SM-SUBMIT-04] submit ke skema lain tetap boleh', function () {
    [$owner, $period, $internal, $eksternal] = uniquenessSliceContext();
    uniquenessSliceSubmission($owner, $period, $internal, Submitted::class, true);
    $draft = uniquenessSliceSubmission($owner, $period, $eksternal, Draft::class, false);

    SubmitFormSubmission::run($draft, $owner);

    expect($draft->fresh()->status)->toBeInstanceOf(Submitted::class);
});

it('[SM-SUBMIT-05] submit ulang ke skema yang sama boleh setelah ditolak', function () {
    [$owner, $period, $internal] = uniquenessSliceContext();
    uniquenessSliceSubmission($owner, $period, $internal, Rejected::class, true);
    $draft = uniquenessSliceSubmission($owner, $period, $internal, Draft::class, false);

    SubmitFormSubmission::run($draft, $owner);

    expect($draft->fresh()->status)->toBeInstanceOf(Submitted::class);
});

it('[SM-SUBMIT-06] pengajuan tanpa skema tidak saling menghalangi', function () {
    [$owner, $period] = uniquenessSliceContext();
    uniquenessSliceSubmission($owner, $period, null, Submitted::class, true);
    $draft = uniquenessSliceSubmission($owner, $period, null, Draft::class, false);

    SubmitFormSubmission::run($draft, $owner);

    expect($draft->fresh()->status)->toBeInstanceOf(Submitted::class);
});
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run PEST `tests/Feature/Submission/SubmitUniquenessTest.php`.
Expected: FAIL pada `[SM-SUBMIT-02]` — tidak ada `ValidationException`; yang muncul
justru `QueryException` dari index database, atau submit malah lolos. Lima sisanya hijau.

- [ ] **Step 3: Tambahkan pencari bentrokan dan gerbangnya**

Di `SubmitFormSubmission`, panggil di `handle()` tepat **setelah**
`$this->guardRequiredDocuments($submission);`:

```php
        $this->guardActiveSubmissionUniqueness($submission);
```

Lalu tambahkan kedua method ini:

```php
    /**
     * BR-SM-01 — pengajuan aktif yang menghalangi, kalau ada. Cermin persis predikat
     * index `one_active_submission_per_period_scheme`.
     *
     * `scheme_id` atau `submission_period_id` yang NULL tidak pernah dianggap
     * duplikat — semantik bawaan unique index PostgreSQL, dan BR-SM-01 memang
     * mengizinkan dua pengajuan tanpa skema hidup bersamaan.
     */
    public function activeSubmissionClash(FormSubmission $submission): ?FormSubmission
    {
        if ($submission->scheme_id === null || $submission->submission_period_id === null) {
            return null;
        }

        return FormSubmission::query()
            ->whereNull('parent_submission_id')
            ->where('is_submitted', true)
            ->whereNotIn('status', [Rejected::$name, Withdrawn::$name])
            ->where('submitted_by', $submission->submitted_by)
            ->where('submission_period_id', $submission->submission_period_id)
            ->where('scheme_id', $submission->scheme_id)
            ->whereKeyNot($submission->getKey())
            ->first();
    }

    /**
     * @throws ValidationException
     */
    private function guardActiveSubmissionUniqueness(FormSubmission $submission): void
    {
        $clash = $this->activeSubmissionClash($submission);

        if ($clash === null) {
            return;
        }

        throw ValidationException::withMessages([
            'scheme_id' => 'Anda sudah punya pengajuan aktif pada skema ini.',
        ]);
    }
```

Tambahkan ke blok `use`:

```php
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;
```

Pesannya sengaja masih polos — Task 8 yang menjadikannya manusiawi.

- [ ] **Step 4: Jalankan test sampai lulus**

Run PEST `tests/Feature/Submission/SubmitUniquenessTest.php`. Expected: PASS, 6 test.

Jalankan juga `tests/Feature/SubmissionUniquenessTest.php` — keempat test di sana bekerja
di lapis database dan harus tetap hijau.

- [ ] **Step 5: Keluarkan ID dari pending**

Hapus `SM-SUBMIT-01` sampai `SM-SUBMIT-06` dari `pending.txt`; setel `# max: 90`.

- [ ] **Step 6: Gate penuh lalu commit**

Run QUALITY.

```bash
git add app tests docs/requirement-analysis/bdd
git commit -m "feat(submission): guard active submission uniqueness for BR-SM-01"
```

---

## Task 8: Penolakan yang manusiawi, termasuk saat index yang menangkap

**Files:**
- Modify: `app/Actions/FormSubmission/SubmitFormSubmission.php`
- Create: `tests/Feature/Submission/SubmitRejectionMessageTest.php`
- Modify: `docs/requirement-analysis/bdd/pending.txt`

**Interfaces:**
- Consumes: `SubmitFormSubmission::activeSubmissionClash()` dari Task 7

BR-SM-16 menuntut tiga hal yang mudah terlewat: penolakan **menyebut** pengajuan yang
menghalangi **beserta tautannya**, draft yang gagal **tetap bisa disunting**, dan
kalaupun yang menangkap adalah index database, pesannya **sama** — bukan galat mentah.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Feature/Submission/SubmitRejectionMessageTest.php
//
// BR-SM-16 — penolakan submit harus bisa ditindaklanjuti peneliti, dan draft
// yang gagal tidak boleh hilang atau terkunci.

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/** @return array{0: User, 1: FormSubmission, 2: FormSubmission} */
function rejectionSliceFixture(): array
{
    $owner = User::factory()->create();
    $period = SubmissionPeriod::factory()->create();
    $scheme = Scheme::factory()->create();

    $aktif = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'scheme_id' => $scheme->id,
        'status' => Submitted::class,
        'is_submitted' => true,
    ]);
    $draft = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'scheme_id' => $scheme->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);

    return [$owner, $aktif, $draft];
}

it('[SM-SUBMIT-15] penolakan menyebut pengajuan aktif yang sudah ada', function () {
    [$owner, $aktif, $draft] = rejectionSliceFixture();

    try {
        SubmitFormSubmission::run($draft, $owner);
        $this->fail('Submit seharusnya ditolak karena sudah ada pengajuan aktif.');
    } catch (ValidationException $e) {
        $pesan = implode(' ', $e->errors()['scheme_id']);

        expect($pesan)->toContain((string) $aktif->id)
            ->and($pesan)->toContain(route('user.submissions.show', $aktif));
    }
});

it('[SM-SUBMIT-16] draft yang gagal disubmit tetap bisa disunting', function () {
    [$owner, , $draft] = rejectionSliceFixture();

    expect(fn () => SubmitFormSubmission::run($draft, $owner))
        ->toThrow(ValidationException::class);

    $segar = $draft->fresh();

    expect($segar->status)->toBeInstanceOf(Draft::class)
        ->and($segar->is_submitted)->toBeFalse()
        ->and($owner->can('update', $segar))->toBeTrue();
});

it('[SM-SUBMIT-17] submit kedua ditolak dengan pesan yang sama, bukan galat mentah', function () {
    $owner = User::factory()->create();
    $period = SubmissionPeriod::factory()->create();
    $scheme = Scheme::factory()->create();

    // Dua DRAFT ke period+scheme yang sama. Status disetel saat pembuatan —
    // bukan lewat update(['status' => ...]), yang dilarang.
    $pertama = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'scheme_id' => $scheme->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);
    $kedua = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'scheme_id' => $scheme->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);

    SubmitFormSubmission::run($pertama, $owner);
    expect($pertama->fresh()->status)->toBeInstanceOf(Submitted::class);

    $tertangkap = null;

    try {
        SubmitFormSubmission::run($kedua->fresh(), $owner);
    } catch (Throwable $e) {
        $tertangkap = $e;
    }

    // Inti BR-SM-16: yang sampai ke peneliti adalah pesan validasi yang bisa
    // ditindaklanjuti — BUKAN QueryException dari index.
    expect($tertangkap)->toBeInstanceOf(ValidationException::class)
        ->and($tertangkap)->not->toBeInstanceOf(QueryException::class)
        ->and($tertangkap->errors())->toHaveKey('scheme_id');

    expect($kedua->fresh()->status)->toBeInstanceOf(Draft::class);
});
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run PEST `tests/Feature/Submission/SubmitRejectionMessageTest.php`.
Expected: FAIL pada `[SM-SUBMIT-15]` — pesan dari Task 7 masih polos, belum menyebut
id maupun tautan. `[SM-SUBMIT-16]` dan `[SM-SUBMIT-17]` sudah hijau sejak Task 7:
gerbangnya sudah melempar `ValidationException`, yang belum manusiawi hanyalah isinya.

- [ ] **Step 3: Jadikan pesannya bisa ditindaklanjuti**

Di `SubmitFormSubmission`, ganti isi `guardActiveSubmissionUniqueness()` dari Task 7:

```php
    /**
     * BR-SM-16 — penolakan harus bisa ditindaklanjuti: sebut pengajuan yang
     * menghalangi beserta tautannya, jangan sekadar "gagal".
     *
     * @throws ValidationException
     */
    private function guardActiveSubmissionUniqueness(FormSubmission $submission): void
    {
        $clash = $this->activeSubmissionClash($submission);

        if ($clash === null) {
            return;
        }

        throw $this->activeSubmissionRejection($clash);
    }

    private function activeSubmissionRejection(FormSubmission $clash): ValidationException
    {
        return ValidationException::withMessages([
            'scheme_id' => sprintf(
                'Anda sudah punya pengajuan aktif pada skema ini: #%d (%s). '
                .'Tarik atau selesaikan pengajuan itu dulu sebelum mengajukan yang baru.',
                $clash->id,
                route('user.submissions.show', $clash)
            ),
        ]);
    }
```

- [ ] **Step 4: Terjemahkan pelanggaran index jadi pesan yang sama**

**Blok ini pertahanan berlapis, dan sengaja tidak diuji langsung.** Pemeriksaan di Task 7
memakai predikat yang **identik** dengan predikat index, jadi dalam satu proses
pemeriksaan itu selalu menang lebih dulu — index tidak akan pernah kebagian menangkap.
Yang bisa membuatnya menyala hanyalah dua request sungguhan yang berjalan bersamaan, dan
itu tidak bisa direproduksi di dalam satu test.

Tetap ditulis karena tanpanya lomba-balap yang nyata memberi peneliti galat 500 — persis
yang dilarang BR-SM-16. Test `[SM-SUBMIT-17]` membuktikan kontrak yang teramati secara
berurutan; blok ini menjaga sisi konkurennya.

Masih di `handle()`, bungkus tulisan yang menyentuh index. Ganti:

```php
        $submission->update([
            'is_submitted' => true,
        ]);
```

menjadi:

```php
        // BR-SM-16 — index one_active_submission_per_period_scheme adalah jaring
        // pengaman lomba-balap: dua submit bersamaan lolos pemeriksaan di atas,
        // lalu salah satunya dibentur di sini. Peneliti tidak boleh melihat galat
        // database mentah, jadi pelanggarannya diterjemahkan jadi pesan yang sama.
        try {
            $submission->update([
                'is_submitted' => true,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() !== self::UNIQUE_VIOLATION) {
                throw $e;
            }

            $clash = $this->activeSubmissionClash($submission);

            throw $clash === null
                ? $e
                : $this->activeSubmissionRejection($clash);
        }
```

Tambahkan konstanta di kelas yang sama:

```php
    /** SQLSTATE PostgreSQL untuk pelanggaran unique constraint. */
    private const UNIQUE_VIOLATION = '23505';
```

dan ke blok `use`:

```php
use Illuminate\Database\QueryException;
```

- [ ] **Step 5: Jalankan test sampai lulus**

Run PEST `tests/Feature/Submission/SubmitRejectionMessageTest.php`. Expected: PASS, 3 test.

Kalau ada yang gagal dengan `QueryException` bocor keluar, periksa `$e->getCode()` —
pada driver PDO PostgreSQL nilainya string `'23505'`, bukan integer. Jangan melonggarkan
perbandingannya jadi `==`; perbaiki tipenya.

- [ ] **Step 6: Keluarkan ID dari pending**

Hapus `SM-SUBMIT-15`, `SM-SUBMIT-16`, `SM-SUBMIT-17` dari `pending.txt`; setel `# max: 87`.

- [ ] **Step 7: Verifikasi akhir irisan**

```bash
grep -vcE '^\s*(#|$)' docs/requirement-analysis/bdd/pending.txt    # harus 87
grep -vcE '^\s*(#|$)' docs/requirement-analysis/bdd/uncovered.txt  # harus 59, tak berubah
grep -E '^SM-(ACCESS|SUBMIT)-' docs/requirement-analysis/bdd/pending.txt  # harus kosong
grep -E '^SM-STATE-' docs/requirement-analysis/bdd/pending.txt     # hanya SM-STATE-05
```

Baris terakhir menyisakan tepat satu ID: `SM-STATE-05`, yang sengaja ditunda (spec §6).

- [ ] **Step 8: Tinjau kosakata langkah**

Bandingkan frasa yang benar-benar dipakai dengan tabel *Kosakata langkah* di
`docs/requirement-analysis/bdd/README.md`:

```bash
find docs/requirement-analysis/bdd -name "*.feature" -exec cat {} + \
  | grep -E "^\s+(Given|When|Then|And)" \
  | sed -E 's/"[^"]*"/"X"/g; s/\b[0-9]+\b/N/g; s/^\s+//' \
  | sort | uniq -c | sort -rn | head -30
```

Frasa yang muncul ≥3 kali tapi belum ada di tabel → **tambahkan barisnya**, dalam commit
yang sama.

- [ ] **Step 9: Gate penuh lalu commit**

Run QUALITY. Jalankan juga `npm run quality`.

```bash
git add app tests docs/requirement-analysis/bdd
git commit -m "feat(submission): make active submission rejection actionable for BR-SM-16"
```

---

## Self-Review

Dijalankan setelah plan selesai ditulis, sebelum diserahkan.

**Cakupan spec.** Ketujuh baris tabel §5 spec punya task: akses (2), state machine
(1, 3, 5), siapa yang submit (4), revisi (5), proposal PDF (6), keunikan (7), penolakan
manusiawi (8). `[SM-STATE-05]` sengaja tidak punya task — spec §6 menyatakan alasannya.

**Placeholder.** Nol. Tidak ada "TBD", "serupa dengan Task N", atau langkah yang
menjelaskan *apa* tanpa menunjukkan *bagaimana*.

**Konsistensi tipe.** `activeSubmissionClash()` didefinisikan Task 7 dan dipakai Task 8
dengan nama dan tipe kembalian yang sama (`?FormSubmission`).
`guardRequiredDocuments()` (Task 6) dan `guardActiveSubmissionUniqueness()` (Task 7,
diganti isinya di Task 8) dipanggil dari `handle()` dengan urutan yang dinyatakan
eksplisit. `ResubmitFormSubmission::run()` (Task 5) dipakai hanya di dalam Task 5.

**Aritmetika ledger.** 114 → 112 → 106 → 105 → 100 → 98 → 96 → 90 → 87. Jumlah ID yang
dikeluarkan: 2+6+1+5+2+2+6+3 = **27**. 114 − 27 = 87. Cocok.

**Nama fungsi global.** Kelima berkas test baru memakai prefiks yang belum terpakai:
`accessSlice*`, `submitSlice*`, `resubmitSlice*`, `proposalSlice*`, `uniquenessSlice*`,
`rejectionSlice*`. Tidak satu pun bertabrakan dengan daftar di Global Constraints.
