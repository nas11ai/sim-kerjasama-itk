# M1 — Irisan: Menutup Register Open Questions

**3 skenario baru · 10 skenario pending dilunasi · 4 register ditutup · pending 87 → 77**

Irisan ketiga M1. Yang pertama (Budget) membuktikan jalur spec→test→hijau untuk Action +
Policy; yang kedua (HTTP) membuktikannya untuk lapis HTTP/Inertia. Irisan ini
**menyambungkan keduanya**: aturan yang sudah benar di Action akhirnya berlaku di jalur
yang sungguh-sungguh dipakai peneliti.

Sasarannya bukan sebuah fitur melainkan sebuah berkas —
[`open-questions.md`](../../requirement-analysis/bdd/open-questions.md) — yang setelah
irisan ini tidak lagi memuat satu pun entri terbuka.

---

## 1. Kenapa irisan ini, dan kenapa sekarang

Register memuat sembilan entri di bagian *Terbuka*. Pemeriksaan satu per satu
menunjukkan bagian itu menyimpan dua jenis isi yang berbeda:

| # | Isi | Keadaan terverifikasi |
| - | --- | --------------------- |
| 9 | Anggaran melebihi batas skema | **Sudah tuntas** — `[BUD-LIMIT-01]` ada di `budget.feature`, punya test di `BudgetLimitTest.php`, kalimat `RESEARCHER-02-04` sudah diganti |
| 10 | Keterlihatan skor monev | **Sudah tuntas** — BR-MON-09 ada di `supporting/02_monev.md`, BR-REV-12 menautkannya |
| 11 | Kebutuhan bisnis tanpa layar | **Sudah tuntas** — `frames.yaml` memuat 65 frame |
| 12 | Jendela kerja setelah submit | **Sudah tuntas** — `Batas Evaluasi` dan `Batas Revisi` terdaftar di `02_ubiquitous_language.md` |
| 24 | Super Admin menembus kunci anggaran | **Sudah tuntas** — `INVARIANT_ABILITIES` memuat `update-budget` |
| 25 | Delegasi submit ke `SubmitFormSubmission` | Butuh kode |
| 26 | `guardRequiredDocuments()` mengabaikan `isRequiredFor()` | Butuh kode |
| 27 | BR-SM-02 tidak ditegakkan pada resubmit | Butuh keputusan, lalu kode |
| 28 | Draft baru tidak bisa dimulai setelah terminal | Butuh kode |

Lima entri teratas adalah pekerjaan yang sudah selesai tapi masih diarsipkan di bagian
yang salah. Memindahkannya adalah pembukuan, bukan pekerjaan — tapi selama ia duduk di
*Terbuka*, register berbohong tentang ukuran utangnya sendiri.

Empat sisanya saling mengunci, dan itu alasan mereka dikerjakan sebagai satu irisan
alih-alih empat tambalan: #25 memindahkan validasi ke Action, #26 memperbaiki cara
validasi itu menyaring, #27 memakai hasilnya di jalur kedua, dan #28 menentukan baris
mana yang divalidasi. Menutup salah satunya sendirian berarti menyentuh berkas yang sama
empat kali.

---

## 2. Tiga cacat yang dibongkar

Ketiganya **diverifikasi langsung**, bukan dibaca dari kode lalu diduga.

### 2.1 Cabang `needs_review = false` melempar galat, bukan menyetujui

`UserFormController::submitForm()` baris 550–553:

```php
if ($formPhaseDetail && !$formPhaseDetail->needs_review) {
    $submission->status->transitionTo(Approved::class);
} else {
    $submission->status->transitionTo(Submitted::class);
}
```

State machine tidak mengizinkan perpindahan itu. Diperiksa langsung terhadap
`SubmissionStatus::config()`:

```
Draft -> Approved   : NOT ALLOWED
Draft -> Submitted  : ALLOWED
```

Satu-satunya jalan masuk ke `Approved` adalah dari `UnderReview`. Jadi setiap submit pada
fase yang tidak butuh review melempar `TransitionNotFound`, dan karena panggilannya ada
di dalam `DB::transaction`, seluruh submit-nya ikut dibatalkan. Nol test dan nol seeder
menyentuh cabang ini.

**Ini mengoreksi premis entri #25.** Register menyebut delegasi tertahan karena
"mendelegasikan tanpa memindahkan percabangan itu akan mengubah perilaku form yang tidak
butuh review". Tidak ada perilaku yang berubah — tidak ada perilaku yang bekerja. Yang
selama ini menahan delegasi ternyata cabang rusak yang tak seorang pun pernah jalani.

### 2.2 Dua jalur validasi dengan cakupan berbeda

| Jalur | Cakupan |
| ----- | ------- |
| `submitForm()` inline (baris 470–499) | **semua** field `is_required` — teks dan file |
| `SubmitFormSubmission::guardRequiredDocuments()` | **hanya** field bertipe `file` |

