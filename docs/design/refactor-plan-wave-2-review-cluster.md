# Refactor Gelombang 2 (lanjutan) — cluster review

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development`
> (disarankan) atau `superpowers:executing-plans`. Langkahnya memakai checkbox (`- [ ]`).
>
> **Dokumen ini spec.** Ia menetapkan lingkup, keputusan, dan ukuran keberhasilan;
> bagian *Tahapan* memberi urutannya. Langkah per langkahnya ada di rencana
> implementasi yang menyusul dokumen ini — kerjakan berurutan, jangan melompat.

**Goal:** Mengeluarkan tiga model cluster review dari `quality-legacy.txt` dengan
memindahkan logic yang bukan milik model ke `Actions/` dan `Queries/`.

**Architecture:** Bentuk yang sama dengan gelombang 2 bagian 1 — **buang dulu,
runtuhkan kedua, pindahkan ketiga** — tapi tiap tahap menyapu keempat model
sekaligus. Alasannya tunggal: keempatnya dipanggil controller yang sama dan
saling memanggil, jadi mengerjakannya satu per satu berarti membuka
`ReviewController` dan `ReviewFormResponseController` empat kali.

**Tech Stack:** Laravel 13 · PHP 8.4 · Pest · PHPStan tier strict

**Spec induk:** [`docs/design/refactor-plan.md`](refactor-plan.md) §2.5 dan §5 gelombang 2

**Pendahulu:** [`refactor-plan-wave-2-formsubmission.md`](refactor-plan-wave-2-formsubmission.md)

**Status:** **selesai 20 September 2026**, mendarat di `06a2d30`. Ditulis hari yang
sama sesudah gelombang 2 bagian 2 mendarat di `60ee951`. Suite acuan awal: **891 test**,
`max.legacy.files: 276`. Hasil akhir: **924 test**, `max.legacy.files: 273`.

---

## Status pelaksanaan

Seluruh 15 Task dikerjakan berurutan. `composer run quality` dan `npm run quality`
hijau di akhir. `pending.txt` dan `uncovered.txt` tidak berubah.

| Ukuran | Sebelum | Sesudah |
| ------ | ------: | ------: |
| Test | 891 | **924** |
| `max.legacy.files` | 276 | **273** |
| `SubmissionReviewer` | 318 baris | **136** |
| `ReviewFormResponse` | 241 baris | **105** |
| `ReviewFormFieldResponse` | 205 baris | **47** |
| `ReviewerFormAssignment` | 175 baris | **97** |

Ketiga model keluar dari `quality-legacy.txt`, dan `HardLimits::violations()` dengan
korpus penuh mengembalikan `[]` untuk keempatnya.

### Yang berbeda dari rencana

**Sensus butuh tiga putaran penghapusan, bukan dua.** Putaran 1 (Task 2–3), putaran 2
(`overdueReviewerFormAssignments`, `scopeOptional`, `scopeDueSoon`, `getStatus`,
`autoLockIfOverdue`, `getFieldResponse`), putaran 3 (`scopeOverdue`). Putaran 4
tidak menemukan apa pun.

**Lima simpangan sensus S1–S5** tercatat di bagian *Sensus bervonis* di atas dan
semuanya mengubah pelaksanaan:

- **S1** — `hasAllRequiredFormsCompleted()` punya pemanggil produksi, jadi Task 9
  mengalihkan `ReviewFormResponseController:377` ke `RequiredFormsCompletion::run()`
  alih-alih menghapus method-nya begitu saja.
- **S3** — `Actions/Review/RemoveEvaluationFormAssignment` **tidak jadi dibuat**.
  `SubmissionReviewer::removeFormAssignment()` nol pemanggil; jalur pencabutan yang
  sebenarnya ada di `ReviewerFormAssignmentController` dan lebih lengkap.
- **S5** — Task 11 tidak menulis ulang `autoLockIfOverdue()`; method itu sudah mati
  di putaran kedua. Penguncian tenggat tetap ditegakkan
  `ReviewerFormAssignmentController::autoLockOverdueAssignments()`.

**Test T2 diganti karena yang di rencana tidak membedakan apa pun.** Test "form wajib
ditambah sesudah submit" gagal karena instance PHP yang basi (`isDirty()` tidak
menyala), bukan karena duplikasi yang hendak dibuktikan — ia tetap merah sesudah
perbaikan. Penggantinya dua test yang memang membedakan kedua implementasi:
status melenceng yang diselaraskan saat submit, dan penugasan nonaktif yang harus
jatuh ke `not_required`.

**Tiga berkas keluar dari antrean lebih awal dari Task 15.** `QualityBudgetTest`
assertion 2 menolak berkas bersih yang masih terdaftar, jadi `ReviewFormResponse`
keluar di Task 3 (276→275) dan `ReviewFormFieldResponse` di Task 13 (275→274).
Task 15 menurunkan sisanya (274→273).

**`ReviewFormField::getValidationRulesStringAttribute()` ikut dicabut.**
Menyempitkan tipe `validation_rules` (Task 13 Step 3) membuka bahwa cabang
`is_bool()`-nya mati. Method-nya sendiri nol pemanggil — frontend punya
`formatValidationRules()` sendiri di `ShowPage.vue` — jadi ia lolos ketiga centang K2.

**`ReviewFormFieldResponse` diberi docblock properti.** `@property string|null $value`
dan `@property-read ReviewFormField|null $reviewFormField` ditambahkan supaya
`FieldResponseFormatter` dan `FieldResponseValidator` bisa bekerja di tier strict
tanpa cast — sesuai aturan 5, menyempitkan tipe lebih dulu, bukan membungkam PHPStan.

### Utang yang ditinggalkan

- `ReviewSummary` dan `Organization` masih di antrean legacy, masing-masing menunggu
  rencananya sendiri.
- Controller cluster review (`ReviewController`, `ReviewFormResponseController`,
  `ReviewerFormAssignmentController`) belum dibedah — itu gelombang 3.

---

## Lingkup

Empat model, 939 baris, 78 method:

| Model | Baris | Method | Lantai¹ | Di antrean legacy? | Pelanggaran persis² |
| ----- | ----: | -----: | ------: | ------------------ | ------------------- |
| `SubmissionReviewer` | 318 | 28 | 93 | ya | ukuran berkas · prosa komentar terlalu panjang |
| `ReviewFormResponse` | 241 | 20 | 69 | ya | ukuran berkas |
| `ReviewFormFieldResponse` | 205 | 13 | 43 | ya | ukuran berkas · `validate()` terlalu panjang · `validate()` terlalu kompleks · `validateCustomRules()` terlalu kompleks |
| `ReviewerFormAssignment` | 175 | 17 | 76 | **tidak** | — sudah bersih |

¹ Lantai = baris relasi + baris di luar method. Inilah ukuran berkas kalau
**seluruh** logic non-relasi keluar. Ketiganya jauh di bawah `hard.php.file.loc: 200`.

² Diambil langsung dari `App\Support\Quality\HardLimits::violations()` dengan korpus
penuh (`SourceTree::measured()`), bukan ditaksir. Korpus parsial memberi jawaban
berbeda karena deteksi duplikasi lintas berkas — **jangan mengukur dengan korpus
sebagian.**

**`ReviewerFormAssignment` ikut meski tidak punya utang.** `SubmissionReviewer` dan
`ReviewFormResponse` sama-sama merujuknya; membiarkannya di luar berarti dua model
yang sudah dirapikan tetap memanggil method yang bentuknya belum diputuskan. Ia tidak
perlu keluar dari antrean — ia memang tidak ada di sana — jadi yang berlaku baginya
hanya tahap 1–3.

### Kenapa target "keluar dari antrean" kali ini realistis

Task 6 di rencana pendahulu gagal karena lantai `FormSubmission` **215 baris**,
di atas ambang 200. Di sini lantai tertinggi **93**. Selisihnya bukan keberuntungan:
`FormSubmission` punya 21 relasi; keempat berkas ini punya 2–3 masing-masing.

---

## Global Constraints

Seluruh Global Constraints rencana pendahulu tetap berlaku. Yang perlu disebut ulang
karena paling sering dilanggar di cluster ini:

- **Identifier bahasa Inggris**; prosa komentar bahasa Indonesia, **maksimal 3 baris**.
  `SubmissionReviewer` sudah melanggar batas prosa ini hari ini — ia salah satu
  pelanggaran yang harus dibereskan, bukan diwariskan.
- **Dilarang menyiasati PHPStan** — tanpa `@phpstan-ignore`, baseline, `assert()`,
  inline `@var`, cast, atau pelebaran tipe. (AGENTS.md aturan 5)
- **Guard clause, bukan `if` bersarang.** Satu Action = satu use case. (AGENTS.md aturan 8)
- **Verifikasi wajib:** `composer run quality` hijau sebelum tugas dinyatakan selesai.
- **Pesan commit: satu baris, tanpa badan, tanpa trailer.**
- **Container:**
  ```bash
  docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
    -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 <perintah>
  ```

---

## Keputusan yang sudah diambil

Tiga keputusan ini **sudah ditetapkan** dan tidak perlu dibuka ulang oleh sesi yang
mengerjakan. Kalau salah satunya terasa salah saat dikerjakan, hentikan dan bawa ke
pemilik repo — jangan diputuskan sendiri di tengah jalan.

### K1 — Keempat model dikerjakan sebagai satu cluster, per tahap

Bukan satu model satu rencana seperti pendahulunya. Preseden itu lahir sebelum
terlihat bahwa keempat model ini saling memanggil dan berbagi controller.

### K2 — Kode mati dihapus, tapi hanya dengan tiga bukti

Tiap method nol pemanggil **wajib** lolos tiga centang sebelum dihapus:

1. **Nol pemanggil lewat bentuk panggilan yang benar** — lihat *Cara menghitung
   pemanggil* di bawah.
2. **Tidak dirujuk skenario BDD mana pun** — `grep -rn "<nama>" docs/requirement-analysis/bdd/`
3. **Aturan bisnis yang ditegakkannya punya penegak lain** — kalau method itu
   satu-satunya yang menegakkan sebuah BR, ia bukan kode mati melainkan kode yang
   kehilangan pemanggilnya, dan itu bug tersendiri.

Gagal salah satu → **method tetap**, dan alasannya dicatat di dokumen ini.
Penghapusan masuk **commit terpisah** dari pemindahan, supaya mudah dibalik satu-satu.

Kebijakan ini lahir dari putaran review gelombang 2 bagian 2: penghapusan
`EvaluationRequirements` ditahan dan diminta dibuktikan dulu. Pemeriksaannya benar
dan hasilnya aman — jadi pemeriksaannya yang dinaikkan jadi prosedur.

### K3 — `isDraft()` dan `canBeEdited()` TIDAK diruntuhkan

Keduanya byte-identik hari ini (`$this->status === 'draft'`), jadi aturan "runtuhkan
predikat kembar" seharusnya menyatukannya. **Sengaja tidak.** "Ini draft" adalah
fakta tentang baris data; "boleh disunting" adalah aturan yang kebetulan sekarang
punya jawaban sama. Menyatukannya mengunci kebetulan itu jadi desain, dan pemisahan
inilah yang membuat aturan sunting bisa berubah tanpa menyentuh arti `draft`.

Ini satu-satunya pengecualian. Predikat kembar lain **diruntuhkan**.

---

## Cara menghitung pemanggil

Sensus gelombang 2 meleset — 5 method mati lolos dan terlanjur dipindahkan. Dua
sebabnya sudah teridentifikasi, dan keduanya menjebak lagi di cluster ini.

**Jebakan 1 — nama method bukan nama panggilan.**

| Bentuk deklarasi | Bentuk panggilan | Contoh nyata di cluster ini |
| ---------------- | ---------------- | --------------------------- |
| `scopeFoo()` | `->foo()` | `scopeUnsettledEvaluation` → `unsettledEvaluation` |
| `getFooBarAttribute()` | `->foo_bar` | `getFormattedValueAttribute` → `formatted_value` |

Mencari `scopeUnsettledEvaluation` di seluruh repo memberi **0** padahal ia dipakai
dua tempat. Sensus yang tidak memetakan kedua bentuk ini **akan** menghapus kode hidup.

**Jebakan 2 — nama berkata umum memberi angka palsu.** Di cluster ini:
`active` (605 kecocokan), `required` (614), `draft` (1565), `submitted` (1163),
`submit` (653), `reviewer` (1690), `validate` (127), `lock` (10). Semuanya harus
diverifikasi lewat **bentuk panggilan beserta penerimanya**, bukan kata telanjang.

**Jebakan 3 — pemanggil internal.** Method `protected`/`private` dengan nol
pemanggil di luar berkasnya belum tentu mati: ia bisa dipanggil method publik di
berkas yang sama. Yang mati adalah method yang nol pemanggil **dan** pemanggil
internalnya juga mati.

**Karena itu sensusnya iteratif.** Membuang satu putaran kode mati membuka putaran
berikutnya. Contoh nyata yang sudah terlihat: `autoLockOverdueForms()` nol pemanggil,
dan satu-satunya pemakai `overdueReviewerFormAssignments()` adalah ia sendiri plus
`getOverdueFormsCountAttribute()` yang juga nol. **Ulangi sensus sampai satu putaran
penuh tidak menemukan apa pun.**

---

## Sensus bervonis

Angka di bawah sudah memetakan scope dan accessor ke bentuk panggilannya, sudah
memverifikasi nama berkata umum lewat `->nama(`, dan sudah memeriksa pemanggil
internal. Kolom **luar** hanya menghitung pemanggil PHP yang nyata; kolom **dalam**
menghitung pemanggil di dalam berkas modelnya sendiri.

Vonisnya: **MATI** = boleh dihapus · **INTERNAL** = hidup selama pemanggil dalamnya
hidup · **HIDUP** = punya pemanggil luar.

### `SubmissionReviewer`

| Method | panggil | luar | dalam | BDD | vonis |
| ------ | ------- | ---: | ----: | --: | ----- |
| `formSubmission`, `reviewer`, `reviewerFormAssignments` | idem | banyak | — | 0 | HIDUP (relasi) |
| `activeReviewerFormAssignments` | idem | 1 (`ReviewSummary:210`) | 2 | 0 | HIDUP |
| `completedReviewerFormAssignments` | idem | 1 (`ReviewSummary:211`) | 2 | 0 | HIDUP |
| `pendingReviewerFormAssignments` | idem | 1 (`ReviewSummary:212`) | 1 | 0 | HIDUP |
| `requiredReviewerFormAssignments` | idem | 0 | 2 | 0 | INTERNAL (`hasAllRequiredFormsCompleted`, hidup) |
| `optionalReviewerFormAssignments` | idem | 0 | 0 | 0 | **MATI** |
| `overdueReviewerFormAssignments` | idem | 0 | 2 | 0 | INTERNAL → MATI putaran 2 |
| `dueSoonReviewerFormAssignments` | idem | 0 | 0 | 0 | **MATI** |
| `scopeActive` | `active` | 1 (`ReviewFormResponseController:68`) | 6 | 0 | HIDUP |
| `scopeUnsettledEvaluation` | `unsettledEvaluation` | 2 | 0 | 0 | HIDUP |
| `hasSettledEvaluation` | idem | 5 | 0 | 0 | HIDUP — invarian BR-FE-06 |
| `markAsReplaced` | idem | 1 (`ReplaceReviewer:48`) | 0 | 0 | HIDUP |
| `hasAssignedForms` | idem | 0 | 1 | 0 | INTERNAL (`updateEvaluationStatus`) |
| `hasAllRequiredFormsCompleted` | idem | **1** (`ReviewFormResponseController:376`) | 1 | 0 | **HIDUP** — lihat S1 |
| `getEvaluationCompletionPercentage` | idem | 0 | 0 | 0 | **MATI** |
| `updateEvaluationStatus` | idem | 5 (controller) | 3 | 0 | HIDUP → `Support/` |
| `canCreateDiscussionThreads` | idem | 0 | 1 | 0 | INTERNAL → dihapus T1 |
| `canParticipateInDiscussions` | idem | 4 | 0 | 0 | HIDUP → diruntuhkan T1 |
| `getEvaluationStatusLabelAttribute` | `evaluation_status_label` | 0 | 0 | 0 | **MATI** |
| `getEvaluationStatusColorAttribute` | `evaluation_status_color` | 0 | 0 | 0 | **MATI** |
| `getPendingFormsCountAttribute` | `pending_forms_count` | 3 (`ReviewController`) | 0 | 0 | HIDUP |
| `getCompletedFormsCountAttribute` | `completed_forms_count` | 0 | 0 | 0 | **MATI** |
| `getOverdueFormsCountAttribute` | `overdue_forms_count` | 0 | 0 | 0 | **MATI** |
| `autoLockOverdueForms` | idem | 0 | 0 | 0 | **MATI** |
| `assignForm` | idem | 2 (`ReviewController:488,560`) | 0 | 0 | HIDUP → Action |
| `removeFormAssignment` | idem | **0** | 0 | 0 | **MATI** — lihat S3 |

### `ReviewerFormAssignment`

| Method | panggil | luar | dalam | BDD | vonis |
| ------ | ------- | ---: | ----: | --: | ----- |
| `submissionReviewer`, `reviewEvaluationForm`, `reviewFormResponse` | idem | banyak | — | 0 | HIDUP (relasi) |
| `scopeActive` | `active` | 6 | 0 | 0 | HIDUP |
| `scopeRequired` | `required` | 2 | 0 | 0 | HIDUP |
| `scopeOptional` | `optional` | 1 (`optionalReviewerFormAssignments`, MATI) | 0 | 0 | MATI putaran 2 |
| `scopeOverdue` | `overdue` | 1 (`overdueReviewerFormAssignments`, MATI p2) | 0 | 0 | MATI putaran 2 |
| `scopeDueSoon` | `dueSoon` | 1 (`dueSoonReviewerFormAssignments`, MATI) | 0 | 0 | MATI putaran 2 |
| `isOverdue` | idem | 2 (`ReviewFormResponseController:446,456`) | 1 | 0 | HIDUP |
| `isCompleted` | idem | 2 (`ReviewerFormAssignmentController:322,323`) | 2 | 0 | HIDUP |
| `getDaysUntilDue` | idem | 2 (`ReviewFormResponseController:447,457`) | 0 | 0 | HIDUP |
| `isDueSoon` | idem | 0 | 0 | 0 | **MATI** |
| `getHoursUntilDue` | idem | 0 | 0 | 0 | **MATI** |
| `canBeCompleted` | idem | 0 | 0 | 0 | **MATI** |
| `getStatusLabel` | idem | **0** | 0 | 0 | **MATI** — lihat S2 |
| `getStatus` | idem | 0 | 2 (keduanya MATI) | 0 | MATI putaran 2 |
| `autoLockIfOverdue` | idem | 1 (`autoLockOverdueForms`, MATI) | 0 | 0 | MATI putaran 2 — lihat S5 |

### `ReviewFormResponse`

| Method | panggil | luar | dalam | BDD | vonis |
| ------ | ------- | ---: | ----: | --: | ----- |
| `reviewerFormAssignment`, `reviewFormFieldResponses` | idem | banyak | — | 0 | HIDUP (relasi) |
| `scopeDraft` | `draft` | **0** | 0 | 0 | **MATI** — lihat S4 |
| `scopeSubmitted` | `submitted` | **0** | 0 | 0 | **MATI** — lihat S4 |
| `scopeLocked` | `locked` | **0** | 0 | 0 | **MATI** — lihat S4 |
| `isDraft` | idem | 2 | 0 | 0 | HIDUP — K3 melarang meruntuhkannya |
| `isSubmitted` | idem | 4 | 0 | 0 | HIDUP |
| `isLocked` | idem | 0 prod, 1 test | 1 | 0 | HIDUP |
| `canBeEdited` | idem | 3 | 0 | 0 | HIDUP — K3 |
| `canBeSubmitted` | idem | 3 | 1 | 0 | HIDUP |
| `submit` | idem | 1 prod (`ReviewFormResponseController:238`) | 0 | 0 | HIDUP → Action |
| `lock` | idem | 1 (`ReviewerFormAssignmentController:353`) | 0 | 0 | HIDUP → Action |
| `hasRequiredFieldsCompleted` | idem | 0 | 1 | 0 | INTERNAL (`canBeSubmitted`, hidup) |
| `getCompletionPercentage` | idem | 2 (`ReviewFormResponseController:148,453`) | 0 | 0 | HIDUP → Query |
| `saveFieldResponse` | idem | 2 (`ReviewFormResponseController:177,223`) | 0 | 0 | HIDUP — tetap di model |
| `getFieldResponse` | idem | 0 | 1 (`generateSummaryNotes`, MATI) | 0 | MATI putaran 2 |
| `updateReviewerEvaluationStatus` | idem | 0 | 1 (`submit`) | 0 | INTERNAL → dihapus T2 |
| `generateSummaryNotes` | idem | 0 | 0 | 0 | **MATI** |
| `getStatusLabelAttribute` | `status_label` | 0 | 0 | 0 | **MATI** |
| `getStatusColorAttribute` | `status_color` | 0 | 0 | 0 | **MATI** |

### `ReviewFormFieldResponse`

| Method | panggil | luar | dalam | BDD | vonis |
| ------ | ------- | ---: | ----: | --: | ----- |
| `reviewFormResponse`, `reviewFormField` | idem | banyak | — | 0 | HIDUP (relasi) |
| `hasValue` | idem | 3 | 3 | 0 | HIDUP — tetap di model |
| `getFormattedValueAttribute` | `formatted_value` | 6 | 0 | 0 | HIDUP → `Support/` (T4) |
| `formatCheckboxValue` … `formatUrlValue` | idem | 0 | 1 masing-masing | 0 | INTERNAL → pindah/hapus T3–T4 |
| `getEditValueAttribute` | `edit_value` | 0 | 0 | 0 | **MATI** |
| `validate` | idem | 1 (`ReviewFormResponseController:363`) | 0 | 0 | HIDUP → `Support/` (T5) |
| `validateCustomRules` | idem | 0 | 1 (`validate`) | 0 | INTERNAL → pindah T5 |

### Lima simpangan dari dugaan awal

Sensus mentah yang digantikan tabel di atas salah di lima tempat. Semuanya jebakan
yang sudah diperingatkan bagian *Cara menghitung pemanggil*, dan semuanya mengubah
rencana implementasi.

**S1 — `hasAllRequiredFormsCompleted()` HIDUP, bukan internal.** Ia dipanggil
`ReviewFormResponseController:376`. Task 9 Step 3 mengira pemanggilnya hanya
`updateEvaluationStatus()`. Ia **tidak dihapus begitu saja**: pemanggil controller-nya
dialihkan ke `RequiredFormsCompletion::run()`.

**S2 — `ReviewerFormAssignment::getStatusLabel()` MATI, bukan hidup (5).** Kelima
kecocokan itu fungsi Vue bernama sama di `IndexPage.vue` dan `DashboardPage.vue`,
bukan pemanggil PHP. Jebakan 2 persis. `getStatus()` ikut mati di putaran kedua.

**S3 — `SubmissionReviewer::removeFormAssignment()` MATI, bukan hidup (2).** Kedua
kecocokan itu `ReviewerFormAssignmentController::removeFormAssignment()` dan
route-nya — method senama di kelas lain. Controller punya jalur pencabutannya
sendiri yang lebih lengkap (menolak respons tersubmit, menghapus field response
turunan) dan memanggil `updateEvaluationStatus()` sendiri, jadi centang K2 ketiga
terpenuhi. Akibatnya **`Actions/Review/RemoveEvaluationFormAssignment` tidak jadi
dibuat** — Action tanpa pemanggil produksi persis kesalahan lima kelas gelombang 2.

**S4 — `scopeDraft`, `scopeSubmitted`, dan `scopeLocked` di `ReviewFormResponse`
MATI.** Ketiganya tidak ada di sensus mentah sama sekali. Tidak ada satu pun
`->draft(`, `->submitted(`, atau `->locked(` di seluruh repo, dan tidak ada
pemanggilan statiknya.

**S5 — `autoLockIfOverdue()` MATI di putaran kedua.** Satu-satunya pemanggilnya
`autoLockOverdueForms()`, yang MATI. Penguncian karena tenggat tetap punya penegak:
`ReviewerFormAssignmentController::autoLockOverdueAssignments()` di
`routes/web.php:489`, yang memanggil `->lock()` langsung. Karena itu Task 11 Step 5
**tidak** menulis ulang `autoLockIfOverdue()` — method-nya sudah tidak ada saat
Task 11 tiba.

## Temuan yang sudah dipastikan

Lima temuan berikut **sudah diperiksa langsung di kode**, bukan dugaan. Masing-masing
jadi tugas tersendiri.

### T1 — Tiga nama, satu pertanyaan (`SubmissionReviewer`)

```php
hasSettledEvaluation()        // in_array($this->evaluation_status, SETTLED_EVALUATION)
canCreateDiscussionThreads()  // === 'completed' || === 'not_required'
canParticipateInDiscussions() // → canCreateDiscussionThreads()
```

Ketiganya menjawab "evaluasinya sudah beres?", dan itu persis definisi BR-FE-06.
Yang bertahan **`hasSettledEvaluation()`** — ia satu-satunya yang diturunkan dari
konstanta `SETTLED_EVALUATION`, sehingga status baru yang kelak ditambahkan tidak
diam-diam lolos. Dua lainnya menuliskan ulang daftarnya sebagai literal.

Pemanggil `canParticipateInDiscussions()` (4) dialihkan; `canCreateDiscussionThreads()`
(0) dihapus.

### T2 — Dua implementasi "hitung ulang status evaluasi", satu di antaranya cuma bisa maju

| Tempat | Perilaku |
| ------ | -------- |
| `SubmissionReviewer::updateEvaluationStatus()` | menangani tiga keadaan: `not_required`, `completed`, `pending` |
| `ReviewFormResponse::updateReviewerEvaluationStatus()` | **hanya menulis `completed`**; tidak pernah mengembalikan ke `pending` |

`submit()` memanggil yang kedua. Akibatnya: reviewer yang sudah `completed` lalu
mendapat penugasan form baru tidak pernah turun kembali ke `pending` lewat jalur ini —
yang menyelamatkannya hanyalah `assignForm()` memanggil versi pertama.

Ini pola yang sama persis dengan BR-REV-05 di putaran sebelumnya: satu definisi,
dua pemakai, dua jawaban. Satukan ke satu Action, lalu kedua pemanggil memakainya.

**Tulis test yang gagal lebih dulu** — perilaku "tidak pernah kembali ke pending"
harus terlihat merah sebelum diperbaiki.

### T3 — `formatEmailValue()` tidak melakukan apa pun

```php
return filter_var($this->value, FILTER_VALIDATE_EMAIL) ? $this->value : $this->value;
```

Kedua cabangnya sama. Hapus method-nya, dan `'email'` di `match` jatuh ke `default`
yang perilakunya identik.

### T4 — HTML dirakit di dalam model, lalu bocor sebagai teks

`formatUrlValue()` mengembalikan `<a href='…' class='text-blue-600 …'>…</a>`.
**Ini bukan lubang XSS** — sudah diperiksa: `formatted_value` tidak pernah dirender
`v-html` di mana pun. Akibatnya justru lebih membosankan dan lebih pasti salah:

- `SubmittedPage.vue` menampilkannya lewat interpolasi biasa, jadi reviewer melihat
  markup mentah.
- `ReviewSummary.php:135` dan `ReviewFormResponseController.php:416` menyisipkannya
  ke ringkasan **teks polos**.

`formatted_value` juga presentasi murni di dalam model. Pindahkan seluruh keluarga
`format*` ke `app/Support/Review/FieldResponseFormatter.php`, dan **buang HTML-nya** —
yang dikembalikan nilai terbaca; keputusan menjadikannya tautan milik layar.

### T5 — `validate()` adalah tiga pelanggaran plafon sekaligus

`validate()` terlalu panjang **dan** terlalu kompleks; `validateCustomRules()` terlalu
kompleks. Keduanya validasi berbasis `switch` atas tipe field dan aturan kustom —
bukan tanggung jawab model.

Preseden repo sudah ada: `app/Support/RequiredFieldGuard.php`, `final class` statis,
dengan alasan tertulis *"sebuah guard bukan use case sehingga ia tidak berhak jadi
Action"*. Ikuti bentuk itu: `app/Support/Review/FieldResponseValidator.php`.

Memecah `switch`-nya per tipe field adalah yang menurunkan kompleksitasnya; jangan
memindahkannya utuh lalu berharap angkanya berubah.

---

## Peta Berkas

Usulan, bukan perintah — sesi yang mengerjakan boleh mengubah nama asal alasannya
dicatat. Yang **tidak** boleh berubah: Action mengubah state, Query tidak, dan
keduanya otomatis kena tier strict.

| Berkas | Tanggung jawab | Asal |
| ------ | -------------- | ---- |
| `app/Actions/Review/RecalculateEvaluationStatus.php` | satu definisi hitung ulang status evaluasi | T2 |
| `app/Actions/Review/AssignEvaluationForm.php` | menugaskan satu form evaluasi | `assignForm` |
| `app/Actions/Review/RemoveEvaluationFormAssignment.php` | mencabut satu penugasan | `removeFormAssignment` |
| `app/Actions/Review/SubmitEvaluationForm.php` | mengunci jawaban evaluasi jadi `submitted` | `submit` |
| `app/Actions/Review/LockEvaluationForm.php` | mengunci karena tenggat | `lock` |
| `app/Actions/Review/SaveEvaluationFieldResponse.php` | menyimpan satu jawaban field | `saveFieldResponse` |
| `app/Queries/Review/RequiredFormsCompletion.php` | apakah seluruh form wajib sudah selesai | `hasAllRequiredFormsCompleted` |
| `app/Queries/Review/EvaluationFormCompletion.php` | persentase penyelesaian satu form | `getCompletionPercentage` |
| `app/Support/Review/FieldResponseFormatter.php` | nilai jawaban yang terbaca manusia | T4 |
| `app/Support/Review/FieldResponseValidator.php` | galat validasi satu jawaban | T5 |

`markAsReplaced()` **tidak** dibuatkan Action tersendiri: ia satu baris `update()`
dan pemanggil sebenarnya sudah `Actions/Review/ReplaceReviewer`. Memindahkannya hanya
menambah lompatan. Ini persis kesalahan yang gelombang 2 lakukan dengan lima kelas
yang lalu dihapus lagi.

---

## Tahapan

Lima tahap. **Tiap tahap berakhir dengan `composer run quality` hijau dan satu commit**,
sehingga sesi lain boleh berhenti di batas tahap mana pun tanpa meninggalkan pekerjaan
setengah jadi.

| Tahap | Isi | Menyentuh controller? |
| ----- | --- | --------------------- |
| 1 | Sensus ulang yang benar, lalu cabut kode mati — **iteratif sampai bersih** | tidak |
| 2 | Runtuhkan predikat kembar (T1), perbaiki T2, cabut T3 | tidak |
| 3 | Pindahkan Action dan Query; alihkan pemanggilnya | **ya, sekali** |
| 4 | Bedah `validate()` (T5) dan `formatted_value` (T4); rapikan prosa komentar | tidak |
| 5 | Keluarkan tiga berkas dari `quality-legacy.txt`, turunkan `max.legacy.files` | tidak |

Tahap 3 satu-satunya yang membuka controller. Tahap 1, 2, dan 4 seluruhnya di dalam
`app/Models/` dan berkas baru — itulah yang membuat urutan ini lebih murah daripada
model per model.

---

## Risiko

**Yang terbesar: memindahkan method yang dipanggil banyak tempat.** `reviewer` muncul
1690 kali, `submit` 653. Yang menahannya 891 test yang sudah ada. Kalau ada tahap yang
harus dikerjakan paling kecil-kecil, tahap 3.

**Yang paling mungkin terlewat: kode mati putaran kedua.** Gelombang 2 kecolongan lima
method karena sensusnya sekali jalan. Tahap 1 tidak selesai sampai satu putaran penuh
tidak menemukan apa pun.

**Yang paling mungkin salah kaprah:** menghapus method yang nol pemanggil padahal ia
satu-satunya penegak sebuah aturan bisnis. K2 centang ketiga ada untuk ini.

---

## Definisi selesai

- [ ] `composer run quality` hijau; jumlah test **naik**, tidak turun
- [ ] `npm run quality` hijau
- [ ] `app/Models/SubmissionReviewer.php`, `ReviewFormResponse.php`, dan
      `ReviewFormFieldResponse.php` keluar dari `quality-legacy.txt`
- [ ] `max.legacy.files` **276 → 273** di `quality-budget.txt`
- [ ] `HardLimits::violations()` mengembalikan `[]` untuk ketiga berkas, diukur dengan
      korpus penuh
- [ ] Tiap method yang dihapus punya tiga centang K2 tercatat
- [ ] `pending.txt` dan `uncovered.txt` tidak naik

---

## Yang tidak dikerjakan dokumen ini

- **`ReviewSummary` dan `Organization`.** Keduanya sisa gelombang 2 tapi bukan cluster
  review; masing-masing dapat rencananya sendiri.
- **Controller.** Gelombang 3. Tahap 3 hanya mengalihkan pemanggilan, bukan membedah
  controller-nya.
- **Utang terbuka dari putaran sebelumnya** — form evaluasi yang digantung di detail
  pengajuan alih-alih detail evaluasi, nasib `EvaluationRequirements`, dan flake
  `[SM-WIZARD-06]`. Ketiganya tercatat di rencana pendahulu dan tidak disentuh di sini.

---
---

# Rencana Implementasi

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development`
> (disarankan) atau `superpowers:executing-plans`. Langkahnya memakai checkbox (`- [ ]`).
>
> Spec-nya bagian atas dokumen ini. **Baca dulu bagian *Keputusan yang sudah diambil*
> dan *Cara menghitung pemanggil*** — dua bagian itu yang paling sering dilanggar.

**Goal:** Mengeluarkan `SubmissionReviewer`, `ReviewFormResponse`, dan
`ReviewFormFieldResponse` dari `quality-legacy.txt` dengan memindahkan logic yang
bukan milik model ke `Actions/`, `Queries/`, dan `Support/`.

**Architecture:** Buang dulu (Task 1–4), runtuhkan kedua (Task 5–7), pindahkan ketiga
(Task 8–11), bedah keempat (Task 12–14), turunkan plafon terakhir (Task 15).

**Tech Stack:** Laravel 13 · PHP 8.4 · Pest · PHPStan tier strict (level 10)

**Spec:** bagian atas dokumen ini.

## Global Constraints

- **Identifier bahasa Inggris**; prosa komentar bahasa Indonesia, **maksimal 3 baris**.
- **Dilarang menyiasati PHPStan** — tanpa `@phpstan-ignore`, baseline, `assert()`,
  inline `@var`, cast, atau pelebaran tipe.
- **`app/Actions/`, `app/Queries/`, dan `app/Support/` kena tier strict level 10.**
  `app/Models/` dan `app/Http/` tidak.
- **Action wajib menulis `AuditTrail::record()`**, atau `implements ChangesNoState`
  kalau ia kueri murni. Ditegakkan `tests/Unit/Audit/AuditCoverageTest.php`.
- **Guard clause, bukan `if` bersarang.** Satu Action = satu use case.
- **Pesan commit: satu baris, tanpa badan, tanpa trailer.**
- **Gerbang:** `composer run quality` hijau sebelum tiap Task dinyatakan selesai.
- **Container** (host PHP 8.5 tidak bisa `composer install`):
  ```bash
  docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
    -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 <perintah>
  ```
  Disingkat **`CI`** di seluruh rencana ini. Pint tidak butuh database:
  `docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q`

---

## Tahap 1 — Sensus dan kode mati

### Task 1: Sensus yang memetakan scope dan accessor

Sensus gelombang 2 meleset karena mencari nama deklarasi, bukan nama panggilan.
Task ini menghasilkan daftar vonis yang dipakai Task 2–4, dan **tidak menyentuh kode
produksi sama sekali**.

**Files:**
- Modify: `docs/design/refactor-plan-wave-2-review-cluster.md` (bagian *Sensus mentah*)

**Interfaces:**
- Consumes: —
- Produces: daftar method bervonis `MATI` / `HIDUP` / `INTERNAL` yang dipakai Task 2–4

- [ ] **Step 1: Jalankan sensus dengan pemetaan nama**

```bash
cat > /tmp/census.sh <<'SH'
#!/bin/bash
snake() { echo "$1" | sed 's/\([a-z0-9]\)\([A-Z]\)/\1_\2/g' | tr '[:upper:]' '[:lower:]'; }
for M in SubmissionReviewer ReviewerFormAssignment ReviewFormResponse ReviewFormFieldResponse; do
  F="app/Models/$M.php"
  echo "===== $M ====="
  grep -oP '(?<=public function )\w+|(?<=protected function )\w+|(?<=private function )\w+' "$F" | while read -r m; do
    case "$m" in
      scope*) c="${m#scope}"; c="$(echo "${c:0:1}" | tr '[:upper:]' '[:lower:]')${c:1}" ;;
      get*Attribute) mid="${m#get}"; c="$(snake "${mid%Attribute}")" ;;
      *) c="$m" ;;
    esac
    luar=$(grep -rn "\b$c\b" app/ resources/ tests/ routes/ database/ 2>/dev/null | grep -v "^$F:" | grep -cv '^\s*$')
    dalam=$(grep -c "\b$c\b" "$F")
    printf '%-40s panggil=%-30s luar=%-6s dalam=%s\n' "$m" "$c" "$luar" "$dalam"
  done
done
SH
chmod +x /tmp/census.sh && /tmp/census.sh
```

Expected: satu baris per method. `luar=0` berarti **kandidat** mati, belum vonis.

- [ ] **Step 2: Verifikasi tiap nama berkata umum lewat bentuk panggilan**

Delapan nama di bawah memberi angka palsu karena kata biasa. Untuk tiap satu,
hitung ulang memakai bentuk panggilannya:

```bash
for c in active required optional overdue dueSoon draft submitted locked submit lock validate reviewer; do
  printf '%-12s ->%s( : %s\n' "$c" "$c" "$(grep -rn -- "->$c(" app/ tests/ routes/ database/ | wc -l)"
done
```

Angka inilah yang dipakai, bukan angka Step 1.

- [ ] **Step 3: Terapkan tiga centang K2 untuk tiap kandidat**

Untuk **setiap** method dengan `luar=0`:

```bash
M=<namaMethodnya>
C=<namaPanggilannya>
echo "1. pemanggil luar : $(grep -rn -- "->$C(" app/ resources/ tests/ routes/ | wc -l)"
echo "2. dirujuk BDD    : $(grep -rn "$C" docs/requirement-analysis/bdd/ | wc -l)"
echo "3. pemanggil dalam: $(grep -n "\b$C\b" app/Models/<Model>.php | grep -v 'function' | wc -l)"
```

Vonisnya:
- `1=0` dan `2=0` dan `3=0` → **MATI**, boleh dihapus
- `1=0` dan `2=0` tapi `3>0` → **INTERNAL**, hanya mati kalau pemanggil dalamnya juga MATI
- `2>0` → **HIDUP**, jangan dihapus; catat BR/skenarionya di dokumen

Centang ketiga K2 — "aturan bisnis yang ditegakkannya punya penegak lain" — dijawab
dengan membaca method-nya, bukan dengan grep. Kalau method itu satu-satunya yang
menegakkan sebuah BR, **ia bukan kode mati melainkan kode yang kehilangan
pemanggilnya**; hentikan dan laporkan.

- [ ] **Step 4: Tulis hasilnya ke dokumen**

Ganti tabel *Sensus mentah* dengan tabel bervonis. Kolomnya: Method · panggil ·
luar · dalam · BDD · **vonis**. Hapus kalimat "Angka di bawah hasil sapuan otomatis
… Task 1 mengulanginya dengan benar" — ia sudah tidak benar setelah Task ini.

- [ ] **Step 5: Commit**

```bash
git add docs/design/refactor-plan-wave-2-review-cluster.md
git commit -m "docs(design): sensus bervonis untuk cluster review"
```

---

### Task 2: Cabut kode mati `SubmissionReviewer`

**Files:**
- Modify: `app/Models/SubmissionReviewer.php`

**Interfaces:**
- Consumes: daftar vonis Task 1
- Produces: `SubmissionReviewer` tanpa method bervonis MATI

- [ ] **Step 1: Hapus hanya yang bervonis MATI di Task 1**

Kandidat dari sensus awal — **jangan hapus yang tidak lolos Task 1**:
`optionalReviewerFormAssignments`, `dueSoonReviewerFormAssignments`,
`getEvaluationCompletionPercentage`, `autoLockOverdueForms`,
`getEvaluationStatusLabelAttribute`, `getEvaluationStatusColorAttribute`,
`getCompletedFormsCountAttribute`, `getOverdueFormsCountAttribute`.

Hapus badan method beserta komentar di atasnya. **Jangan** sentuh
`requiredReviewerFormAssignments`, `overdueReviewerFormAssignments`, dan
`hasAssignedForms` di Step ini — ketiganya INTERNAL dan giliran Task 4.

- [ ] **Step 2: Jalankan gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, **891 test**, jumlahnya tidak berubah.

Kalau ada yang merah, method itu ternyata hidup dan sensusnya salah — kembalikan,
lalu cari tahu bentuk panggilan apa yang terlewat.

- [ ] **Step 3: Commit**

```bash
git add app/Models/SubmissionReviewer.php
git commit -m "refactor(review): cabut kode mati SubmissionReviewer"
```

---

### Task 3: Cabut kode mati tiga model sisanya

**Files:**
- Modify: `app/Models/ReviewerFormAssignment.php`
- Modify: `app/Models/ReviewFormResponse.php`
- Modify: `app/Models/ReviewFormFieldResponse.php`

**Interfaces:**
- Consumes: daftar vonis Task 1
- Produces: tiga model tanpa method bervonis MATI

- [ ] **Step 1: Hapus yang bervonis MATI**

Kandidat dari sensus awal:

| Model | Kandidat |
| ----- | -------- |
| `ReviewerFormAssignment` | `isDueSoon`, `getHoursUntilDue`, `canBeCompleted` |
| `ReviewFormResponse` | `getStatusLabelAttribute`, `getStatusColorAttribute`, `generateSummaryNotes` |
| `ReviewFormFieldResponse` | `getEditValueAttribute` |

`generateSummaryNotes` memakai `getFieldResponse`; kalau ia MATI, `getFieldResponse`
jadi kandidat putaran kedua (Task 4). Jangan hapus keduanya sekaligus di sini —
hapus yang luar, biarkan Task 4 menyusul yang dalam.

- [ ] **Step 2: Jalankan gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 891 test.

- [ ] **Step 3: Commit**

```bash
git add app/Models
git commit -m "refactor(review): cabut kode mati tiga model cluster review"
```

---

### Task 4: Sensus putaran kedua sampai bersih

Gelombang 2 kecolongan lima method karena sensusnya sekali jalan. Task ini yang
mencegahnya berulang.

**Files:**
- Modify: `app/Models/SubmissionReviewer.php`
- Modify: `app/Models/ReviewFormResponse.php`
- Modify: `app/Models/ReviewFormFieldResponse.php`

**Interfaces:**
- Consumes: hasil Task 2 dan 3
- Produces: keempat model tanpa satu pun method nol pemanggil

- [ ] **Step 1: Jalankan ulang sensus Task 1 Step 1**

Run: `/tmp/census.sh`

Method yang tadinya INTERNAL kini bisa jadi MATI karena pemanggil dalamnya sudah
hilang. Yang sudah terlihat akan muncul: `overdueReviewerFormAssignments` (pemakainya
`autoLockOverdueForms` dan `getOverdueFormsCountAttribute`, keduanya dihapus Task 2)
dan `getFieldResponse` kalau `generateSummaryNotes` dihapus Task 3.

- [ ] **Step 2: Terapkan tiga centang K2, hapus yang MATI**

Sama persis dengan Task 1 Step 3.

- [ ] **Step 3: Ulangi Step 1–2 sampai satu putaran penuh tidak menemukan apa pun**

Task ini **tidak selesai** sampai sensus berjalan tanpa hasil baru. Catat berapa
putaran yang dibutuhkan di pesan commit.

- [ ] **Step 4: Jalankan gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 891 test.

- [ ] **Step 5: Commit**

```bash
git add app/Models
git commit -m "refactor(review): cabut kode mati putaran kedua"
```

---

## Tahap 2 — Runtuhkan dan perbaiki

### Task 5: Tiga nama, satu pertanyaan (T1)

**Files:**
- Modify: `app/Models/SubmissionReviewer.php`
- Test: `tests/Feature/Review/EvaluationPendingTest.php`

**Interfaces:**
- Consumes: `SubmissionReviewer::SETTLED_EVALUATION`
- Produces: `hasSettledEvaluation(): bool` jadi satu-satunya jawaban "evaluasi beres?";
  `canCreateDiscussionThreads()` dan `canParticipateInDiscussions()` **tidak ada lagi**

- [ ] **Step 1: Tulis test yang mengunci satu definisi**

Tambahkan ke `tests/Feature/Review/EvaluationPendingTest.php`:

```php
it('menjawab "evaluasi beres?" dari SETTLED_EVALUATION, bukan daftar literal', function () {
    $submission = FormSubmission::factory()->create();

    $selesai = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'not_required',
        'status' => 'active',
    ]);

    $tertunda = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    expect($selesai->hasSettledEvaluation())->toBeTrue()
        ->and($tertunda->hasSettledEvaluation())->toBeFalse()
        ->and(SubmissionReviewer::SETTLED_EVALUATION)->toContain('not_required');
});
```

- [ ] **Step 2: Jalankan, pastikan LULUS**

Run: `CI ./vendor/bin/pest tests/Feature/Review/EvaluationPendingTest.php`
Expected: LULUS. Ini test karakterisasi — ia merekam perilaku yang sudah benar
supaya peruntuhan di Step 3 punya jaring.

- [ ] **Step 3: Alihkan pemanggil, lalu hapus dua nama yang kalah**

Ganti setiap `->canParticipateInDiscussions()` jadi `->hasSettledEvaluation()`:

```bash
grep -rn 'canParticipateInDiscussions\|canCreateDiscussionThreads' app/ resources/ tests/
```

Lalu hapus kedua method dari `app/Models/SubmissionReviewer.php`.

`hasSettledEvaluation()` yang bertahan karena ia satu-satunya yang diturunkan dari
konstanta — dua lainnya menuliskan ulang daftarnya sebagai literal, jadi status
evaluasi baru akan lolos diam-diam.

**Jangan ikut meruntuhkan `isDraft()` dan `canBeEdited()` di `ReviewFormResponse`.**
Keduanya memang byte-identik, tapi K3 di spec menyatakannya sengaja dibiarkan
terpisah. Meruntuhkannya di sini adalah melanggar keputusan, bukan merapikan.

- [ ] **Step 4: Jalankan gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 891 + 1 = **892 test**.

- [ ] **Step 5: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app tests
git commit -m "refactor(review): runtuhkan tiga nama evaluasi beres jadi satu"
```

---

### Task 6: Status evaluasi yang cuma bisa maju (T2)

Ini bug, bukan kerapian.

**Files:**
- Modify: `app/Models/ReviewFormResponse.php` — `submit()` (baris ~107) dan `updateReviewerEvaluationStatus()` (baris ~182)
- Test: `tests/Feature/Review/EvaluationStatusRecalculationTest.php` (baru)

**Interfaces:**
- Consumes: `SubmissionReviewer::updateEvaluationStatus()`
- Produces: `ReviewFormResponse::updateReviewerEvaluationStatus()` **tidak ada lagi**;
  `submit()` memanggil `updateEvaluationStatus()` milik `SubmissionReviewer`

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Feature/Review/EvaluationStatusRecalculationTest.php
//
// BR-REV-05 — status evaluasi adalah cerminan keadaan sekarang, bukan
// stempel sekali jalan. ReviewFormResponse::updateReviewerEvaluationStatus()
// dulu hanya bisa menulis `completed` dan tak pernah kembali ke `pending`.

use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormResponse;
use App\Models\Reviewer;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\SubmissionReviewer;

/** @return array{0: SubmissionReviewer, 1: ReviewEvaluationForm} */
function recalcFixture(): array
{
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    $reviewer = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $detail->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    return [$reviewer, $form];
}

it('mengembalikan status ke pending saat form wajib baru ditugaskan', function () {
    [$reviewer, $form] = recalcFixture();

    $assignment = $reviewer->assignForm($form->id, true);
    ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $assignment->id,
        'status' => 'submitted',
    ]);
    $reviewer->updateEvaluationStatus();

    expect($reviewer->fresh()->evaluation_status)->toBe('completed');

    // Form wajib kedua: pekerjaannya bertambah, jadi statusnya harus turun.
    $kedua = ReviewEvaluationForm::create([
        'title' => 'Anggaran',
        'form_phase_detail_id' => $form->form_phase_detail_id,
        'is_active' => true,
        'is_required' => true,
        'order' => 2,
    ]);
    $reviewer->assignForm($kedua->id, true);

    expect($reviewer->fresh()->evaluation_status)->toBe('pending');
});
```

- [ ] **Step 2: Jalankan, pastikan LULUS**

Run: `CI ./vendor/bin/pest tests/Feature/Review/EvaluationStatusRecalculationTest.php`
Expected: LULUS — `assignForm()` memakai `updateEvaluationStatus()` yang benar.
Test ini mengunci jalur yang **sudah** benar supaya jalur yang salah terlihat kontras.

- [ ] **Step 3: Tulis test kedua yang GAGAL — lewat `submit()`**

```php
it('menurunkan status lagi saat form wajib ditambah sesudah submit', function () {
    [$reviewer, $form] = recalcFixture();

    $assignment = $reviewer->assignForm($form->id, true);
    $response = ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $assignment->id,
        'status' => 'draft',
    ]);

    $response->submit();

    expect($reviewer->fresh()->evaluation_status)->toBe('completed');

    // Penugasan baru yang belum dikerjakan: submit() sebelumnya tidak boleh
    // meninggalkan stempel `completed` yang tidak bisa dicabut.
    $kedua = ReviewEvaluationForm::create([
        'title' => 'Anggaran',
        'form_phase_detail_id' => $form->form_phase_detail_id,
        'is_active' => true,
        'is_required' => true,
        'order' => 2,
    ]);
    $reviewer->assignForm($kedua->id, true);

    expect($reviewer->fresh()->evaluation_status)->toBe('pending');
});
```

- [ ] **Step 4: Jalankan, pastikan GAGAL kalau `submit()` belum benar**

Run: `CI ./vendor/bin/pest tests/Feature/Review/EvaluationStatusRecalculationTest.php`

Kalau kedua test LULUS sejak awal, `submit()` ternyata sudah aman lewat jalur lain.
**Berhenti dan baca ulang** `updateReviewerEvaluationStatus()` sebelum mengubah apa
pun — jangan memperbaiki yang tidak rusak.

- [ ] **Step 5: Hapus duplikatnya, pakai yang satu**

Di `app/Models/ReviewFormResponse.php`, hapus seluruh
`updateReviewerEvaluationStatus()` dan ganti pemanggilnya di `submit()`:

```php
    public function submit(): bool
    {
        if (!$this->canBeSubmitted()) {
            return false;
        }

        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // BR-REV-05 — satu definisi hitung ulang. Versi lama di sini hanya bisa
        // menulis `completed` dan tak pernah kembali ke `pending`.
        $this->reviewerFormAssignment->submissionReviewer->updateEvaluationStatus();

        return true;
    }
```

- [ ] **Step 6: Jalankan gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 892 + 2 = **894 test**.

- [ ] **Step 7: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app tests
git commit -m "fix(review): hitung ulang status evaluasi dengan satu definisi"
```

---

### Task 7: `formatEmailValue()` tidak melakukan apa pun (T3)

**Files:**
- Modify: `app/Models/ReviewFormFieldResponse.php`

**Interfaces:**
- Consumes: —
- Produces: `formatEmailValue()` tidak ada lagi; `'email'` jatuh ke cabang `default`

- [ ] **Step 1: Buktikan kedua cabangnya sama**

```bash
grep -n 'FILTER_VALIDATE_EMAIL' -A 1 app/Models/ReviewFormFieldResponse.php
```
Expected: `return filter_var($this->value, FILTER_VALIDATE_EMAIL) ? $this->value : $this->value;`

Kalau kedua cabangnya ternyata **tidak** sama, tugas ini batal — laporkan.

- [ ] **Step 2: Hapus method dan cabangnya**

Hapus `formatEmailValue()`, lalu hapus `'email' => $this->formatEmailValue(),` dari
`match` di `getFormattedValueAttribute()`. `'email'` kini jatuh ke
`default => $this->value` — nilainya identik dengan sebelumnya.

- [ ] **Step 3: Jalankan gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 894 test, jumlahnya tidak berubah.

- [ ] **Step 4: Commit**

```bash
git add app/Models/ReviewFormFieldResponse.php
git commit -m "refactor(review): cabut formatEmailValue yang kedua cabangnya sama"
```

---

## Tahap 3 — Pindahkan ke Actions, Queries, dan Support

> **Menyimpang dari Peta Berkas spec, dengan alasan.** Spec mengusulkan
> `Actions/Review/RecalculateEvaluationStatus`. Saat rencana ini ditulis ketahuan
> itu salah bentuk: hitung ulang status dipicu **di dalam** Action lain, jadi kalau
> ia Action juga, satu penugasan menulis **dua** entri audit untuk satu kejadian.
> Ia dipindahkan ke `app/Support/Review/EvaluationStatusRecalculator.php` mengikuti
> preseden `RequiredFieldGuard` — memulihkan invarian bukan use case.
>
> `saveFieldResponse()` dan `getFieldResponse()` **tidak jadi** dipindahkan: keduanya
> delegasi satu baris ke relasi, dan memindahkannya hanya menambah lompatan. Itu
> persis kesalahan lima kelas gelombang 2 yang dibuat lalu dihapus lagi.

### Task 8: Dua Query — kesiapan form wajib dan persentase penyelesaian

Dikerjakan lebih dulu karena Task 9 memakainya.

**Files:**
- Create: `app/Queries/Review/RequiredFormsCompletion.php`
- Create: `app/Queries/Review/EvaluationFormCompletion.php`
- Test: `tests/Feature/Review/RequiredFormsCompletionTest.php`

**Interfaces:**
- Consumes: `ReviewerFormAssignment::scopeRequired()`, `scopeActive()`
- Produces:
  - `RequiredFormsCompletion::run(SubmissionReviewer $reviewer): bool`
  - `EvaluationFormCompletion::run(ReviewFormResponse $response): int`

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Feature/Review/RequiredFormsCompletionTest.php

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormResponse;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Queries\Review\RequiredFormsCompletion;

/** @return array{0: SubmissionReviewer, 1: FormPhaseDetail} */
function completionFixture(): array
{
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    $reviewer = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    return [$reviewer, $detail];
}

function completionForm(FormPhaseDetail $detail, int $order): ReviewEvaluationForm
{
    return ReviewEvaluationForm::create([
        'title' => "Form {$order}",
        'form_phase_detail_id' => $detail->id,
        'is_active' => true,
        'is_required' => true,
        'order' => $order,
    ]);
}

it('menganggap reviewer tanpa form wajib sudah selesai', function () {
    [$reviewer] = completionFixture();

    expect(RequiredFormsCompletion::run($reviewer))->toBeTrue();
});

it('menyatakan belum selesai selama satu form wajib belum tersubmit', function () {
    [$reviewer, $detail] = completionFixture();

    $reviewer->assignForm(completionForm($detail, 1)->id, true);

    expect(RequiredFormsCompletion::run($reviewer->fresh()))->toBeFalse();
});

it('menyatakan selesai setelah seluruh form wajib tersubmit', function () {
    [$reviewer, $detail] = completionFixture();

    $assignment = $reviewer->assignForm(completionForm($detail, 1)->id, true);
    ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $assignment->id,
        'status' => 'submitted',
    ]);

    expect(RequiredFormsCompletion::run($reviewer->fresh()))->toBeTrue();
});

it('mengabaikan penugasan yang sudah dicabut', function () {
    [$reviewer, $detail] = completionFixture();

    $assignment = $reviewer->assignForm(completionForm($detail, 1)->id, true);
    $assignment->update(['is_active' => false]);

    expect(RequiredFormsCompletion::run($reviewer->fresh()))->toBeTrue();
});
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `CI ./vendor/bin/pest tests/Feature/Review/RequiredFormsCompletionTest.php`
Expected: GAGAL — `Class "App\Queries\Review\RequiredFormsCompletion" not found`.

- [ ] **Step 3: Tulis `RequiredFormsCompletion`**

```php
<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\ReviewFormResponse;
use App\Models\SubmissionReviewer;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-05 — apakah seluruh form wajib reviewer ini sudah tersubmit. Tanpa form
 * wajib jawabannya "sudah": menunggu yang tidak ada akan mengunci siklus
 * berikutnya selamanya.
 *
 * @method static bool run(SubmissionReviewer $reviewer)
 */
class RequiredFormsCompletion implements ChangesNoState
{
    use AsAction;

    public function handle(SubmissionReviewer $reviewer): bool
    {
        return $reviewer->reviewerFormAssignments()
            ->required()
            ->active()
            ->whereDoesntHave('reviewFormResponse', function (Builder $query): void {
                $query->where('status', 'submitted');
            })
            ->doesntExist();
    }
}
```

Kalau PHPStan menuntut generic pada `Builder`, tulis `Builder<ReviewFormResponse>` —
**jangan** melonggarkan tipenya jadi `mixed` atau membuang type hint-nya.

- [ ] **Step 4: Tulis `EvaluationFormCompletion`**

```php
<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\ReviewFormResponse;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Persentase field yang sudah terisi pada satu jawaban evaluasi. Form tanpa
 * field dihitung 100% — bukan 0% — supaya ia tidak menahan apa pun.
 *
 * @method static int run(ReviewFormResponse $response)
 */
class EvaluationFormCompletion implements ChangesNoState
{
    use AsAction;

    public function handle(ReviewFormResponse $response): int
    {
        $totalFields = $response->reviewerFormAssignment
            ->reviewEvaluationForm
            ->reviewFormFields()
            ->count();

        if ($totalFields === 0) {
            return 100;
        }

        $filled = $response->reviewFormFieldResponses()
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->count();

        return min(100, (int) round($filled / $totalFields * 100));
    }
}
```

`(int) round(...)` adalah konversi float→int yang memang diperlukan, bukan cast
untuk membungkam PHPStan — ia tetap boleh.

- [ ] **Step 5: Alihkan pemanggil `getCompletionPercentage()`**

```bash
grep -rn 'getCompletionPercentage' app/ resources/ tests/
```

Ganti tiap `$response->getCompletionPercentage()` jadi
`EvaluationFormCompletion::run($response)`, lalu hapus method-nya dari
`app/Models/ReviewFormResponse.php`.

`hasAllRequiredFormsCompleted()` di `SubmissionReviewer` **belum** dihapus di Task
ini — ia masih dipakai `updateEvaluationStatus()`. Task 9 yang mencabutnya.

- [ ] **Step 6: Jalankan gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 894 + 4 = **898 test**.

- [ ] **Step 7: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app tests
git commit -m "refactor(review): pindahkan dua kueri kesiapan evaluasi ke Queries"
```

---

### Task 9: `EvaluationStatusRecalculator`

**Files:**
- Create: `app/Support/Review/EvaluationStatusRecalculator.php`
- Modify: `app/Models/SubmissionReviewer.php`
- Modify: `app/Models/ReviewFormResponse.php`
- Test: `tests/Feature/Review/EvaluationStatusRecalculationTest.php`

**Interfaces:**
- Consumes: `RequiredFormsCompletion::run(SubmissionReviewer $reviewer): bool`
- Produces: `EvaluationStatusRecalculator::for(SubmissionReviewer $reviewer): void`;
  `SubmissionReviewer::updateEvaluationStatus()`, `hasAssignedForms()`, dan
  `hasAllRequiredFormsCompleted()` **tidak ada lagi**

- [ ] **Step 1: Tulis kelasnya**

```php
<?php

namespace App\Support\Review;

use App\Models\SubmissionReviewer;
use App\Queries\Review\RequiredFormsCompletion;

/**
 * BR-REV-05 — menyelaraskan `evaluation_status` dengan penugasan yang berlaku.
 *
 * `final class` statis mengikuti preseden `RequiredFieldGuard`: memulihkan
 * invarian bukan use case. Kalau ia jadi Action, satu penugasan akan menulis
 * dua entri audit untuk satu kejadian yang sama.
 */
final class EvaluationStatusRecalculator
{
    public static function for(SubmissionReviewer $reviewer): void
    {
        $reviewer->update(['evaluation_status' => self::statusFor($reviewer)]);
    }

    private static function statusFor(SubmissionReviewer $reviewer): string
    {
        // Tidak ada yang ditunggu dari reviewer ini — BR-FE-06 memperlakukannya
        // sebagai selesai, bukan menggantungnya di `pending` selamanya.
        if (!$reviewer->activeReviewerFormAssignments()->exists()) {
            return 'not_required';
        }

        return RequiredFormsCompletion::run($reviewer) ? 'completed' : 'pending';
    }
}
```

- [ ] **Step 2: Alihkan seluruh pemanggil**

```bash
grep -rn 'updateEvaluationStatus' app/ tests/
```

Ganti tiap `$reviewer->updateEvaluationStatus()` jadi
`EvaluationStatusRecalculator::for($reviewer)`. Pemanggil yang diketahui:
`SubmissionReviewer::assignForm()`, `SubmissionReviewer::removeFormAssignment()`,
dan `ReviewFormResponse::submit()` (hasil Task 6).

- [ ] **Step 3: Hapus tiga method dari `SubmissionReviewer`**

`updateEvaluationStatus()`, `hasAssignedForms()`, dan
`hasAllRequiredFormsCompleted()`. Ketiganya kehilangan pemanggil terakhirnya di
Step 2 — `hasAssignedForms()` dan `hasAllRequiredFormsCompleted()` hanya dipakai
`updateEvaluationStatus()`.

Jalankan centang K2 untuk ketiganya sebelum menghapus. Kalau salah satunya ternyata
punya pemanggil lain, **biarkan** dan catat.

- [ ] **Step 4: Jalankan gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 898 test. Test Task 6 (`EvaluationStatusRecalculationTest`) adalah
jaringnya — kalau ia merah, perilaku hitung ulangnya berubah dan itu regresi.

- [ ] **Step 5: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app tests
git commit -m "refactor(review): pindahkan hitung ulang status evaluasi ke Support"
```

---

### Task 10: Dua Action penugasan form

**Files:**
- Create: `app/Actions/Review/AssignEvaluationForm.php`
- Create: `app/Actions/Review/RemoveEvaluationFormAssignment.php`
- Modify: `app/Models/SubmissionReviewer.php`
- Modify: `app/Http/Controllers/ReviewController.php`
- Test: `tests/Feature/Review/EvaluationFormAssignmentTest.php`

**Interfaces:**
- Consumes: `EvaluationStatusRecalculator::for()`
- Produces:
  - `AssignEvaluationForm::run(SubmissionReviewer $reviewer, int $formId, bool $isRequired = true, ?DateTimeInterface $dueDate = null): ReviewerFormAssignment`
  - `RemoveEvaluationFormAssignment::run(SubmissionReviewer $reviewer, int $formId): bool`
  - `SubmissionReviewer::assignForm()` dan `removeFormAssignment()` **tidak ada lagi**

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Feature/Review/EvaluationFormAssignmentTest.php

use App\Actions\Review\AssignEvaluationForm;
use App\Actions\Review\RemoveEvaluationFormAssignment;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ReviewEvaluationForm;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;

/** @return array{0: SubmissionReviewer, 1: ReviewEvaluationForm} */
function assignmentFixture(): array
{
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    $reviewer = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'not_required',
        'status' => 'active',
    ]);

    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $detail->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    return [$reviewer, $form];
}

it('menugaskan form dan menurunkan status evaluasi jadi pending', function () {
    [$reviewer, $form] = assignmentFixture();

    $assignment = AssignEvaluationForm::run($reviewer, $form->id);

    expect($assignment->review_evaluation_form_id)->toBe($form->id)
        ->and($assignment->is_required)->toBeTrue()
        ->and($reviewer->fresh()->evaluation_status)->toBe('pending');
});

it('mencabut penugasan dan mengembalikan status jadi not_required', function () {
    [$reviewer, $form] = assignmentFixture();

    AssignEvaluationForm::run($reviewer, $form->id);

    expect(RemoveEvaluationFormAssignment::run($reviewer, $form->id))->toBeTrue()
        ->and($reviewer->fresh()->evaluation_status)->toBe('not_required');
});

it('menjawab false saat tidak ada penugasan yang dicabut', function () {
    [$reviewer, $form] = assignmentFixture();

    expect(RemoveEvaluationFormAssignment::run($reviewer, $form->id))->toBeFalse();
});
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `CI ./vendor/bin/pest tests/Feature/Review/EvaluationFormAssignmentTest.php`
Expected: GAGAL — kelasnya belum ada.

- [ ] **Step 3: Tulis `AssignEvaluationForm`**

```php
<?php

namespace App\Actions\Review;

use App\Models\ReviewerFormAssignment;
use App\Models\SubmissionReviewer;
use App\Support\Audit\AuditTrail;
use App\Support\Review\EvaluationStatusRecalculator;
use DateTimeInterface;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menugaskan satu form evaluasi kepada seorang reviewer.
 *
 * @method static ReviewerFormAssignment run(SubmissionReviewer $reviewer, int $formId, bool $isRequired = true, ?DateTimeInterface $dueDate = null)
 */
class AssignEvaluationForm
{
    use AsAction;

    public function handle(
        SubmissionReviewer $reviewer,
        int $formId,
        bool $isRequired = true,
        ?DateTimeInterface $dueDate = null,
    ): ReviewerFormAssignment {
        $assignment = $reviewer->reviewerFormAssignments()->create([
            'review_evaluation_form_id' => $formId,
            'is_required' => $isRequired,
            'due_date' => $dueDate,
            'assigned_at' => now(),
        ]);

        // BR-REV-05 — pekerjaannya bertambah, jadi statusnya ikut turun.
        EvaluationStatusRecalculator::for($reviewer);

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Form evaluasi ditugaskan', $assignment);

        return $assignment;
    }
}
```

Kalau PHPStan tidak bisa menyimpulkan tipe `$assignment`, perbaiki generic relasi
`SubmissionReviewer::reviewerFormAssignments(): HasMany<ReviewerFormAssignment, $this>`
— **jangan** menambahkan inline `@var`.

- [ ] **Step 4: Tulis `RemoveEvaluationFormAssignment`**

```php
<?php

namespace App\Actions\Review;

use App\Models\SubmissionReviewer;
use App\Support\Audit\AuditTrail;
use App\Support\Review\EvaluationStatusRecalculator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Mencabut penugasan satu form evaluasi dari seorang reviewer.
 *
 * @method static bool run(SubmissionReviewer $reviewer, int $formId)
 */
class RemoveEvaluationFormAssignment
{
    use AsAction;

    public function handle(SubmissionReviewer $reviewer, int $formId): bool
    {
        $deleted = $reviewer->reviewerFormAssignments()
            ->where('review_evaluation_form_id', $formId)
            ->delete();

        if ($deleted === 0) {
            return false;
        }

        EvaluationStatusRecalculator::for($reviewer);

        AuditTrail::record('Penugasan form evaluasi dicabut', $reviewer);

        return true;
    }
}
```

- [ ] **Step 5: Alihkan pemanggil dan hapus method modelnya**

```bash
grep -rn 'assignForm\|removeFormAssignment' app/ tests/
```

Pemanggil produksi yang diketahui ada di
`ReviewController::autoAssignEvaluationForms()`. Ganti
`$submissionReviewer->assignForm($form->id, true, EvaluationDueDate::run($form))`
jadi `AssignEvaluationForm::run($submissionReviewer, $form->id, true, EvaluationDueDate::run($form))`.

Lalu hapus `assignForm()` dan `removeFormAssignment()` dari
`app/Models/SubmissionReviewer.php`.

**Test yang memakai `assignForm()` ikut dialihkan** — termasuk fixture Task 6 dan
Task 8. Jangan biarkan test memanggil method yang sudah tidak ada.

- [ ] **Step 6: Jalankan gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 898 + 3 = **901 test**.

`AuditCoverageTest` ikut jalan di sini — kedua Action baru menulis
`AuditTrail::record()`, jadi ia hijau. Kalau merah, periksa ejaan
`AuditTrail::record` di kedua berkas.

- [ ] **Step 7: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app tests
git commit -m "refactor(review): pindahkan penugasan form evaluasi ke Actions"
```

---

### Task 11: Dua Action daur hidup jawaban evaluasi

**Files:**
- Create: `app/Actions/Review/SubmitEvaluationForm.php`
- Create: `app/Actions/Review/LockEvaluationForm.php`
- Modify: `app/Models/ReviewFormResponse.php`
- Test: `tests/Feature/Review/EvaluationFormLifecycleTest.php`

**Interfaces:**
- Consumes: `EvaluationStatusRecalculator::for()`, `ReviewFormResponse::canBeSubmitted()`,
  `ReviewFormResponse::isLocked()`
- Produces:
  - `SubmitEvaluationForm::run(ReviewFormResponse $response): bool`
  - `LockEvaluationForm::run(ReviewFormResponse $response): bool`
  - `ReviewFormResponse::submit()` dan `lock()` **tidak ada lagi**

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Feature/Review/EvaluationFormLifecycleTest.php

use App\Actions\Review\AssignEvaluationForm;
use App\Actions\Review\LockEvaluationForm;
use App\Actions\Review\SubmitEvaluationForm;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormResponse;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;

function lifecycleResponse(string $status = 'draft'): ReviewFormResponse
{
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    $reviewer = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $detail->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    $assignment = AssignEvaluationForm::run($reviewer, $form->id);

    return ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $assignment->id,
        'status' => $status,
    ]);
}

it('menyubmit jawaban dan menyelesaikan evaluasi reviewernya', function () {
    $response = lifecycleResponse();

    expect(SubmitEvaluationForm::run($response))->toBeTrue()
        ->and($response->fresh()->status)->toBe('submitted')
        ->and($response->fresh()->submitted_at)->not->toBeNull()
        ->and($response->reviewerFormAssignment->submissionReviewer->fresh()->evaluation_status)
        ->toBe('completed');
});

it('menolak submit jawaban yang sudah terkunci', function () {
    $response = lifecycleResponse('locked');

    expect(SubmitEvaluationForm::run($response))->toBeFalse()
        ->and($response->fresh()->status)->toBe('locked');
});

it('mengunci jawaban sekali saja', function () {
    $response = lifecycleResponse();

    expect(LockEvaluationForm::run($response))->toBeTrue()
        ->and($response->fresh()->status)->toBe('locked')
        ->and(LockEvaluationForm::run($response->fresh()))->toBeFalse();
});
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `CI ./vendor/bin/pest tests/Feature/Review/EvaluationFormLifecycleTest.php`
Expected: GAGAL — kelasnya belum ada.

- [ ] **Step 3: Tulis `SubmitEvaluationForm`**

```php
<?php

namespace App\Actions\Review;

use App\Models\ReviewFormResponse;
use App\Support\Audit\AuditTrail;
use App\Support\Review\EvaluationStatusRecalculator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menyubmit satu jawaban evaluasi. Jawaban yang belum lengkap atau sudah
 * terkunci ditolak — syaratnya dipegang `canBeSubmitted()`.
 *
 * @method static bool run(ReviewFormResponse $response)
 */
class SubmitEvaluationForm
{
    use AsAction;

    public function handle(ReviewFormResponse $response): bool
    {
        if (!$response->canBeSubmitted()) {
            return false;
        }

        $response->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // BR-REV-05 — satu definisi hitung ulang, dipakai seluruh pemicu.
        EvaluationStatusRecalculator::for($response->reviewerFormAssignment->submissionReviewer);

        AuditTrail::record('Evaluasi disubmit', $response);

        return true;
    }
}
```

- [ ] **Step 4: Tulis `LockEvaluationForm`**

```php
<?php

namespace App\Actions\Review;

use App\Models\ReviewFormResponse;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Mengunci satu jawaban evaluasi, biasanya karena tenggatnya lewat.
 * Mengunci yang sudah terkunci bukan galat — ia cuma tidak melakukan apa-apa.
 *
 * @method static bool run(ReviewFormResponse $response)
 */
class LockEvaluationForm
{
    use AsAction;

    public function handle(ReviewFormResponse $response): bool
    {
        if ($response->isLocked()) {
            return false;
        }

        $response->update([
            'status' => 'locked',
            'locked_at' => now(),
        ]);

        AuditTrail::record('Evaluasi dikunci', $response);

        return true;
    }
}
```

- [ ] **Step 5: Alihkan pemanggil dan hapus method modelnya**

```bash
grep -rn -- '->submit()\|->lock()' app/ tests/
```

Perhatikan: `submit` dan `lock` kata umum. Pakai bentuk panggilan `->submit()` dan
`->lock()` **beserta** memeriksa penerimanya bertipe `ReviewFormResponse` — jangan
mengganti `->submit()` milik objek lain.

Pemanggil yang diketahui ada di `ReviewFormResponseController` dan
`ReviewerFormAssignment::autoLockIfOverdue()`. Yang terakhir jadi:

```php
    public function autoLockIfOverdue(): void
    {
        if (!$this->isOverdue() || !$this->reviewFormResponse) {
            return;
        }

        LockEvaluationForm::run($this->reviewFormResponse);
    }
```

Lalu hapus `submit()` dan `lock()` dari `app/Models/ReviewFormResponse.php`.

- [ ] **Step 6: Jalankan gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 901 + 3 = **904 test**.

- [ ] **Step 7: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app tests
git commit -m "refactor(review): pindahkan submit dan lock evaluasi ke Actions"
```

---

## Tahap 4 — Bedah dua berkas yang melanggar bentuk

### Task 12: `FieldResponseFormatter` — dan buang HTML dari model (T4)

**Ini mengubah tampilan.** `formatUrlValue()` sekarang mengembalikan
`<a href='…'>…</a>` yang **tidak pernah** dirender `v-html`, jadi reviewer melihat
markup mentah dan ringkasan teks kemasukan tag. Sesudah Task ini, yang tampil
URL-nya saja.

**Files:**
- Create: `app/Support/Review/FieldResponseFormatter.php`
- Modify: `app/Models/ReviewFormFieldResponse.php`
- Modify: `tests/Support/fixtures.php`
- Test: `tests/Feature/Review/FieldResponseFormatterTest.php`

**Interfaces:**
- Consumes: `ReviewFormFieldResponse::hasValue()`
- Produces: `FieldResponseFormatter::readable(ReviewFormFieldResponse $response): string`;
  `getFormattedValueAttribute()` mendelegasikan ke sana; `formatCheckboxValue()`,
  `formatSelectValue()`, `formatNumberValue()`, `formatDateValue()`, dan
  `formatUrlValue()` **tidak ada lagi**

- [ ] **Step 1: Tulis test yang mengunci perilaku baru**

```php
<?php

// tests/Feature/Review/FieldResponseFormatterTest.php
//
// Model tidak merakit HTML. `formatted_value` dipakai layar Inertia DAN
// ringkasan teks polos di ReviewSummary, jadi keluarannya wajib terbaca di
// keduanya — keputusan menjadikannya tautan milik layar.

use App\Support\Review\FieldResponseFormatter;

it('mengembalikan string kosong saat tidak ada nilai', function () {
    expect(FieldResponseFormatter::readable(evaluationFieldResponse('text', null)))->toBe('');
});

it('tidak pernah mengembalikan HTML untuk URL', function () {
    $hasil = FieldResponseFormatter::readable(evaluationFieldResponse('url', 'https://itk.ac.id'));

    expect($hasil)->toBe('https://itk.ac.id')
        ->and($hasil)->not->toContain('<a ');
});

it('menggabungkan pilihan checkbox jadi satu baris', function () {
    expect(FieldResponseFormatter::readable(evaluationFieldResponse('checkbox', '["A","B"]')))
        ->toBe('A, B');
});

it('memformat angka dengan dua desimal', function () {
    expect(FieldResponseFormatter::readable(evaluationFieldResponse('number', '3.5')))->toBe('3.50');
});

it('mengembalikan nilai apa adanya untuk tipe tanpa aturan khusus', function () {
    expect(FieldResponseFormatter::readable(evaluationFieldResponse('email', 'a@itk.ac.id')))
        ->toBe('a@itk.ac.id');
});
```

`evaluationFieldResponse()` belum ada — **tulis lebih dulu** di
`tests/Support/fixtures.php` (sudah ter-autoload lewat `composer.json`). Keduanya,
`ReviewFormField` dan `ReviewFormFieldResponse`, tidak punya factory dan rantai
induknya tujuh model dalam, jadi satu fixture bersama lebih murah daripada dua
factory:

```php
/**
 * Satu jawaban field evaluasi lengkap dengan rantai induknya, supaya test
 * formatter dan validator tidak masing-masing merakit tujuh model.
 */
function evaluationFieldResponse(string $fieldType, ?string $value, bool $isRequired = false): ReviewFormFieldResponse
{
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create(['form_phase_detail_id' => $detail->id]);

    $reviewer = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    $form = ReviewEvaluationForm::create([
        'title' => 'Substansi',
        'form_phase_detail_id' => $detail->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    $assignment = ReviewerFormAssignment::create([
        'submission_reviewer_id' => $reviewer->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
        'assigned_at' => now(),
        'is_active' => true,
    ]);

    $response = ReviewFormResponse::create([
        'reviewer_form_assignment_id' => $assignment->id,
        'status' => 'draft',
    ]);

    $field = ReviewFormField::create([
        'review_evaluation_form_id' => $form->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => $fieldType])->id,
        'label' => 'Uji',
        'is_required' => $isRequired,
        'order' => 1,
    ]);

    return ReviewFormFieldResponse::create([
        'review_form_response_id' => $response->id,
        'review_form_field_id' => $field->id,
        'value' => $value,
    ]);
}
```

Tambahkan import yang belum ada di `fixtures.php`: `FieldType`,
`ReviewEvaluationForm`, `ReviewFormField`, `ReviewFormFieldResponse`,
`ReviewFormResponse`, `ReviewerFormAssignment`, `Reviewer`, `SubmissionReviewer`.

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `CI ./vendor/bin/pest tests/Feature/Review/FieldResponseFormatterTest.php`
Expected: GAGAL — kelasnya belum ada.

- [ ] **Step 3: Tulis `FieldResponseFormatter`**

```php
<?php

namespace App\Support\Review;

use App\Models\ReviewFormFieldResponse;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Nilai jawaban evaluasi dalam bentuk yang terbaca manusia. Keluarannya teks
 * polos: ia dipakai layar Inertia sekaligus ringkasan teks, jadi tag HTML di
 * sini muncul sebagai sampah di salah satu dari keduanya.
 */
final class FieldResponseFormatter
{
    public static function readable(ReviewFormFieldResponse $response): string
    {
        if (!$response->hasValue()) {
            return '';
        }

        $value = (string) $response->value;

        return match ($response->reviewFormField->fieldType->name ?? '') {
            'checkbox' => self::checkbox($value),
            'radio', 'select' => self::optionLabel($response, $value),
            'number' => is_numeric($value) ? number_format((float) $value, 2) : $value,
            'date' => self::date($value),
            default => $value,
        };
    }

    private static function checkbox(string $value): string
    {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? implode(', ', $decoded) : $value;
    }

    private static function optionLabel(ReviewFormFieldResponse $response, string $value): string
    {
        $option = $response->reviewFormField->reviewFormFieldOptions()
            ->where('value', $value)
            ->orWhere('label', $value)
            ->first();

        return $option?->label ?? $value;
    }

    private static function date(string $value): string
    {
        try {
            return Carbon::parse($value)->format('d M Y');
        } catch (InvalidFormatException) {
            return $value;
        }
    }
}
```

Menangkap `InvalidFormatException`, bukan `\Exception`: menelan seluruh Throwable
menyembunyikan galat yang bukan soal format.

- [ ] **Step 4: Sederhanakan accessor-nya**

Di `app/Models/ReviewFormFieldResponse.php`, ganti seluruh
`getFormattedValueAttribute()` beserta lima method `format*` jadi:

```php
    public function getFormattedValueAttribute(): string
    {
        return FieldResponseFormatter::readable($this);
    }
```

Accessor-nya tetap supaya `formatted_value` di layar dan di `ReviewSummary` tidak
ikut berubah bentuk.

- [ ] **Step 5: Jalankan gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 904 + 5 = **909 test**.

Kalau ada test lain yang merah karena mengharapkan `<a href`, **jangan longgarkan
`FieldResponseFormatter`** — perbarui test itu; perilaku barulah yang benar.

- [ ] **Step 6: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app tests database
git commit -m "refactor(review): pindahkan format jawaban ke Support dan buang HTML"
```

---

### Task 13: `FieldResponseValidator` (T5)

`validate()` melanggar tiga plafon sekaligus: terlalu panjang, terlalu kompleks,
dan `validateCustomRules()` terlalu kompleks. **Memindahkannya utuh tidak cukup** —
yang menurunkan angkanya adalah memecah `switch`-nya.

**Files:**
- Create: `app/Support/Review/FieldResponseValidator.php`
- Modify: `app/Models/ReviewFormFieldResponse.php`
- Modify: `app/Models/ReviewFormField.php` (docblock `$validation_rules`)
- Test: `tests/Feature/Review/FieldResponseValidatorTest.php`

**Interfaces:**
- Consumes: `ReviewFormFieldResponse::hasValue()`, `ReviewFormField::hasValidationRules()`
- Produces: `FieldResponseValidator::errorsFor(ReviewFormFieldResponse $response): array<int, string>`;
  `ReviewFormFieldResponse::validate()` dan `validateCustomRules()` **tidak ada lagi**

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Feature/Review/FieldResponseValidatorTest.php

use App\Support\Review\FieldResponseValidator;

it('menuntut field wajib yang kosong', function () {
    $response = evaluationFieldResponse('text', null);
    $response->reviewFormField->update(['is_required' => true]);

    expect(FieldResponseValidator::errorsFor($response->fresh()))
        ->toBe(["Field 'Uji' is required"]);
});

it('mendiamkan field tidak wajib yang kosong', function () {
    expect(FieldResponseValidator::errorsFor(evaluationFieldResponse('text', null)))->toBe([]);
});

it('tidak menerapkan aturan tipe pada nilai kosong', function () {
    $response = evaluationFieldResponse('email', null);

    expect(FieldResponseValidator::errorsFor($response))->toBe([]);
});

it('menolak email yang bukan email', function () {
    expect(FieldResponseValidator::errorsFor(evaluationFieldResponse('email', 'bukan-email')))
        ->toBe(["Field 'Uji' must be a valid email address"]);
});

it('menolak angka yang bukan angka', function () {
    expect(FieldResponseValidator::errorsFor(evaluationFieldResponse('number', 'dua')))
        ->toBe(["Field 'Uji' must be a number"]);
});

it('menerapkan aturan panjang kustom', function () {
    $response = evaluationFieldResponse('text', 'ab');
    $response->reviewFormField->update(['validation_rules' => ['min_length' => 5]]);

    expect(FieldResponseValidator::errorsFor($response->fresh()))
        ->toBe(["Field 'Uji' must be at least 5 characters"]);
});

it('mengumpulkan galat tipe dan galat kustom sekaligus', function () {
    $response = evaluationFieldResponse('email', 'ab');
    $response->reviewFormField->update(['validation_rules' => ['min_length' => 5]]);

    expect(FieldResponseValidator::errorsFor($response->fresh()))->toHaveCount(2);
});
```

`evaluationFieldResponse()` sudah tinggal di `tests/Support/fixtures.php` sejak
Task 12, jadi berkas ini bisa dijalankan sendirian tanpa memuat berkas test lain.

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `CI ./vendor/bin/pest tests/Feature/Review/FieldResponseValidatorTest.php`
Expected: GAGAL — kelasnya belum ada.

- [ ] **Step 3: Sempitkan tipe `validation_rules` lebih dulu**

Di `app/Models/ReviewFormField.php`, ganti docblock propertinya:

```php
 * @property array<string, int|float|string>|null $validation_rules
```

Ini yang membuat `FieldResponseValidator` bisa membandingkan nilainya tanpa cast.
Urutannya penting: menulis validator lebih dulu akan memancing cast yang dilarang
aturan 5.

- [ ] **Step 4: Tulis `FieldResponseValidator`**

```php
<?php

namespace App\Support\Review;

use App\Models\ReviewFormField;
use App\Models\ReviewFormFieldResponse;

/**
 * Galat validasi satu jawaban evaluasi. `final class` statis mengikuti preseden
 * `RequiredFieldGuard`: sebuah guard bukan use case, jadi ia tidak berhak jadi
 * Action.
 */
final class FieldResponseValidator
{
    /** @return array<int, string> */
    public static function errorsFor(ReviewFormFieldResponse $response): array
    {
        $field = $response->reviewFormField;

        // Nilai kosong hanya bisa melanggar satu hal. Aturan tipe dan aturan
        // kustom tidak punya apa pun untuk diperiksa.
        if (!$response->hasValue()) {
            return $field->is_required ? ["Field '{$field->label}' is required"] : [];
        }

        $value = (string) $response->value;

        return [
            ...self::typeErrors($field, $value),
            ...self::customRuleErrors($field, $value),
        ];
    }

    /** @return array<int, string> */
    private static function typeErrors(ReviewFormField $field, string $value): array
    {
        $label = $field->label;

        return match ($field->fieldType->name ?? '') {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? ["Field '{$label}' must be a valid email address"] : [],
            'url' => filter_var($value, FILTER_VALIDATE_URL) === false
                ? ["Field '{$label}' must be a valid URL"] : [],
            'number' => is_numeric($value) ? [] : ["Field '{$label}' must be a number"],
            default => [],
        };
    }

    /** @return array<int, string> */
    private static function customRuleErrors(ReviewFormField $field, string $value): array
    {
        if (!$field->hasValidationRules()) {
            return [];
        }

        $errors = [];

        foreach ($field->validation_rules ?? [] as $rule => $limit) {
            $error = self::ruleError($rule, $limit, $value, $field->label);

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    private static function ruleError(string $rule, int|float|string $limit, string $value, string $label): ?string
    {
        return match ($rule) {
            'min_length' => mb_strlen($value) < (int) $limit
                ? "Field '{$label}' must be at least {$limit} characters" : null,
            'max_length' => mb_strlen($value) > (int) $limit
                ? "Field '{$label}' must not exceed {$limit} characters" : null,
            'min_value' => is_numeric($value) && (float) $value < (float) $limit
                ? "Field '{$label}' must be at least {$limit}" : null,
            'max_value' => is_numeric($value) && (float) $value > (float) $limit
                ? "Field '{$label}' must not exceed {$limit}" : null,
            default => null,
        };
    }
}
```

`mb_strlen`, bukan `strlen`: label dan jawaban boleh berisi huruf beraksen, dan
`strlen` menghitung byte sehingga batasnya jadi lebih ketat dari yang dimaksud.
Ini perubahan perilaku yang **disengaja**; kalau ada test lama yang mengunci
hitungan byte, perbarui test itu.

- [ ] **Step 5: Cabut kedua method dari model**

Hapus `validate()` dan `validateCustomRules()` dari
`app/Models/ReviewFormFieldResponse.php`, lalu alihkan pemanggilnya:

```bash
grep -rn -- '->validate()' app/ resources/ tests/
```

Ganti tiap `$fieldResponse->validate()` jadi
`FieldResponseValidator::errorsFor($fieldResponse)`.

- [ ] **Step 6: Jalankan gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 909 + 7 = **916 test**.

- [ ] **Step 7: Buktikan ketiga pelanggaran bentuknya hilang**

```bash
cat > /tmp/why.php <<'PHP'
<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$corpus = App\Support\Quality\SourceTree::measured(base_path());
$limits = new App\Support\Quality\HardLimits(
    new App\Support\Quality\BudgetFile(base_path('quality-budget.txt')),
    array_map(fn ($p) => base_path($p), $corpus),
);
foreach (['SubmissionReviewer','ReviewFormResponse','ReviewFormFieldResponse'] as $m) {
    $r = $limits->violations(base_path("app/Models/$m.php"));
    printf("%-26s %s\n", $m, $r === [] ? '(bersih)' : implode(' | ', $r));
}
PHP
cp /tmp/why.php ./why-tmp.php && CI php why-tmp.php ; rm -f why-tmp.php
```

Expected: `ReviewFormFieldResponse` tidak lagi menyebut `validate()`. Ia mungkin
masih menyebut *ukuran berkas* — itu giliran Task 14.

- [ ] **Step 8: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app tests
git commit -m "refactor(review): pecah validasi jawaban evaluasi ke Support"
```

---

### Task 14: Turunkan ukuran berkas dan prosa komentar

**Files:**
- Modify: `app/Models/SubmissionReviewer.php`
- Modify: `app/Models/ReviewFormResponse.php`
- Modify: `app/Models/ReviewFormFieldResponse.php`

**Interfaces:**
- Consumes: hasil Task 2–13
- Produces: ketiga berkas `< 200` baris tanpa pelanggaran bentuk

- [ ] **Step 1: Ukur posisi sekarang**

```bash
for m in SubmissionReviewer ReviewFormResponse ReviewFormFieldResponse; do
  printf '%-26s %s baris\n' "$m" "$(wc -l < app/Models/$m.php)"
done
```

Lantainya 93, 69, dan 43. Kalau ada yang masih di atas 200, masih ada logic yang
belum pindah — **jangan** memangkas komentar untuk mengejar angka; cari logic-nya.

- [ ] **Step 2: Pendekkan prosa komentar `SubmissionReviewer`**

Pelanggaran *prosa komentar terlalu panjang* berasal dari docblock
`SETTLED_EVALUATION` yang enam baris. Batasnya `hard.comment.prose: 3`. Ganti jadi:

```php
    /**
     * Status evaluasi yang dihitung selesai untuk BR-REV-05, BR-REV-06, dan
     * BR-FE-06. `not_required` ikut karena reviewer yang memang tidak wajib
     * menilai tak akan pernah selesai, dan submission-nya terkunci selamanya.
     */
    public const SETTLED_EVALUATION = ['completed', 'not_required'];
```

Tiga baris prosa, isinya utuh: alasan `not_required` ikut tetap tertulis. Yang
dibuang rujukan "register #14" — ia nomor internal yang tidak bisa dicari siapa pun
dari dalam repo.

- [ ] **Step 3: Buang komentar `// NEW:` yang tersisa**

```bash
grep -rn '// NEW:' app/Models/SubmissionReviewer.php app/Models/ReviewFormResponse.php app/Models/ReviewFormFieldResponse.php
```

Seluruhnya peninggalan fork dan tidak memberi tahu apa pun — "NEW" relatif terhadap
waktu yang sudah lewat. Hapus barisnya, bukan diganti komentar lain.

- [ ] **Step 4: Jalankan gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 916 test.

- [ ] **Step 5: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app/Models
git commit -m "refactor(review): rapikan komentar tiga model cluster review"
```

---

## Tahap 5 — Keluar dari antrean

### Task 15: Turunkan `max.legacy.files`

**Files:**
- Modify: `quality-legacy.txt`
- Modify: `quality-budget.txt`

**Interfaces:**
- Consumes: hasil Task 14
- Produces: `max.legacy.files: 273`

- [ ] **Step 1: Buktikan ketiganya bersih, dengan korpus penuh**

Jalankan ulang skrip Task 13 Step 7.
Expected: ketiganya `(bersih)`.

Kalau ada yang belum, **jangan** hapus barisnya dari `quality-legacy.txt` —
assertion 2 di `QualityBudgetTest` akan menangkapnya, dan itu memang tugasnya.

- [ ] **Step 2: Hapus tiga baris dari antrean**

```bash
sed -i '/^app\/Models\/SubmissionReviewer\.php$/d;/^app\/Models\/ReviewFormResponse\.php$/d;/^app\/Models\/ReviewFormFieldResponse\.php$/d' quality-legacy.txt
grep -vc '^#\|^$' quality-legacy.txt
```
Expected: **273**.

- [ ] **Step 3: Turunkan plafonnya**

Di `quality-budget.txt`, ganti `max.legacy.files:      276` jadi
`max.legacy.files:      273`.

Angka ini **hanya boleh turun**. Kalau ia harus naik, itu keputusan menambah utang
dan tempatnya dibahas di PR.

- [ ] **Step 4: Jalankan gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 916 test. `QualityBudgetTest` assertion 2 memastikan
`quality-legacy.txt` dan `max.legacy.files` konsisten — kalau ia merah, kedua angka
itu berselisih.

- [ ] **Step 5: Commit**

```bash
git add quality-legacy.txt quality-budget.txt
git commit -m "chore(quality): keluarkan tiga model cluster review dari antrean legacy"
```

- [ ] **Step 6: Catat hasilnya di dokumen ini**

Tambahkan bagian *Status pelaksanaan* di bawah judul, mengikuti bentuk
`refactor-plan-wave-2-formsubmission.md`: apa yang berubah dari rencana, berapa
putaran sensus yang dibutuhkan, dan utang apa yang ditinggalkan. Commit terpisah:

```bash
git add docs/design/refactor-plan-wave-2-review-cluster.md
git commit -m "docs(design): catat hasil pelaksanaan cluster review"
```
