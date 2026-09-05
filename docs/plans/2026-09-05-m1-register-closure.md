# M1 — Menutup Register Open Questions: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengosongkan bagian *Terbuka* di `open-questions.md` — menutup #25, #26, #27, #28
dengan kode, dan memindahkan #9, #10, #11, #12, #24 yang sudah tuntas ke *Terselesaikan*.
Ledger `pending` 87 → 77.

**Architecture:** Delapan task berurutan. Dua Action baru (`ResolveWizardSubmission`,
`StartSubmission`) dan satu guard (`RequiredFieldGuard`) mendarat lebih dulu; setelah itu
`UserFormController` menyusut jadi orkestrasi tipis yang mendelegasikan keputusan submit
ke `SubmitFormSubmission`. Cabang `needs_review` dihapus karena ia melempar
`TransitionNotFound` — bukan perilaku yang hilang, melainkan cacat yang dicabut.

**Tech Stack:** Laravel 13 · PHP 8.4 · PostgreSQL · Pest · Vue 3 + Inertia · Vitest ·
`spatie/laravel-model-states` · `lorisleiva/laravel-actions` · `spatie/laravel-permission`

**Spec:** [`docs/design/bdd-coverage/m1-register-closure.md`](../design/bdd-coverage/m1-register-closure.md)
— plan ini berargumen dari spec itu; keduanya dibaca bersama.

## Global Constraints

- **Setiap task berakhir dengan `composer run quality` hijau sebelum commit.** Pint +
  PHPStan level 5 + PHPStan level 9 (`phpstan-strict.neon`) + seluruh Pest.
  Task yang menyentuh frontend juga wajib `npm run quality` hijau.
- **Commit memakai Conventional Commits**, pesan singkat dan deskriptif, **tanpa trailer
  co-author**.
- **Dilarang menambah dependency.**
- **Dilarang mengubah `docs/requirement-analysis/ddd/`.** Kalau implementasi mengungkap
  aturan yang salah, hasilnya entri di `open-questions.md` — bukan sunting sepihak.
- **Berkas `.feature` hanya boleh DITAMBAHI tiga skenario** yang disebut spec §9:
  `SM-SUBMIT-18`, `SM-SUBMIT-19`, `SM-WIZARD-12`. Skenario yang sudah ada **tidak boleh
  diubah satu kata pun** — termasuk `[SM-STATE-04]` yang menuntut `403`.
- **Status hanya berubah lewat `transitionTo()`**, tidak pernah `update(['status' => …])`.
- **Business logic tidak di Controller** — Controller hanya validasi → resolve → Action → response.
- **Dilarang menyiasati PHPStan** — tidak ada `@phpstan-ignore`, entri baseline,
  `assert()`, inline `@var`, atau cast untuk menghilangkan error.
- **Dilarang percabangan khusus testing di production code** (`app()->environment('testing')`).
- **Guard clause, bukan `if` bersarang.** Satu Action = satu use case.
- **Gaya negasi `!$x`, bukan `! $x`** — `pint.json` menyetel
  `not_operator_with_successor_space=false`. Jalankan Pint sebelum commit.
- **Sebut kode BR di komentar** saat mengimplementasikan aturan.
- **Sebut ID skenario di deskripsi test** (`it('[FE-TEMPORAL-01] ...')`).
- **Tidak ada `any` di TypeScript, tidak ada `console.log`.**

### Aturan ledger yang ditegakkan mesin

`tests/Unit/Bdd/BddCoverageTest.php` menegakkan sembilan assertion. Tiga yang mengikat
plan ini:

- **Assertion 4** — setiap skenario wajib punya test, kecuali terdaftar di `pending.txt`.
- **Assertion 5** — `pending.txt` **tidak boleh** memuat skenario yang sudah punya test.
- **Assertion 8** — kedua daftar tidak boleh melewati plafon `# max:`-nya.

Konsekuensi langsung: **tiga skenario baru tidak pernah masuk `pending.txt`**, karena
ditulis dan dites di task yang sama. Menambahkannya lalu menghapusnya di task yang sama
justru melanggar assertion 5 di tengah jalan.

### Nama fungsi global Pest yang SUDAH terpakai

Pest memuat seluruh berkas test, jadi fungsi global bertabrakan lintas berkas dan
menghasilkan fatal error. Nama berikut **tidak boleh** dipakai ulang:

```
actingFormAdmin actingResearcher bddIdsClaimedByTests bddNonBehavioral bddPending
bddRuleCodes bddScenarios bddUncovered budgetFixture budgetSuperAdmin
buildAccessibleFormPhaseDetail concreteModelClasses createActivePeriod
createFormWithAccess createSubmissionDate createUserWithOrganization draftPayload
enrolResearchMember invokeGetEvaluationDueDate invokeMigrationMethod lineItemAttributes
makeSubmissionReviewer plainSubmission proposalSliceFixture setUpBiodataForm
submissionFor submissionOwnedBy submissionWithStatus superAdmin writeLedger
```

Nama baru yang diperkenalkan plan ini: `wizardFixture`, `temporalField`.

### Menjalankan perintah

Host PHP 8.5 tidak bisa dipakai (`composer install` gagal di `phpoffice/phpspreadsheet`,
dan `pdo_pgsql` tidak ada). Container wajib. Image `simpas-php84:local` dan container
`simpas_dev_pg` sudah ada di mesin dev; kalau belum, bangun seperti di
[`2026-09-05-m1-http-slice.md`](2026-09-05-m1-http-slice.md) §Menjalankan perintah.

```bash
# PEST — satu berkas atau seluruh suite
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest <args>

# QUALITY — gate penuh backend
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  -e COMPOSER_HOME=/tmp/composer simpas-php84:local composer run quality
```

Frontend jalan di host apa adanya: `npm run quality`.

---

## File Structure