Delegasi polos karena itu akan **melonggarkan** validasi: field teks wajib berhenti
diperiksa. Keduanya juga sama-sama menyaring `is_required` mentah dan mengabaikan
`FormField::isRequiredFor()` — entri #26, yang sampai sekarang punya unit test tapi
**nol pemanggil produksi**.

### 2.3 `firstOrCreate` ada di dua tempat, bukan satu

Entri #28 menyebut `saveDraft()` baris 372. Pemeriksaan menemukan bentuk yang sama juga
di `submitForm()` baris 503. Keduanya mencari `['form_id', 'submitted_by']` — satu baris
per (Form, peneliti), tanpa memperhitungkan periode maupun status.

---

## 3. Empat keputusan

### 3.1 Submit tidak pernah menyetujui — cabang `needs_review` dihapus

Submit selalu berpindah ke `SUBMITTED`. Pertanyaan bagaimana fase tanpa review mencapai
`APPROVED` menjadi milik BR-REV-05 di M2, tempat `EvaluateSubmissionOutcome` dibangun.

Alasannya bukan kemudahan: BR-REV-05 **sudah** memiliki aturan approval otomatis.
Menaruh salinannya di jalur submit persis melahirkan "dua implementasi untuk satu
aturan" yang diperingatkan tabel *Aturan Cermin Lintas Konteks* di
[`ddd/README.md`](../../requirement-analysis/ddd/README.md#aturan-cermin-lintas-konteks) —
risiko integritas data, bukan sekadar kerapian.

Dicatat sebagai **ADR 0016** karena keputusan ini akan mengejutkan siapa pun yang membaca
kode legacy.

### 3.2 Validasi pindah ke Action, sekaligus diperbaiki dengan `isRequiredFor()`

Seluruh pemeriksaan field wajib pindah ke satu guard yang menyaring dengan
`FormField::isRequiredFor()`, bukan `is_required` mentah. Menutup #25 dan #26 sekaligus,
dan membuat Temporal Field Binding BR-FE-11 akhirnya menyala di produksi.

### 3.3 Resubmit memakai guard yang sama

`ResubmitFormSubmission` memanggil guard yang sama dengan `SubmitFormSubmission`,
memakai **jangkar temporal yang sama** — `$submission->created_at` yang asli, bukan waktu
revisi. Konsekuensinya penting: revisi tidak menarik field wajib baru ke dalam pengajuan
yang sudah berjalan.

Menutup #27: peneliti yang mengosongkan response proposalnya sebelum resubmit tidak lagi
lolos.

### 3.4 Hanya satu tempat yang boleh membuat baris

Identitas draft wizard: `(form_id, submitted_by, submission_period_id)` dengan status
`DRAFT` **atau** `NEEDS_REVISION`.

Ikutnya `NEEDS_REVISION` bukan kelonggaran melainkan keharusan: kalau resolver hanya
mencocokkan `DRAFT`, pengajuan yang sedang direvisi jadi tak terjangkau `save-draft` dan
alur revisi ikut mati. Dua status itu persis yang diizinkan
`FormSubmissionPolicy::update()` — resolver dan Policy bicara hal yang sama.

Baris terminal (`REJECTED`, `WITHDRAWN`, `APPROVED`) dan yang sedang berjalan
(`SUBMITTED`, `UNDER_REVIEW`) tidak pernah cocok. **Itulah yang menutup #28** — tanpa
satu pun gerbang dicabut. Arah perbaikannya persis yang diusulkan register: bukan di
gerbangnya, melainkan di pencariannya.

---

## 4. Batas strangler

Batas ditarik di **`UserFormController`** saja. Kedua method-nya menyusut jadi orkestrasi
tipis: validasi request → resolve → persist response → Action.

Yang **tidak** disentuh: `SubmissionViewController`, `ReviewController`, dan seluruh
alur review. Irisan ini hanya jalur pengajuan.

Berbeda dari irisan HTTP yang sengaja tidak menulis ulang `submitForm()`, irisan ini
**memang** menulis ulang isinya — karena itulah pekerjaan yang diminta #25, dan karena
tanpanya tiga register lain tidak bisa ditutup. Yang tetap berlaku: tidak merapikan yang
tidak sedang disentuh.

### Konsekuensi yang harus disebut

Audiens notifikasi berpindah:

| | Penerima |
| - | -------- |
| `EmailNotificationService::notifyAdminFormSubmission()` | `User::role(['Super Admin', 'Admin'])` |
| `SendProposalSubmittedNotification` (listener `ProposalSubmitted`) | `User::permission('submissions.view-all')` |

Mendelegasikan ke Action berarti berpindah dari basis role ke basis permission. Itu arah
yang benar menurut DDD ("Spatie Permission = apa yang boleh dilakukan; Organization = dari
mana user berasal") dan menuntaskan pensiun `EmailNotificationService` untuk jalur ini
sesuai rencana M0. Tapi orang sungguhan akan berhenti dan mulai menerima email — dicatat
sebagai konsekuensi yang dipilih, bukan efek samping yang tidak disadari.

---

## 5. Komponen

| Berkas | Tanggung jawab |
| ------ | -------------- |
| `app/Actions/FormSubmission/StartSubmission.php` | Resolve-or-create, **idempoten**. Menyetel `submission_period_id`, `form_phase_detail_id`, `last_completed_step = 1`; `created_at` menjadi jangkar Temporal Field Binding |
| `app/Actions/FormSubmission/ResolveWizardSubmission.php` | Pencarian murni tanpa efek samping; mengembalikan `?FormSubmission` |
| `app/Support/RequiredFieldGuard.php` | `assertComplete(FormSubmission): void`. Menyaring dengan `isRequiredFor()`, melempar `ValidationException` berkunci `field_{id}` |

`RequiredFieldGuard` ditaruh di `app/Support` mengikuti preseden `OptimisticLock`:
`final class` dengan method statis, karena tidak ada state instance yang dipegang. Ia
bukan Action — sebuah guard bukan use case. Letaknya juga membuatnya otomatis kena
PHPStan level 9, yang tidak berlaku kalau ia tinggal di `app/Models`.

Idempotensi `StartSubmission` bukan kemewahan: frontend memanggilnya pada `onMounted`,
sehingga tanpa itu setiap refresh melahirkan draft kembar.

---

## 6. Perubahan pada yang sudah ada

| Berkas | Perubahan |
| ------ | --------- |
| `UserFormController::saveDraft()` | `firstOrCreate` → resolver; `409` bila null. Blok persist response diekstrak jadi `persistResponses()` (Extract Method) |
| `UserFormController::submitForm()` | ±200 → ±30 baris. Hapus validasi inline, cabang `needs_review`, dan panggilan `emailService`. Delegasi ke `SubmitFormSubmission` |
| `SubmitFormSubmission` | `guardRequiredDocuments()` → `RequiredFieldGuard::assertComplete()` |
| `ResubmitFormSubmission` | Tambah `RequiredFieldGuard::assertComplete()` |
| `resources/js/Pages/User/FormPhase.vue` | `onMounted` → `POST /submissions`, simpan id di state |
| `routes/web.php` | Satu route baru |

---

## 7. Galat dan status HTTP

| Keadaan | Respons |
| ------- | ------- |
| Resolver tidak menemukan draft pada `save-draft` / `submit` | `409` — "Draft tidak ditemukan; mulai pengajuan dari langkah 1" |
| Field wajib kosong saat submit | `422`, error per field berkunci `field_{id}` |
| Bukan `submitted_by` | `403` dari `FormSubmissionPolicy` (tidak berubah) |
| Bentrok keunikan pengajuan aktif | `422` beserta tautan, lewat savepoint (tidak berubah) |

`409` dipilih untuk resolver kosong, bukan `404`: endpoint-nya ada, yang bertentangan
adalah **keadaan** — klien meminta menyimpan draft yang tidak sedang hidup. Klien
memulihkannya dengan memanggil `POST /submissions`, persis pola pemulihan yang sama
dengan `409` optimistic lock yang sudah dipakai `OptimisticLock::assertNotStale()`.

`422` per field dituntut dua skenario yang menyebut bentuk error-nya secara eksplisit:
`[SM-SUBMIT-07]` ("melihat error per field pada `Proposal PDF`") dan `[SM-WIZARD-06]`.

---

## 8. Strategi test

| Lapis | Yang dibuktikan |
| ----- | --------------- |
| Unit | `RequiredFieldGuard` untuk `FE-TEMPORAL-01..08`. Empat test di `tests/Unit/FormFieldTest.php` sudah menguji `isRequiredFor()` — keempatnya **diikat ke ID skenario**, bukan ditulis ulang |
| Action | `StartSubmission`: idempotensi, jangkar `created_at`, dan kelahiran draft baru saat baris lama terminal (`SM-WIZARD-12`). `ResubmitFormSubmission`: guard field wajib (`SM-SUBMIT-19`) |
| HTTP | `saveDraft` / `submitForm` lewat rute bernama: `409` saat draft tidak ada, `422` per field, dan delegasi benar-benar terjadi. `FE-TEMPORAL-09` dibuktikan di sini karena ia menyangkut penyimpanan langkah yang **tidak** memvalidasi penuh |
| Frontend | Satu test Vitest: `onMounted` memanggil `POST /submissions` tepat sekali |

Tidak ada `FormFieldFactory` di repo ini — `tests/Unit/FormFieldTest.php` membangun model
secara manual. Test baru mengikuti pola itu alih-alih menambah factory yang belum
dibutuhkan siapa pun.

Delegasi diuji sebagai **perilaku**, bukan lewat mock: submit pada draft tanpa proposal
harus ditolak `422` di jalur HTTP. Kalau `submitForm()` diam-diam berhenti memanggil
Action-nya, test itu memerah — sesuatu yang assertion "Action dipanggil" tidak jamin.

---

## 9. Cakupan

Tiga skenario baru, ditulis **dan** dites di irisan yang sama.

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `SM-SUBMIT-18` | happy | Fase `needs_review = false` tetap mendarat di `SUBMITTED` | RESEARCHER-02-10 |
| `SM-SUBMIT-19` | denial | Resubmit tanpa field wajib ditolak | RESEARCHER-02-05 |
| `SM-WIZARD-12` | happy | Form yang pengajuan sebelumnya terminal melahirkan draft baru | RESEARCHER-02-02 |

Rentang `SM-SUBMIT` tumbuh 17 → 19 dan `SM-WIZARD` 11 → 12. Ini penambahan di ujung,
bukan penggeseran — ID yang sudah dialokasikan tidak berubah maknanya.

Skenario pending yang dilunasi:

| Kelompok | ID | BR | Lapis dominan |
| -------- | -- | -- | ------------- |
| Temporal Field Binding | `FE-TEMPORAL-01..09` | BR-FE-11 | Unit (guard) + HTTP untuk `-09` |
| Jangkar wizard | `SM-WIZARD-01` | BR-FE-11 | Action |

`FE-TEMPORAL-08` (field yang sudah di-soft-delete tidak diwajibkan) tidak butuh kode
tambahan: `FormField` memakai `SoftDeletes`, jadi relasi `formFields()` sudah menyaringnya
selama guard tidak memanggil `withTrashed()`. Yang dibutuhkan hanya test yang
membuktikannya, supaya sifat itu tidak hilang diam-diam saat query-nya diubah.

---

## 10. Ledger

Kondisi awal `pending` 87, `uncovered` 59. `uncovered` **tidak berubah** — BR-FE-11 sudah
punya skenario sejak Wave 1a, dan tiga skenario baru tidak memperkenalkan BR baru.

Tiga skenario baru masuk `pending.txt` lalu keluar di irisan yang sama, jadi kontribusi
netonya nol.

| Tahap | ID yang keluar | `pending` |
| ----- | -------------- | --------- |
| Guard field wajib | `FE-TEMPORAL-01..08` | 87 → **79** |
| Validasi parsial per langkah | `FE-TEMPORAL-09` | 79 → **78** |
| Jangkar langkah 1 | `SM-WIZARD-01` | 78 → **77** |

Empat test di `tests/Unit/FormFieldTest.php` sudah menguji `isRequiredFor()` tapi belum
menyebut ID skenario. Keempatnya diikat ke ID-nya alih-alih ditulis ulang — test yang
sudah membuktikan hal yang benar tidak perlu diganti, hanya perlu mengaku apa yang
dibuktikannya.

---

## 11. Yang sengaja tidak dikerjakan

**Enam endpoint wizard ADR 0012 lainnya.** Irisan ini hanya memasang langkah 1
(`POST /submissions`). Langkah 2–7 punya irisannya sendiri, bersama UI wizard-nya.

**Kasus hampa BR-REV-05** — bagaimana fase tanpa reviewer mencapai `APPROVED`. Ditunda ke
M2 dan dicatat sebagai baris baru di [`m2-review.md`](m2-review.md) supaya tidak hilang
bersama cabang yang dihapus §3.1.

**Gerbang periode tertutup saat membuat draft.** Wireframe `RESEARCHER-02-02` menyatakan
draft tetap tersimpan meski periode ditutup, dan tidak ada skenario yang menuntut gerbang
di titik pembuatan. Menambahkannya tanpa skenario adalah persis bentuk drift yang dilarang
repo ini.

---

## 12. Pembukuan register

| Entri | Tindakan |
| ----- | -------- |
| 9, 10, 11, 12, 24 | Dipindah ke *Terselesaikan* apa adanya — kelimanya sudah diverifikasi mendarat (§1) |
| 25, 26, 27, 28 | Ditutup dengan catatan keputusan yang merujuk spec ini |

Catatan penutup #25 wajib menyebut koreksi §2.1: alasan teknis yang selama ini tercatat
di sana keliru, dan register tidak boleh mengabadikan alasan yang tidak benar.

Setelah irisan ini, bagian *Terbuka* kosong. Itu keadaan yang **tidak akan bertahan** —
register memang dirancang untuk terus diisi selama implementasi berjalan. Yang berubah
adalah utangnya kembali jujur.
