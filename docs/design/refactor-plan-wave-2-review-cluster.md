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

**Status:** belum dikerjakan. Ditulis 20 September 2026, sesudah gelombang 2 bagian 2
mendarat di `60ee951`. Suite acuan: **891 test**, `max.legacy.files: 276`.

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

## Sensus mentah

Angka di bawah hasil sapuan otomatis yang **sudah** memetakan scope dan accessor,
tapi **belum** memverifikasi nama berkata umum dan belum memeriksa pemanggil internal.
Ia menunjukkan ukuran pekerjaan, bukan vonis. **Task 1 mengulanginya dengan benar.**

### `SubmissionReviewer`

| Method | Pemanggil | Dugaan |
| ------ | --------: | ------ |
| `requiredReviewerFormAssignments` | 0 | dipakai internal oleh `hasAllRequiredFormsCompleted` |
| `optionalReviewerFormAssignments` | 0 | mati |
| `overdueReviewerFormAssignments` | 0 | dipakai internal oleh dua method yang juga nol |
| `dueSoonReviewerFormAssignments` | 0 | mati |
| `hasAssignedForms` | 0 | dipakai internal oleh `updateEvaluationStatus` |
| `getEvaluationCompletionPercentage` | 0 | mati |
| `canCreateDiscussionThreads` | 0 | predikat kembar — lihat T1 |
| `autoLockOverdueForms` | 0 | mati |
| `evaluation_status_label` | 0 | mati (presentasi di model) |
| `evaluation_status_color` | 0 | mati (presentasi di model) |
| `completed_forms_count` | 0 | mati |
| `overdue_forms_count` | 0 | mati |
| `pending_forms_count` | 3 | hidup |
| `hasSettledEvaluation` | 5 | **tetap di model** — invarian BR-FE-06 |
| `canParticipateInDiscussions` | 4 | predikat kembar — lihat T1 |
| `updateEvaluationStatus` | 5 | → `Actions/` |
| `markAsReplaced` | 2 | → `Actions/` |
| `assignForm` | 2 | → `Actions/` |
| `removeFormAssignment` | 2 | → `Actions/` |
| `hasAllRequiredFormsCompleted` | 1 | → `Queries/` |
| `scopeActive`, `scopeUnsettledEvaluation` | hidup | **tetap di model** |

### `ReviewerFormAssignment`

| Method | Pemanggil | Dugaan |
| ------ | --------: | ------ |
| `isDueSoon` | 0 | mati |
| `getStatus` | 0 | dipakai internal oleh `getStatusLabel` |
| `getHoursUntilDue` | 0 | mati |
| `canBeCompleted` | 0 | mati |
| `scopeOptional`, `scopeOverdue`, `scopeDueSoon` | kata umum | verifikasi ulang |
| `isOverdue` (3), `isCompleted` (2), `getStatusLabel` (5), `getDaysUntilDue` (8), `autoLockIfOverdue` (1) | hidup | golongkan di T3 |

### `ReviewFormResponse`

| Method | Pemanggil | Dugaan |
| ------ | --------: | ------ |
| `hasRequiredFieldsCompleted` | 0 | dipakai internal oleh `canBeSubmitted` |
| `getFieldResponse` | 0 | dipakai internal oleh `generateSummaryNotes` |
| `updateReviewerEvaluationStatus` | 0 | dipakai internal oleh `submit` — **dan ia bug, lihat T2** |
| `generateSummaryNotes` | 0 | mati kalau pemanggilnya nihil |
| `status_label`, `status_color` | 0 | mati (presentasi di model) |
| `submit`, `lock`, `saveFieldResponse` | kata umum | → `Actions/`, verifikasi ulang |
| `getCompletionPercentage` | 2 | → `Queries/` |
| `isDraft` (2), `canBeEdited` (6), `isSubmitted` (7), `isLocked` (1), `canBeSubmitted` (3) | hidup | **tetap di model** — predikat satu baris atas kolomnya sendiri |

### `ReviewFormFieldResponse`

| Method | Pemanggil | Dugaan |
| ------ | --------: | ------ |
| `formatCheckboxValue` … `formatUrlValue` (6) | 0 | dipakai internal oleh `formatted_value` |
| `edit_value` | 0 | mati |
| `validateCustomRules` | 0 | dipakai internal oleh `validate` |
| `formatted_value` | 6 | → `Support/`, lihat T4 |
| `validate` | kata umum | → `Support/`, lihat T5 |
| `hasValue` | 3 | **tetap di model** |

---

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