| Berkas | Tanggung jawab |
| ------ | -------------- |
| `app/Actions/FormSubmission/ResolveWizardSubmission.php` | **Create.** Pencarian murni: baris terbaru untuk (Form, peneliti, periode), **apa pun statusnya** |
| `app/Actions/FormSubmission/StartSubmission.php` | **Create.** Resolve-or-create idempoten; satu-satunya pembuat baris |
| `app/Support/RequiredFieldGuard.php` | **Create.** `assertComplete()` — field wajib lewat `isRequiredFor()` (BR-FE-11) |
| `app/Actions/FormSubmission/SubmitFormSubmission.php` | **Modify.** `guardRequiredDocuments()` diganti guard baru |
| `app/Actions/FormSubmission/ResubmitFormSubmission.php` | **Modify.** Tambah guard yang sama (#27) |
| `app/Http/Controllers/UserFormController.php` | **Modify.** `startSubmission()` baru; `saveDraft()` dan `submitForm()` memakai resolver dan mendelegasikan |
| `routes/web.php` | **Modify.** Satu route baru `user.submissions.store` |
| `resources/js/Pages/User/FormPhase.vue` | **Modify.** `watch(currentForm)` memanggil endpoint langkah 1 |
| `tests/Unit/RequiredFieldGuardTest.php` | **Create.** `FE-TEMPORAL-01..08` |
| `tests/Unit/FormFieldTest.php` | **Modify.** Empat test yang ada diikat ke ID skenario |
| `tests/Feature/Submission/StartSubmissionTest.php` | **Create.** `SM-WIZARD-01`, `SM-WIZARD-12` |
| `tests/Feature/Submission/WizardResolutionHttpTest.php` | **Create.** `409` vs `403`, `FE-TEMPORAL-09`, `SM-SUBMIT-18` |
| `tests/Feature/Submission/ResubmitTest.php` | **Modify.** Tambah `SM-SUBMIT-19` |
| `tests/frontend/pages/formPhaseStart.spec.ts` | **Create.** Endpoint langkah 1 dipanggil sekali per Form |
| `docs/adr/0016-submit-never-approves.md` | **Create.** |
| `docs/requirement-analysis/bdd/core/submission/submit.feature` | **Modify.** `SM-SUBMIT-18`, `SM-SUBMIT-19` |
| `docs/requirement-analysis/bdd/core/submission/wizard.feature` | **Modify.** `SM-WIZARD-12` |
| `docs/requirement-analysis/bdd/pending.txt` | **Modify.** Task 1, 3, 6 |
| `docs/requirement-analysis/bdd/open-questions.md` | **Modify.** Task 8 |

`uncovered.txt` **tidak disentuh sama sekali** — tidak ada BR baru.

### Keputusan desain yang mengikat seluruh plan

**Resolver mengembalikan baris terbaru apa pun statusnya, bukan hanya yang bisa disunting.**

Ini berbeda dari kalimat spec §3.4 dan sengaja. Kalau resolver hanya mencocokkan
`DRAFT`/`NEEDS_REVISION`, maka baris terminal menjadi **tidak ditemukan**, dan
`saveDraft()` akan menjawab `409`. Padahal `[SM-STATE-04]` mengunci `403` untuk status
terminal — skenario yang sudah hijau dan tidak boleh dilunakkan.

Karena itu pembagiannya:

| Pertanyaan | Dijawab oleh |
| ---------- | ------------ |
| Baris mana yang sedang dibicarakan? | `ResolveWizardSubmission` — terbaru untuk (form, peneliti, periode) |
| Boleh disunting tidak? | `FormSubmissionPolicy::update()` — sudah ada, sudah benar |
| Baris lama dipakai ulang atau bikin baru? | `StartSubmission`, dengan menanyakan Policy yang sama |

Hasilnya: `409` hanya untuk "belum ada baris sama sekali", `403` untuk "ada tapi
terkunci", dan aturan kelayakan sunting tetap tertulis **satu kali** di Policy.

### Aritmetika ledger — WAJIB berurutan

Kondisi awal: `pending` 87/87, `uncovered` 59/59.

| Task | ID yang keluar dari `pending` | `pending` |
| ---- | ----------------------------- | --------- |
| 1 | `SM-WIZARD-01` | 87 → **86** |
| 3 | `FE-TEMPORAL-01..08` | 86 → **78** |
| 6 | `FE-TEMPORAL-09` | 78 → **77** |

Task 2, 4, 5, 7, 8 tidak menyentuh ledger. Urutan ini berbeda dari tabel proyeksi di
spec §10 (yang mendaftar guard lebih dulu); titik akhirnya sama, **77**, dan urutan di
sinilah yang otoritatif untuk eksekusi.

---

## Task 1: Dua Action — resolver dan pembuat baris

**Files:**
- Create: `app/Actions/FormSubmission/ResolveWizardSubmission.php`
- Create: `app/Actions/FormSubmission/StartSubmission.php`
- Create: `tests/Feature/Submission/StartSubmissionTest.php`
- Modify: `docs/requirement-analysis/bdd/core/submission/wizard.feature`
- Modify: `docs/requirement-analysis/bdd/pending.txt`

**Interfaces:**
- Consumes: `FormSubmissionPolicy::update(User, FormSubmission): bool` — sudah ada
- Produces:
  - `ResolveWizardSubmission::run(int $formId, int $submissionPeriodId, User $user): ?FormSubmission`
  - `StartSubmission::run(int $formId, int $submissionPeriodId, FormPhaseDetail $formPhaseDetail, User $user): FormSubmission`

- [ ] **Step 1: Tambahkan skenario `SM-WIZARD-12`**

Di akhir `docs/requirement-analysis/bdd/core/submission/wizard.feature`, tambahkan:

```gherkin
  @BR-SM-01 @M1 @RESEARCHER-02-02 @happy
  Scenario: [SM-WIZARD-12] Pengajuan yang berakhir terminal tidak memblokir draft baru
    Given submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus REJECTED
    When "Budi" memulai pengajuan baru pada "Pengajuan Penelitian"
    Then submission DRAFT terbentuk pada langkah 1
    And "P-001" tetap REJECTED
```

Frasa `Then "P-001" tetap REJECTED` adalah bentuk parameter dari `Then status "<id>" tetap
<STATUS>` yang sudah baku di `bdd/README.md` — tidak ada kosakata baru.

- [ ] **Step 2: Tulis test yang gagal**

```php
<?php

// tests/Feature/Submission/StartSubmissionTest.php
//
// ADR 0012 — baris form_submissions lahir di langkah 1 supaya created_at
// menjadi jangkar Temporal Field Binding (BR-FE-11).

use App\Actions\FormSubmission\StartSubmission;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\States\Submission\Draft;
use App\States\Submission\Rejected;

/** @return array{user: \App\Models\User, form: Form, period: \App\Models\SubmissionPeriod, detail: \App\Models\FormPhaseDetail} */
function wizardFixture(): array
{
    [$user, $organization] = actingResearcher();
    $form = Form::factory()->create();
    $accessControl = FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $organization->id,
    ]);
    $formPhase = FormPhase::create(['title' => 'Fase Uji', 'is_active' => true]);
    $built = buildAccessibleFormPhaseDetail($user, $organization, $accessControl, $formPhase);

    return [
        'user' => $user,
        'form' => $form,
        'period' => $built['period'],
        'detail' => $built['detail'],
    ];
}

it('[SM-WIZARD-01] langkah 1 membuat draft beserta jangkar temporalnya', function () {
    $fx = wizardFixture();

    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($submission->status)->toBeInstanceOf(Draft::class)
        ->and($submission->last_completed_step)->toBe(1)
        ->and($submission->is_submitted)->toBeFalse()
        ->and($submission->submission_period_id)->toBe($fx['period']->id)
        ->and($submission->form_phase_detail_id)->toBe($fx['detail']->id)
        // Jangkar temporal: created_at harus benar-benar tersimpan, bukan null.
        ->and($submission->created_at)->not->toBeNull();
});

it('memakai ulang draft yang sudah ada alih-alih membuat kembar', function () {
    $fx = wizardFixture();

    $first = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);
    $second = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($second->id)->toBe($first->id)
        ->and(FormSubmission::where('form_id', $fx['form']->id)->count())->toBe(1);
});

it('[SM-WIZARD-12] pengajuan yang berakhir terminal tidak memblokir draft baru', function () {
    $fx = wizardFixture();

    $rejected = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => Rejected::class,
        'is_submitted' => true,
    ]);

    $fresh = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($fresh->id)->not->toBe($rejected->id)
        ->and($fresh->status)->toBeInstanceOf(Draft::class)
        // Baris lama tidak disentuh — status terminal itu beku.
        ->and($rejected->fresh()->status)->toBeInstanceOf(Rejected::class);
});

it('memakai ulang pengajuan yang sedang direvisi, bukan membuat yang baru', function () {
    $fx = wizardFixture();

    $revising = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => \App\States\Submission\NeedsRevision::class,
        'is_submitted' => true,
    ]);

    $resolved = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    expect($resolved->id)->toBe($revising->id);
});
```

- [ ] **Step 3: Jalankan test untuk memastikan gagal**

Run PEST `tests/Feature/Submission/StartSubmissionTest.php`.
Expected: FAIL — `Class "App\Actions\FormSubmission\StartSubmission" not found`.

- [ ] **Step 4: Tulis `ResolveWizardSubmission`**

```php
<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveWizardSubmission
{
    use AsAction;

    /**
     * Baris pengajuan terbaru milik peneliti untuk sebuah Form di sebuah periode —
     * **apa pun statusnya**.
     *
     * Sengaja tidak menyaring status. Pemanggilnya yang memutuskan: `StartSubmission`
     * menanyakan Policy untuk tahu apakah baris ini masih boleh dipakai ulang, dan
     * `UserFormController` membedakan "belum ada baris" (409) dari "ada tapi terkunci"
     * (403) — pembedaan yang dituntut [SM-STATE-04].
     *
     * `parent_submission_id` NULL: laporan monev adalah child submission dan bukan
     * urusan wizard pengajuan (BR-MON-03).
     */
    public function handle(int $formId, int $submissionPeriodId, User $user): ?FormSubmission
    {
        return FormSubmission::query()
            ->whereNull('parent_submission_id')
            ->where('form_id', $formId)
            ->where('submitted_by', $user->id)
            ->where('submission_period_id', $submissionPeriodId)
            ->latest('id')
            ->first();
    }
}
```

- [ ] **Step 5: Tulis `StartSubmission`**

```php
<?php

namespace App\Actions\FormSubmission;

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class StartSubmission
{
    use AsAction;

    /**
     * Langkah 1 wizard (ADR 0012). Idempoten: frontend memanggilnya setiap kali
     * peneliti membuka sebuah Form, jadi ia harus memakai ulang draft yang sudah
     * ada alih-alih melahirkan kembar.
     */
    public function handle(
        int $formId,
        int $submissionPeriodId,
        FormPhaseDetail $formPhaseDetail,
        User $user
    ): FormSubmission {
        $existing = ResolveWizardSubmission::run($formId, $submissionPeriodId, $user);

        // BR-SM-05 lewat Policy — baris yang tidak lagi boleh disunting (terminal,
        // atau sedang direview) tidak dipakai ulang. Itulah yang membuat status
        // terminal melahirkan baris baru alih-alih membentur baris lama; kelayakan
        // suntingnya tetap tertulis satu kali saja, di FormSubmissionPolicy.
        if ($existing !== null && $user->can('update', $existing)) {
            return $existing;
        }

        // ADR 0012 — created_at baris ini menjadi jangkar Temporal Field Binding
        // (BR-FE-11), jadi ia harus lahir saat peneliti mulai mengisi.
        return FormSubmission::create([
            'form_id' => $formId,
            'submitted_by' => $user->id,
            'submission_period_id' => $submissionPeriodId,
            'form_phase_detail_id' => $formPhaseDetail->id,
            'is_submitted' => false,
            'last_completed_step' => 1,
        ]);
    }
}
```

- [ ] **Step 6: Jalankan test sampai lulus**

Run PEST `tests/Feature/Submission/StartSubmissionTest.php`.
Expected: PASS, 4 test.

- [ ] **Step 7: Keluarkan ID dari pending**

Hapus baris `SM-WIZARD-01` dari `pending.txt`; setel `# max: 86`.
**Jangan** menambahkan `SM-WIZARD-12` — ia sudah punya test, dan assertion 5 menolak
skenario terimplementasi yang masih terdaftar pending.

- [ ] **Step 8: Gate penuh lalu commit**

Run QUALITY.

```bash
git add app/Actions tests/Feature/Submission docs/requirement-analysis/bdd
git commit -m "feat(submission): add wizard draft resolver and step-1 action for BR-FE-11"
```

---

## Task 2: Endpoint langkah 1

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/UserFormController.php`
- Create: `tests/Feature/Submission/WizardResolutionHttpTest.php`

**Interfaces:**
- Consumes: `StartSubmission::run(int, int, FormPhaseDetail, User): FormSubmission` (Task 1)
- Produces: route bernama `user.submissions.store`, menerima
  `{form_id, submission_period_id, form_phase_id}`

Nama route memakai `user.submissions.store`, bukan `POST /submissions` polos seperti
ditulis ADR 0012. Alasannya seluruh rute peneliti hidup di grup
`prefix('user')->name('user.')`, dan memasang `/submissions` di root sekarang akan
menciptakan konvensi kedua yang harus dibongkar lagi saat wizard penuh dibangun.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Feature/Submission/WizardResolutionHttpTest.php
//
// Endpoint langkah 1 (ADR 0012) dan pembedaan 409/403 yang dituntut
// [SM-STATE-04].

use App\Models\FormSubmission;
use App\States\Submission\Draft;

it('membuat draft lewat endpoint langkah 1', function () {
    $fx = wizardFixture();

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.store'), [
            'form_id' => $fx['form']->id,
            'submission_period_id' => $fx['period']->id,
            'form_phase_id' => $fx['detail']->form_phase_id,
        ])
        ->assertRedirect();

    $submission = FormSubmission::where('form_id', $fx['form']->id)->sole();

    expect($submission->status)->toBeInstanceOf(Draft::class)
        ->and($submission->submitted_by)->toBe($fx['user']->id);
});

it('memanggil endpoint langkah 1 dua kali tidak melahirkan draft kembar', function () {
    $fx = wizardFixture();

    $payload = [
        'form_id' => $fx['form']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_id' => $fx['detail']->form_phase_id,
    ];

    $this->actingAs($fx['user'])->post(route('user.submissions.store'), $payload);
    $this->actingAs($fx['user'])->post(route('user.submissions.store'), $payload);

    expect(FormSubmission::where('form_id', $fx['form']->id)->count())->toBe(1);
});
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run PEST `tests/Feature/Submission/WizardResolutionHttpTest.php`.
Expected: FAIL — `Route [user.submissions.store] not defined`.

- [ ] **Step 3: Tambahkan route**

Di `routes/web.php`, di dalam grup
`Route::middleware(['auth', 'check_biodata', 'check_reviewer_status'])->prefix('user')->name('user.')`,
tepat **sebelum** `Route::get('/submissions', [SubmissionViewController::class, 'userIndex'])`:

```php
    // ADR 0012 langkah 1 — satu-satunya jalur yang boleh melahirkan baris pengajuan.
    Route::post('/submissions', [UserFormController::class, 'startSubmission'])
        ->name('submissions.store');
```

- [ ] **Step 4: Tambahkan method controller**

Di `app/Http/Controllers/UserFormController.php`, tepat **sebelum** `saveDraft()`:

```php
    public function startSubmission(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'form_id' => 'required|exists:forms,id',
            'submission_period_id' => 'required|exists:submission_periods,id',
            'form_phase_id' => 'required|exists:form_phases,id',
        ]);

        $form = Form::findOrFail($validated['form_id']);

        if (!$this->formAccessService->canAccessForm($user, $form)) {
            abort(403, 'Anda tidak memiliki akses ke formulir ini.');
        }

        $formPhaseDetail = $this->resolveFormPhaseDetail(
            (int) $validated['form_phase_id'],
            (int) $validated['form_id'],
            $user
        );

        StartSubmission::run(
            (int) $validated['form_id'],
            (int) $validated['submission_period_id'],
            $formPhaseDetail,
            $user
        );

        return redirect()->back();
    }
```

Tambahkan `use App\Actions\FormSubmission\StartSubmission;` dan
`use Illuminate\Http\RedirectResponse;` di blok `use` berkas itu kalau belum ada.

- [ ] **Step 5: Jalankan test sampai lulus**

Run PEST `tests/Feature/Submission/WizardResolutionHttpTest.php`.
Expected: PASS, 2 test.

- [ ] **Step 6: Gate penuh lalu commit**

Run QUALITY.

```bash
git add routes app/Http/Controllers tests/Feature/Submission
git commit -m "feat(submission): add wizard step-1 endpoint per ADR 0012"
```

---

## Task 3: `RequiredFieldGuard` — Temporal Field Binding yang akhirnya menyala

**Files:**
- Create: `app/Support/RequiredFieldGuard.php`
- Create: `tests/Unit/RequiredFieldGuardTest.php`
- Modify: `tests/Unit/FormFieldTest.php`
- Modify: `docs/requirement-analysis/bdd/pending.txt`

**Interfaces:**
- Consumes: `FormField::isRequiredFor(FormSubmission): bool` — sudah ada di
  `app/Models/FormField.php:63`, punya unit test, **nol pemanggil produksi** (register #26)
- Produces: `RequiredFieldGuard::assertComplete(FormSubmission $submission): void`,
  melempar `ValidationException` berkunci `field_{id}`

`FormField` memakai `SoftDeletes`, jadi relasi `formFields()` sudah menyaring field yang
dihapus — itulah yang membuat `FE-TEMPORAL-08` lulus tanpa kode tambahan. **Jangan**
memanggil `withTrashed()`.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Unit/RequiredFieldGuardTest.php
//
// BR-FE-11 — Temporal Field Binding. Sebuah field hanya diwajibkan untuk sebuah
// submission kalau ia sudah ada DAN sudah wajib sebelum submission itu dibuat.

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\States\Submission\Draft;
use App\Support\RequiredFieldGuard;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Field dengan waktu lahir dan waktu-jadi-wajib yang dikendalikan penuh.
 * `booted()` di FormField menyetel required_since otomatis, jadi nilainya
 * ditimpa setelah pembuatan supaya skenario bisa menentukan tanggalnya.
 */
function temporalField(Form $form, string $label, bool $required, string $createdAt, ?string $requiredSince): FormField
{
    $type = FieldType::firstOrCreate(['name' => 'text']);

    $field = FormField::create([
        'form_id' => $form->id,
        'label' => $label,
        'is_required' => $required,
        'field_type_id' => $type->id,
        'order' => 1,
    ]);

    $field->forceFill([
        'created_at' => Carbon::parse($createdAt),
        'required_since' => $requiredSince === null ? null : Carbon::parse($requiredSince),
    ])->save();

    return $field->fresh();
}

function submissionCreatedAt(Form $form, string $createdAt): FormSubmission
{
    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'status' => Draft::class,
        'scheme_id' => null,
    ]);

    $submission->forceFill(['created_at' => Carbon::parse($createdAt)])->save();

    return $submission->fresh();
}

it('[FE-TEMPORAL-01] field wajib yang lahir sebelum submission tetap diwajibkan', function () {
    $form = Form::factory()->create();
    $field = temporalField($form, 'Abstrak', true, '2026-01-10 00:00', '2026-01-10 00:00');
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    expect(fn () => RequiredFieldGuard::assertComplete($submission))
        ->toThrow(ValidationException::class);

    try {
        RequiredFieldGuard::assertComplete($submission);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('field_'.$field->id)
            ->and($e->errors()['field_'.$field->id][0])->toContain('Abstrak');
    }
});

it('[FE-TEMPORAL-02] field yang lahir setelah submission tidak diwajibkan', function () {
    $form = Form::factory()->create();
    temporalField($form, 'Roadmap', true, '2026-03-01 00:00', '2026-03-01 00:00');
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    RequiredFieldGuard::assertComplete($submission);
})->throwsNoExceptions();

it('[FE-TEMPORAL-03] field yang baru jadi wajib setelah submission tidak diwajibkan', function () {
    $form = Form::factory()->create();
    temporalField($form, 'Mitra', true, '2026-01-10 00:00', '2026-03-01 00:00');
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    RequiredFieldGuard::assertComplete($submission);
})->throwsNoExceptions();

it('[FE-TEMPORAL-04] field yang jadi wajib tepat sebelum submission diwajibkan', function () {
    $form = Form::factory()->create();
    temporalField($form, 'Mitra', true, '2026-01-10 00:00', '2026-01-20 00:00');
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    expect(fn () => RequiredFieldGuard::assertComplete($submission))
        ->toThrow(ValidationException::class);
});

it('[FE-TEMPORAL-05] required_since kosong berarti mengikuti is_required apa adanya', function () {
    $form = Form::factory()->create();
    temporalField($form, 'Judul', true, '2026-01-10 00:00', null);
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    expect(fn () => RequiredFieldGuard::assertComplete($submission))
        ->toThrow(ValidationException::class);
});

it('[FE-TEMPORAL-06] field opsional tidak pernah diwajibkan', function () {
    $form = Form::factory()->create();
    temporalField($form, 'Catatan', false, '2026-01-10 00:00', null);
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    RequiredFieldGuard::assertComplete($submission);
})->throwsNoExceptions();

it('[FE-TEMPORAL-07] field yang lahir tepat bersamaan dengan submission diwajibkan', function () {
    $form = Form::factory()->create();
    temporalField($form, 'Luaran', true, '2026-02-01 09:00', '2026-02-01 09:00');
    $submission = submissionCreatedAt($form, '2026-02-01 09:00');

    expect(fn () => RequiredFieldGuard::assertComplete($submission))
        ->toThrow(ValidationException::class);
});

it('[FE-TEMPORAL-08] field yang sudah di-soft-delete tidak diwajibkan', function () {
    $form = Form::factory()->create();
    $field = temporalField($form, 'Lampiran Lama', true, '2026-01-10 00:00', '2026-01-10 00:00');
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    $field->delete();

    RequiredFieldGuard::assertComplete($submission);
})->throwsNoExceptions();

it('field wajib yang sudah terisi tidak memblokir', function () {
    $form = Form::factory()->create();
    $field = temporalField($form, 'Abstrak', true, '2026-01-10 00:00', '2026-01-10 00:00');
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    $submission->formFieldResponses()->create([
        'form_field_id' => $field->id,
        'value' => 'Isi abstrak',
    ]);

    RequiredFieldGuard::assertComplete($submission);
})->throwsNoExceptions();

it('response kosong dihitung belum terisi', function () {
    $form = Form::factory()->create();
    $field = temporalField($form, 'Abstrak', true, '2026-01-10 00:00', '2026-01-10 00:00');
    $submission = submissionCreatedAt($form, '2026-02-01 00:00');

    $submission->formFieldResponses()->create([
        'form_field_id' => $field->id,
        'value' => '',
    ]);

    expect(fn () => RequiredFieldGuard::assertComplete($submission))
        ->toThrow(ValidationException::class);
});
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run PEST `tests/Unit/RequiredFieldGuardTest.php`.
Expected: FAIL — `Class "App\Support\RequiredFieldGuard" not found`.

- [ ] **Step 3: Tulis guard-nya**

```php
<?php

namespace App\Support;

use App\Models\FormField;
use App\Models\FormSubmission;
use Illuminate\Validation\ValidationException;

/**
 * BR-SM-02 dan BR-FE-11 — field wajib harus terisi sebelum submit, tapi hanya
 * field yang sudah wajib **sebelum** submission dibuat.
 *
 * `final class` statis mengikuti preseden `OptimisticLock`: tidak ada state
 * instance yang dipegang, dan sebuah guard bukan use case sehingga ia tidak
 * berhak jadi Action.
 */
final class RequiredFieldGuard
{
    /**
     * @throws ValidationException
     */
    public static function assertComplete(FormSubmission $submission): void
    {
        $form = $submission->form;

        if ($form === null) {
            return;
        }

        // FormField memakai SoftDeletes, jadi relasi ini sudah menyaring field
        // yang dihapus — itulah yang memenuhi [FE-TEMPORAL-08]. Menambahkan
        // withTrashed() di sini akan mematahkannya.
        $required = $form->formFields()->get()->filter(
            fn (FormField $field): bool => $field->isRequiredFor($submission)
        );

        if ($required->isEmpty()) {
            return;
        }

        $filled = $submission->formFieldResponses()
            ->whereIn('form_field_id', $required->pluck('id'))
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->pluck('form_field_id')
            ->all();

        $missing = $required->reject(
            fn (FormField $field): bool => in_array($field->id, $filled, true)
        );

        if ($missing->isEmpty()) {
            return;
        }

        // Error per field supaya wizard bisa menyorot baris yang salah —
        // dituntut [SM-SUBMIT-07] dan [SM-WIZARD-06].
        throw ValidationException::withMessages(
            $missing->mapWithKeys(fn (FormField $field): array => [
                'field_'.$field->id => "Field '{$field->label}' wajib diisi sebelum submit.",
            ])->all()
        );
    }
}
```

- [ ] **Step 4: Jalankan test sampai lulus**

Run PEST `tests/Unit/RequiredFieldGuardTest.php`.
Expected: PASS, 10 test.

- [ ] **Step 5: Ikat empat test lama ke ID skenario**

Di `tests/Unit/FormFieldTest.php`, ubah **hanya deskripsinya** — badan test tidak
disentuh, karena keempatnya sudah membuktikan hal yang benar:

| Deskripsi sekarang | Jadi |
| ------------------ | ---- |
| `returns false if field was created after submission` | `[FE-TEMPORAL-02] isRequiredFor menolak field yang lahir setelah submission` |
| `returns false if field became required after submission` | `[FE-TEMPORAL-03] isRequiredFor menolak field yang baru jadi wajib` |

Dua test lain di berkas itu (`required_since` kosong dan field opsional) diberi prefiks
`[FE-TEMPORAL-05]` dan `[FE-TEMPORAL-06]` dengan cara yang sama. Assertion 4 hanya
menuntut ID disebut di suatu test — dua berkas boleh sama-sama menyebutnya.

- [ ] **Step 6: Keluarkan ID dari pending**

Hapus `FE-TEMPORAL-01` s/d `FE-TEMPORAL-08` dari `pending.txt`; setel `# max: 78`.
`FE-TEMPORAL-09` **tetap tinggal** — ia dibuktikan di Task 6.

- [ ] **Step 7: Gate penuh lalu commit**

Run QUALITY.

```bash
git add app/Support tests/Unit docs/requirement-analysis/bdd
git commit -m "feat(form-engine): enforce temporal field binding with a required-field guard for BR-FE-11"
```

---

## Task 4: `SubmitFormSubmission` memakai guard baru

**Files:**
- Modify: `app/Actions/FormSubmission/SubmitFormSubmission.php`

**Interfaces:**
- Consumes: `RequiredFieldGuard::assertComplete(FormSubmission): void` (Task 3)
- Produces: perilaku submit yang memeriksa **semua** field wajib, bukan hanya file

- [ ] **Step 1: Jalankan test yang sudah ada sebagai baseline**

Run PEST `tests/Feature/Submission/SubmitProposalRequirementTest.php`.
Expected: PASS, 2 test — `[SM-SUBMIT-07]` dan `[SM-SUBMIT-08]`. Keduanya **harus tetap
hijau** setelah penggantian; itulah jaring pengaman task ini.

- [ ] **Step 2: Ganti guard lama dengan yang baru**

Di `app/Actions/FormSubmission/SubmitFormSubmission.php`:

1. Ganti baris `$this->guardRequiredDocuments($submission);` menjadi:

```php
        // BR-SM-02 + BR-FE-11 — seluruh field wajib, disaring Temporal Field Binding.
        RequiredFieldGuard::assertComplete($submission);
```

2. **Hapus seluruh method `guardRequiredDocuments()`** beserta docblock-nya.
3. Tambahkan `use App\Support\RequiredFieldGuard;` di blok `use`.
4. Hapus `use App\Models\FormField;` dan
   `use Illuminate\Contracts\Database\Eloquent\Builder;` **kalau tidak ada pemakai lain
   yang tersisa** di berkas itu — Pint dan PHPStan akan menandai import yang menganggur.

- [ ] **Step 3: Jalankan test sampai lulus**

Run PEST `tests/Feature/Submission/`.
Expected: PASS seluruhnya. `[SM-SUBMIT-07]` masih menuntut pesan yang memuat
`Proposal PDF` — guard baru memakai `$field->label`, jadi pesannya tetap memuat label itu.

- [ ] **Step 4: Gate penuh lalu commit**

Run QUALITY.

```bash
git add app/Actions
git commit -m "refactor(submission): widen submit gate to every required field for BR-FE-11"
```

---

## Task 5: Resubmit memakai guard yang sama

**Files:**
- Modify: `app/Actions/FormSubmission/ResubmitFormSubmission.php`
- Modify: `tests/Feature/Submission/ResubmitTest.php`
- Modify: `docs/requirement-analysis/bdd/core/submission/submit.feature`

**Interfaces:**
- Consumes: `RequiredFieldGuard::assertComplete(FormSubmission): void` (Task 3)
- Produces: BR-SM-02 berlaku juga pada transisi NEEDS_REVISION → RESUBMITTED

Jangkar temporalnya tetap `$submission->created_at` yang asli — guard membacanya dari
submission, bukan dari waktu resubmit. Konsekuensinya disengaja: revisi **tidak** menarik
field wajib baru ke dalam pengajuan yang sudah berjalan.

- [ ] **Step 1: Tambahkan skenario `SM-SUBMIT-19`**

Di akhir `docs/requirement-analysis/bdd/core/submission/submit.feature`:

```gherkin
  @BR-SM-02 @M1 @RESEARCHER-02-05 @denial
  Scenario: [SM-SUBMIT-19] Resubmit tanpa field wajib ditolak
    Given submission "P-001" milik "Budi" berstatus NEEDS_REVISION
    And "Budi" belum mengunggah proposal PDF
    When "Budi" resubmit "P-001"
    Then submit ditolak karena proposal PDF belum diunggah
```

- [ ] **Step 2: Tulis test yang gagal**

Tambahkan di akhir `tests/Feature/Submission/ResubmitTest.php`:

```php
it('[SM-SUBMIT-19] resubmit tanpa field wajib ditolak', function () {
    $owner = App\Models\User::factory()->create();
    $form = App\Models\Form::factory()->create();
    $fileType = App\Models\FieldType::firstOrCreate(['name' => 'file']);
    $field = App\Models\FormField::create([
        'form_id' => $form->id,
        'label' => 'Proposal PDF',
        'is_required' => true,
        'field_type_id' => $fileType->id,
        'order' => 1,
    ]);
    $submission = App\Models\FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $owner->id,
        'status' => App\States\Submission\NeedsRevision::class,
        'scheme_id' => null,
    ]);

    try {
        App\Actions\FormSubmission\ResubmitFormSubmission::run($submission, $owner);
        $this->fail('Resubmit seharusnya ditolak karena proposal PDF belum diunggah.');
    } catch (Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('field_'.$field->id);
    }

    // Statusnya tidak boleh bergerak saat gerbang menolak.
    expect($submission->fresh()->status)
        ->toBeInstanceOf(App\States\Submission\NeedsRevision::class);
});
```

- [ ] **Step 3: Jalankan test untuk memastikan gagal**

Run PEST `tests/Feature/Submission/ResubmitTest.php`.
Expected: FAIL — resubmit berhasil dan status berubah jadi RESUBMITTED, karena gerbangnya
belum ada.

- [ ] **Step 4: Tambahkan guard**

Di `app/Actions/FormSubmission/ResubmitFormSubmission.php`, tepat **sebelum**
`$status->transitionTo(Resubmitted::class);`:

```php
        // BR-SM-02 — gerbang dokumen berlaku juga di jalur resubmit. Jangkarnya
        // tetap created_at asli, jadi revisi tidak menarik field wajib baru ke
        // dalam pengajuan yang sudah berjalan (register #27).
        RequiredFieldGuard::assertComplete($submission);
```

Tambahkan `use App\Support\RequiredFieldGuard;` di blok `use`.

- [ ] **Step 5: Jalankan test sampai lulus**

Run PEST `tests/Feature/Submission/ResubmitTest.php`.
Expected: PASS seluruhnya, termasuk `[SM-SUBMIT-12]` dan `[SM-STATE-03]` yang sudah ada.
Kalau salah satunya jadi merah, fixture-nya kemungkinan tidak punya field wajib sama
sekali — guard mengabaikan Form tanpa field wajib, jadi test lama seharusnya tidak
terpengaruh.

- [ ] **Step 6: Gate penuh lalu commit**

Run QUALITY.

```bash
git add app/Actions tests/Feature/Submission docs/requirement-analysis/bdd
git commit -m "fix(submission): enforce required documents on resubmit for BR-SM-02"
```

---

## Task 6: Controller mendelegasikan, cabang rusak dicabut

**Files:**
- Modify: `app/Http/Controllers/UserFormController.php:372` (saveDraft) dan `:503` (submitForm)
- Modify: `tests/Feature/Submission/WizardResolutionHttpTest.php`
- Modify: `docs/requirement-analysis/bdd/core/submission/submit.feature`
- Modify: `docs/requirement-analysis/bdd/pending.txt`

**Interfaces:**
- Consumes: `ResolveWizardSubmission::run()` (Task 1),
  `SubmitFormSubmission::run(FormSubmission, User): FormSubmission` (Task 4)
- Produces: `saveDraft()` dan `submitForm()` yang tidak lagi membuat baris sendiri

Ini task terbesar. Tiga hal terjadi sekaligus karena ketiganya menyentuh baris yang sama:
delegasi (#25), resolver menggantikan `firstOrCreate` (#28), dan pencabutan cabang
`needs_review`.

**Cabang yang dicabut sudah terbukti rusak** — `transitionTo(Approved::class)` dari
`Draft` melempar `TransitionNotFound`, karena `SubmissionStatus::config()` hanya
mengizinkan `UnderReview → Approved`. Tidak ada perilaku yang hilang.

- [ ] **Step 1: Tambahkan skenario `SM-SUBMIT-18`**

Di `docs/requirement-analysis/bdd/core/submission/submit.feature`, setelah
`[SM-SUBMIT-19]`:

```gherkin
  @BR-SM-03 @M1 @RESEARCHER-02-10 @happy
  Scenario: [SM-SUBMIT-18] Fase yang tidak butuh review tetap mendarat di SUBMITTED
    Given submission "P-001" milik "Budi" berstatus DRAFT
    And fase pengajuan "P-001" tidak membutuhkan review
    When "Budi" submit "P-001"
    Then status "P-001" menjadi SUBMITTED
```

Frasa `And fase pengajuan "<id>" tidak membutuhkan review` adalah kosakata baru —
tambahkan barisnya ke tabel kosakata di `docs/requirement-analysis/bdd/README.md`
(tabel ketiga), sesuai aturan "kalau langkah yang kamu butuhkan tidak ada, tambahkan
barisnya di PR yang sama".

- [ ] **Step 2: Tulis test yang gagal**

Tambahkan di `tests/Feature/Submission/WizardResolutionHttpTest.php`:

```php
it('menolak simpan draft dengan 409 saat belum ada baris sama sekali', function () {
    $fx = wizardFixture();

    $this->actingAs($fx['user'])
        ->post(route('user.form-submission.save-draft'), draftPayload($fx['form'], $fx['period'], $fx['detail']->formPhase))
        ->assertStatus(409);
});

it('[FE-TEMPORAL-09] menyimpan draft tidak menjalankan validasi penuh', function () {
    $fx = wizardFixture();
    $type = App\Models\FieldType::firstOrCreate(['name' => 'file']);
    App\Models\FormField::create([
        'form_id' => $fx['form']->id,
        'label' => 'Proposal PDF',
        'is_required' => true,
        'field_type_id' => $type->id,
        'order' => 2,
    ]);

    App\Actions\FormSubmission\StartSubmission::run(
        $fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']
    );

    // Field wajib sengaja dibiarkan kosong. Penyimpanan langkah harus tetap lolos.
    $this->actingAs($fx['user'])
        ->post(route('user.form-submission.save-draft'), draftPayload($fx['form'], $fx['period'], $fx['detail']->formPhase))
        ->assertRedirect();

    $submission = App\Models\FormSubmission::where('form_id', $fx['form']->id)->sole();

    expect($submission->status)->toBeInstanceOf(App\States\Submission\Draft::class);
});

it('[SM-SUBMIT-18] fase yang tidak butuh review tetap mendarat di SUBMITTED', function () {
    $fx = wizardFixture();
    $fx['detail']->update(['needs_review' => false]);

    App\Actions\FormSubmission\StartSubmission::run(
        $fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']
    );

    $this->actingAs($fx['user'])
        ->post(route('user.form-submission.submit'), draftPayload($fx['form'], $fx['period'], $fx['detail']->formPhase))
        ->assertRedirect();

    $submission = App\Models\FormSubmission::where('form_id', $fx['form']->id)->sole();

    expect($submission->status)->toBeInstanceOf(App\States\Submission\Submitted::class);
});
```

- [ ] **Step 3: Jalankan test untuk memastikan gagal**

Run PEST `tests/Feature/Submission/WizardResolutionHttpTest.php`.
Expected: FAIL — `409` belum pernah dikembalikan, dan `[SM-SUBMIT-18]` gagal dengan
`TransitionNotFound` (persis cacat yang dicabut task ini).

- [ ] **Step 4: Ganti `firstOrCreate` di `saveDraft()`**

Di `app/Http/Controllers/UserFormController.php`, di dalam `DB::transaction` pada
`saveDraft()`, **ganti** blok `FormSubmission::firstOrCreate([...])` (baris 372) dan
gerbang di bawahnya menjadi:

```php
            $submission = ResolveWizardSubmission::run(
                (int) $validated['form_id'],
                (int) $validated['submission_period_id'],
                $user
            );

            // Belum ada baris sama sekali — peneliti melewati langkah 1.
            abort_if($submission === null, 409, 'Draft tidak ditemukan; mulai pengajuan dari langkah 1.');

            // BR-SM-05 — isi pengajuan terkunci pada status terminal. Resolver
            // sengaja tidak menyaring status supaya pembedaan 409/403 ini bisa
            // dibuat; [SM-STATE-04] menuntut 403, bukan 409.
            abort_unless($user->can('update', $submission), 403, 'Pengajuan ini sudah terkunci.');
```

Blok `$submission->update([...])` yang menyetel `form_phase_detail_id` dan
`submission_period_id` **dihapus** — `StartSubmission` sudah menyetelnya saat baris lahir.

- [ ] **Step 5: Tulis ulang `submitForm()`**

Ganti seluruh isi `submitForm()` **setelah** `$validated = $request->validate([...]);`
dan blok unggah berkas, menjadi:

```php
        $form = Form::findOrFail($validated['form_id']);

        if (!$this->formAccessService->canAccessForm($user, $form)) {
            abort(403, 'Anda tidak memiliki akses ke formulir ini.');
        }

        $submission = ResolveWizardSubmission::run(
            (int) $validated['form_id'],
            (int) $validated['submission_period_id'],
            $user
        );

        abort_if($submission === null, 409, 'Draft tidak ditemukan; mulai pengajuan dari langkah 1.');
        abort_unless($user->can('update', $submission), 403, 'Pengajuan ini sudah terkunci.');

        DB::transaction(function () use ($validated, $fileUploads, $submission): void {
            $this->persistResponses($submission, $validated['responses'], $fileUploads);
        });

        // BR-SM-01, BR-SM-02, BR-SM-04, BR-SM-16 — seluruh keputusan submit ada di
        // Action. Cabang needs_review yang dulu di sini dicabut: ia memanggil
        // transitionTo(Approved) dari DRAFT, transisi yang tidak diizinkan state
        // machine, sehingga selalu melempar TransitionNotFound (ADR 0016).
        SubmitFormSubmission::run($submission, $user);

        return redirect()->back()
            ->with('success', 'Formulir berhasil diserahkan.');
```

Yang **dihapus** dari method ini: seluruh loop validasi field wajib (baris 470–499),
blok `firstOrCreate` (baris 503), pencarian `FormPhaseDetail` kedua, cabang
`needs_review`, dan panggilan `$this->emailService->notifyAdminFormSubmission($submission)`.

Notifikasi tetap terkirim lewat event `ProposalSubmitted` yang di-dispatch Action, dengan
audiens `submissions.view-all` alih-alih role `Admin`/`Super Admin` — perpindahan yang
disengaja dan dicatat di spec §4.

- [ ] **Step 6: Ekstrak `persistResponses()`**

Blok penulisan response identik di `saveDraft()` dan `submitForm()`. Ekstrak jadi private
method (Extract Method) dan panggil dari keduanya:

```php
    /**
     * @param  array<int, array{form_field_id: int|string, value: mixed}>  $responses
     * @param  array<int|string, string>  $fileUploads
     */
    private function persistResponses(FormSubmission $submission, array $responses, array $fileUploads): void
    {
        $submission->formFieldResponses()->delete();

        foreach ($responses as $index => $responseData) {
            $value = $fileUploads[$index] ?? $responseData['value'];

            if (empty($value) && $value !== '0' && $value !== 0) {
                continue;
            }

            FormFieldResponse::create([
                'form_submission_id' => $submission->id,
                'form_field_id' => $responseData['form_field_id'],
                'value' => is_array($value) ? json_encode($value) : (string) $value,
            ]);
        }
    }
```

- [ ] **Step 7: Jalankan seluruh suite**

Run PEST (seluruh suite, tanpa argumen).
Expected: PASS seluruhnya. Yang wajib diperiksa khusus:

- `tests/Feature/Submission/SubmissionEditLockHttpTest.php` — `[SM-STATE-04]` **harus
  tetap 403**, bukan 409. Kalau ia jadi 409, resolver salah menyaring status.
- `tests/Feature/UserFormControllerFormPhaseDetailTest.php` — keempat test menyimpan
  draft. Sekarang mereka butuh baris yang sudah ada, jadi tambahkan pemanggilan
  `StartSubmission::run(...)` di fixture-nya sebelum `post(save-draft)`.

- [ ] **Step 8: Keluarkan ID dari pending**

Hapus `FE-TEMPORAL-09` dari `pending.txt`; setel `# max: 77`.

- [ ] **Step 9: Gate penuh lalu commit**

Run QUALITY.

```bash
git add app/Http/Controllers tests docs/requirement-analysis/bdd
git commit -m "refactor(submission): delegate the real submit path to the action for BR-SM-01, 02, 04"
```

---

## Task 7: Frontend memanggil langkah 1

**Files:**
- Modify: `resources/js/Pages/User/FormPhase.vue`
- Create: `tests/frontend/pages/formPhaseStart.spec.ts`

**Interfaces:**
- Consumes: route `user.submissions.store` (Task 2)
- Produces: jaminan baris draft ada sebelum `save-draft` dipanggil

**Penting — "step" di berkas ini bukan langkah wizard ADR 0012.** `currentStepIndex`
mengindeks `groupedForms`, jadi berpindah langkah berarti berpindah **Form**, dan tiap
Form punya barisnya sendiri. Karena itu pemanggilannya `watch(currentForm)`, bukan
`onMounted` — `onMounted` hanya akan menjamin Form pertama.

- [ ] **Step 1: Tulis test yang gagal**

```ts
// tests/frontend/pages/formPhaseStart.spec.ts
import { describe, expect, it, vi } from 'vitest'

const post = vi.fn()

vi.mock('@inertiajs/vue3', () => ({
    router: { post },
    useForm: () => ({}),
    Head: {},
}))

// Fungsi yang diuji diekspor dari komponen supaya bisa dipanggil tanpa mount penuh.
import { ensureSubmissionStarted } from '@/Pages/User/FormPhase.vue'

describe('ensureSubmissionStarted', () => {
    it('memanggil endpoint langkah 1 sekali untuk satu Form', () => {
        post.mockClear()
        const started = new Set<number>()

        ensureSubmissionStarted(started, post, { formId: 7, periodId: 3, phaseId: 5 })
        ensureSubmissionStarted(started, post, { formId: 7, periodId: 3, phaseId: 5 })

        expect(post).toHaveBeenCalledTimes(1)
    })

    it('memanggilnya lagi saat Form berganti', () => {
        post.mockClear()
        const started = new Set<number>()

        ensureSubmissionStarted(started, post, { formId: 7, periodId: 3, phaseId: 5 })
        ensureSubmissionStarted(started, post, { formId: 8, periodId: 3, phaseId: 5 })

        expect(post).toHaveBeenCalledTimes(2)
    })
})
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `npm run test:run -- tests/frontend/pages/formPhaseStart.spec.ts`
Expected: FAIL — `ensureSubmissionStarted` belum diekspor.

- [ ] **Step 3: Tambahkan fungsi dan `watch` di `FormPhase.vue`**

Tambahkan blok `<script lang="ts">` terpisah di atas `<script setup>` supaya fungsinya
bisa diekspor dan diuji tanpa mount:

```ts
<script lang="ts">
type StartPayload = { formId: number; periodId: number; phaseId: number }
type PostFn = (url: string, data: Record<string, number>, options?: object) => void

// ADR 0012 langkah 1 — baris pengajuan harus ada sebelum langkah mana pun bisa
// disimpan. Dipanggil per Form, bukan per halaman: satu fase memuat beberapa Form.
export function ensureSubmissionStarted(started: Set<number>, post: PostFn, p: StartPayload): void {
    if (started.has(p.formId)) return

    started.add(p.formId)

    post(
        route('user.submissions.store'),
        { form_id: p.formId, submission_period_id: p.periodId, form_phase_id: p.phaseId },
        { preserveState: true, preserveScroll: true },
    )
}
</script>
```

Lalu di dalam `<script setup>`, setelah deklarasi `currentForm`:

```ts
const startedForms = new Set<number>()

watch(
    currentForm,
    (form) => {
        if (!form) return

        ensureSubmissionStarted(startedForms, router.post, {
            formId: form.id,
            periodId: props.submissionPeriod.id,
            phaseId: props.formPhase.id,
        })
    },
    { immediate: true },
)
```

Pastikan `watch` ada di import `vue` di berkas itu.

- [ ] **Step 4: Jalankan test sampai lulus**

Run: `npm run test:run -- tests/frontend/pages/formPhaseStart.spec.ts`
Expected: PASS, 2 test.

- [ ] **Step 5: Gate frontend penuh lalu commit**

Run: `npm run quality`

```bash
git add resources/js tests/frontend
git commit -m "feat(wizard): call the step-1 endpoint when a form becomes active"
```

---

## Task 8: ADR dan pembukuan register

**Files:**
- Create: `docs/adr/0016-submit-never-approves.md`
- Modify: `docs/adr/README.md`
- Modify: `docs/requirement-analysis/bdd/open-questions.md`
- Modify: `docs/design/bdd-coverage/m2-review.md`

Tidak ada kode dan tidak ada test di task ini. Ia tetap task tersendiri karena reviewer
bisa menolak rumusan keputusannya tanpa menolak implementasinya.

- [ ] **Step 1: Tulis ADR 0016**

`docs/adr/0016-submit-never-approves.md`, mengikuti bentuk ADR yang sudah ada
(Status / Tanggal / Konteks / Keputusan / Konsekuensi / Alternatif yang ditolak).

Isi yang wajib ada:

- **Konteks:** `submitForm()` memindahkan submission ke `Approved` ketika
  `FormPhaseDetail.needs_review` bernilai false; `SubmissionStatus::config()` tidak
  mengizinkan `Draft → Approved`, jadi cabang itu selalu melempar `TransitionNotFound`.
  Nol test dan nol seeder menyentuhnya.
- **Keputusan:** submit selalu berpindah ke `SUBMITTED`. `APPROVED` hanya dicapai lewat
  Review.
- **Konsekuensi:** fase dengan `needs_review = false` berhenti di `SUBMITTED` sampai M2
  menetapkan bagaimana kasus hampa BR-REV-05 (nol reviewer aktif, nol ReviewSummary
  terbuka) menyelesaikannya.
- **Alternatif yang ditolak:** memperlebar state machine dengan
  `allowTransition(Submitted, Approved)` lalu auto-approve saat submit — ditolak karena
  BR-REV-05 sudah memiliki aturan approval otomatis, dan menyalinnya ke jalur submit
  melahirkan dua implementasi untuk satu aturan, persis yang diperingatkan tabel
  *Aturan Cermin Lintas Konteks* di `ddd/README.md`.

Tambahkan barisnya ke tabel di `docs/adr/README.md`.

- [ ] **Step 2: Catat kasus hampa BR-REV-05 di spec M2**

Di `docs/design/bdd-coverage/m2-review.md`, pada bagian yang membahas BR-REV-05,
tambahkan catatan bahwa fase dengan `needs_review = false` menghasilkan submission yang
berhenti di `SUBMITTED`, dan M2 wajib menetapkan skenario yang memutuskan nasibnya.
Tautkan ke ADR 0016.

Tanpa langkah ini, pencabutan cabang di Task 6 menghilangkan satu-satunya jejak bahwa
kasus itu pernah ada.

- [ ] **Step 3: Pindahkan lima entri yang sudah tuntas**

Di `open-questions.md`, pindahkan entri **9.1, 10.1, 11.1, 12.1, dan 24.1** dari bagian
*Terbuka* ke *Terselesaikan* **tanpa mengubah isinya**. Kelimanya sudah membawa
keputusan bertanggal 2026-09-04 dan sudah diverifikasi mendarat (spec §1).

- [ ] **Step 4: Tutup #25, #26, #27, #28**

Pindahkan keempatnya ke *Terselesaikan*, masing-masing dengan catatan penutup yang
menyebut spec ini. Yang wajib ada:

- **#25** — sebutkan koreksi faktual: alasan teknis yang tercatat di entri itu keliru,
  karena cabang `needs_review` tidak pernah bekerja. Register tidak boleh mengabadikan
  alasan yang tidak benar.
- **#26** — `isRequiredFor()` kini punya pemanggil produksi lewat `RequiredFieldGuard`.
- **#27** — diputuskan: guard yang sama dipakai kedua jalur, jangkar tetap `created_at`.
- **#28** — diperbaiki di pencariannya (resolver + Policy), bukan dengan mencabut gerbang.

Perbarui kalimat pembuka bagian *Terbuka* supaya tidak lagi menjanjikan empat entri yang
sudah tidak ada di sana.

- [ ] **Step 5: Gate penuh lalu commit**

Run QUALITY (assertion BDD membaca `open-questions.md` hanya sebagai prosa, tapi gate
tetap dijalankan supaya tidak ada yang lolos).

```bash
git add docs
git commit -m "docs: close open questions 25-28 and record ADR 0016"
```

---

## Self-Review

**Cakupan spec.** Setiap bagian spec punya task:

| Spec | Task |
| ---- | ---- |
| §3.1 submit tidak menyetujui | 6 (cabut cabang), 8 (ADR 0016) |
| §3.2 validasi pindah + `isRequiredFor()` | 3 (guard), 4 (dipakai submit) |
| §3.3 resubmit memakai guard sama | 5 |
| §3.4 satu pembuat baris | 1 (Action), 2 (endpoint), 6 (controller) |
| §4 batas strangler + audiens notifikasi | 6 |
| §5 tiga komponen | 1, 1, 3 |
| §6 perubahan berkas | 4, 5, 6, 7 |
| §7 galat 409/403/422 | 6 |
| §8 strategi test | tersebar; frontend di 7 |
| §9 tiga skenario baru | 1 (`SM-WIZARD-12`), 5 (`SM-SUBMIT-19`), 6 (`SM-SUBMIT-18`) |
| §10 ledger 87 → 77 | 1, 3, 6 |
| §12 pembukuan register | 8 |

**Penyimpangan dari spec yang disengaja, dan alasannya:**

1. **Resolver tidak menyaring status** (spec §3.4 menulis "status `DRAFT` atau
   `NEEDS_REVISION`"). Menyaringnya membuat baris terminal tidak ditemukan sehingga
   `saveDraft()` menjawab `409`, padahal `[SM-STATE-04]` mengunci `403`. Penyaringan
   dipindah ke Policy, yang sudah memilikinya.
2. **Route `user.submissions.store`**, bukan `POST /submissions` (spec §5). Seluruh rute
   peneliti hidup di grup `prefix('user')`.
3. **`watch(currentForm)`, bukan `onMounted`** (spec §6). "Step" di `FormPhase.vue`
   mengindeks Form, bukan langkah wizard.
4. **Urutan ledger berbeda** dari tabel proyeksi spec §10; titik akhirnya sama, 77.

Keempatnya ditemukan saat memetakan spec ke berkas nyata. Kalau salah satunya ditolak
reviewer, yang berubah adalah spec — bukan diam-diam diikuti separuh.

**Konsistensi tipe.** `ResolveWizardSubmission::run(int, int, User): ?FormSubmission` dan
`StartSubmission::run(int, int, FormPhaseDetail, User): FormSubmission` dipakai dengan
tanda tangan itu persis di Task 2 dan Task 6.
`RequiredFieldGuard::assertComplete(FormSubmission): void` dipakai identik di Task 4 dan 5.

**Yang belum punya jawaban dan sengaja dibiarkan.** Task 6 Step 7 memperingatkan bahwa
`UserFormControllerFormPhaseDetailTest.php` perlu `StartSubmission::run()` di fixture-nya.
Jumlah persis test yang terdampak belum dipastikan — pelaksana wajib menjalankan suite
penuh dan memperbaiki yang merah, bukan menebak dari plan ini.
