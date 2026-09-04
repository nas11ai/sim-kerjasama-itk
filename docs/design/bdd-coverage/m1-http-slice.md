# M1 — Irisan Vertikal HTTP: Submit, State, Access

**27 skenario · 3 kelompok · 8 BR perilaku · pending 114 → 87**

Irisan kedua M1. Yang pertama (Budget) membuktikan jalur spec→test→hijau untuk
**Action + Policy**. Irisan ini membuktikannya untuk **HTTP/Inertia** — lapis yang
belum pernah diuji sekali pun, dan tempat tiga cacat nyata sedang bersembunyi.

Spec ini menemani [`m1-submission-budget.md`](m1-submission-budget.md); skenarionya
sudah ditulis di sana dan di `.feature`. Yang ditetapkan di sini adalah **di lapis mana
tiap skenario dibuktikan**, dan di mana batas strangler ditarik.

---

## 1. Kenapa irisan ini, dan kenapa sekarang

`pending.txt` memuat 114 skenario yang punya spec tapi belum punya test. Spec yang tidak
pernah menjadi test adalah dokumentasi, bukan jaminan.

Irisan Budget membuktikan pola untuk Action yang dipanggil langsung. Tapi mayoritas sisa
`pending` — `SM-SUBMIT`, `SM-ACCESS`, `SM-WIZARD`, `FE-OVERRIDE` — berbentuk HTTP: ia
menyebut kode status, pesan per field, dan isi respons. Menulis 200 skenario M2–M5 di
atas kosakata yang baru teruji untuk satu bentuk saja adalah taruhan yang tidak perlu.

---

## 2. Aturan pemilihan lapis

Skenario dibuktikan di **lapis terendah yang masih membuktikan seluruh kalimatnya**.
Yang menentukan bukan selera, melainkan bunyi langkah `Then`-nya:

| Bunyi `Then` | Lapis | Alasan |
| ------------ | ----- | ------ |
| `akses ditolak dengan 403` | HTTP | Kode status hanya ada di respons |
| `penyuntingan ditolak dengan 403` | HTTP | idem |
| `melihat error per field pada "<field>"` | HTTP | Bentuk error validasi Laravel |
| `penolakan menyebut "<id>" beserta tautannya` | Action | Pesannya disusun di Action; HTTP hanya merendernya |
| `penolakan bukan galat database mentah` | Action | `ValidationException`, bukan `QueryException` — lebih presisi daripada "bukan 500" |
| `status "<id>" menjadi <STATUS>` | Action | Perubahan keadaan domain |
| `submit ditolak karena <alasan>` | Action | Aturan domain, bukan presentasi |
| `perpindahan ditolak` | State machine | `transitionTo()` menolak sendiri |

Konsekuensinya satu skenario bisa menuntut dua test — satu di Action, satu di HTTP —
dan itu bukan duplikasi: yang satu membuktikan aturannya, yang lain membuktikan
aturannya benar-benar tersambung ke jalur yang dipakai pengguna.

---

## 3. Tiga cacat yang dibongkar skenario

Ketiganya **diverifikasi langsung**, bukan dibaca dari kode lalu diduga.

### 3.1 `userShowSubmission` memakai cek buatan sendiri

`SubmissionViewController::userShowSubmission()` menjaga aksesnya begini:

```php
$canView = $submission->submitted_by === $user->id
    || $this->canUserReview($submission, $user);
```

Dua aktor hilang dari situ: **anggota penelitian** dan pemegang
**`submissions.view-all`**. `FormSubmission::isAccessibleBy()` sudah menangani keempat
aktor dengan benar dan sudah punya test unit — controller-nya saja yang tidak
memakainya. AGENTS.md aturan 2 menyebut ini eksplisit.

Akibatnya `[SM-ACCESS-02]` (co-investigator) dan `[SM-ACCESS-04]` (Operator) akan
**gagal hari ini**. Itu bukan test yang salah.

### 3.2 `resubmit` dideklarasikan invarian tapi tidak punya Policy

`INVARIANT_ABILITIES` memuat `resubmit`, sehingga `Gate::before` sengaja tidak
melewatinya. Tapi `FormSubmissionPolicy` hanya punya `view`, `update`, `submit`, dan
`withdraw` — tidak ada `resubmit`. Ability tanpa method Policy selalu ditolak.

Terbukti: pada submission NEEDS_REVISION miliknya sendiri,
`$owner->can('resubmit', $s)` bernilai **`false`**.

Jadi pemilik tidak bisa merevisi pengajuannya. `[SM-SUBMIT-12]` dan `[SM-STATE-03]`
mengunci perbaikannya.

### 3.3 Jalur HTTP submit tidak memanggil Action-nya

`UserFormController::submitForm()` adalah method warisan sepanjang ±200 baris yang
memvalidasi, mengunggah berkas, dan menyetel status **inline**. Ia tidak pernah
memanggil `SubmitFormSubmission`. Artinya BR-SM-04 yang ditegakkan Action itu tidak
berlaku di jalur yang benar-benar dipakai pengguna.

---

## 4. Batas strangler

Irisan ini **tidak** menulis ulang `submitForm()`. Itu refactor besar yang tidak diminta
dan tidak muat dalam satu irisan yang bisa direview.

Yang dilakukan: **aturan domain baru mendarat di `SubmitFormSubmission`**, dan
`submitForm()` diubah seperlunya saja untuk mendelegasikan keputusan submit ke Action
itu. Validasi berkas dan pemetaan response tetap di tempatnya sekarang.

Prinsipnya sama seperti yang sudah disepakati di AGENTS.md: saat menyentuh sebuah flow,
ekstrak logic-nya beserta test-nya — jangan menyalin pola legacy ke kode baru, dan
jangan pula merapikan yang tidak sedang disentuh.

---

## 5. Cakupan

| Kelompok | ID | BR | Lapis dominan |
| -------- | -- | -- | ------------- |
| Akses submission | `SM-ACCESS-01..06` | BR-SM-07 | HTTP |
| State machine | `SM-STATE-01, 02, 03, 04` | BR-SM-03, BR-SM-05 | State machine + HTTP |
| Siapa yang submit | `SM-SUBMIT-09..14` | BR-SM-04 | Action + HTTP |
| Revisi | `SM-SUBMIT-12`, `SM-STATE-03` | BR-SM-04, BR-SM-03 | Policy + Action |
| Proposal PDF | `SM-SUBMIT-07, 08` | BR-SM-02 | Action + HTTP |
| Keunikan pengajuan aktif | `SM-SUBMIT-01..06` | BR-SM-01 | Action |
| Penolakan yang manusiawi | `SM-SUBMIT-15..17` | BR-SM-16 | Action (isi pesan) |

---

## 6. Yang sengaja ditunda

**`[SM-STATE-05]` — Operator menyentuh status terminal lewat override.**

`FormSubmissionOverride` ada sebagai model, punya `isValid()` dan `revoke()`, dan punya
test unit. Tapi **nol kode produksi mengonsumsinya** — tidak ada Policy, Action, maupun
controller yang menanyakan apakah sebuah override berlaku.

Membuat `[SM-STATE-05]` hijau berarti membangun seluruh jalur konsumsi override:
Policy yang menanyakannya, Action yang menghormatinya, dan jejak audit yang
mencatatnya. Itu fitur tersendiri yang diatur [ADR 0014](../../adr/0014-time-boxed-access-single-override-mechanism.md),
bukan bagian dari irisan HTTP.

Ia tetap di `pending.txt`. Menundanya secara terbuka lebih jujur daripada melunakkan
skenarionya supaya cepat hijau.

---

## 7. Ledger

Kondisi awal `pending` 114, `uncovered` 59. `uncovered` **tidak berubah** — kedelapan BR
di sini sudah punya skenario sejak Fase A; yang bertambah hanyalah buktinya.

| Task | ID yang keluar | `pending` |
| ---- | -------------- | --------- |
| 1 | `SM-STATE-01, 02` | 114 → **112** |
| 2 | `SM-ACCESS-01..06` | 112 → **106** |
| 3 | `SM-STATE-04` | 106 → **105** |
| 4 | `SM-SUBMIT-09, 10, 11, 13, 14` | 105 → **100** |
| 5 | `SM-SUBMIT-12`, `SM-STATE-03` | 100 → **98** |
| 6 | `SM-SUBMIT-07, 08` | 98 → **96** |
| 7 | `SM-SUBMIT-01..06` | 96 → **90** |
| 8 | `SM-SUBMIT-15, 16, 17` | 90 → **87** |

---

## 8. Yang dibuktikan irisan ini untuk milestone berikutnya

Setelah irisan ini mendarat, tiga hal berhenti jadi dugaan:

1. **Pola test HTTP** — bagaimana `actingAs` + rute bernama + `assertStatus(403)` ditulis
   di repo ini, termasuk perangkap middleware.
2. **Kosakata langkah untuk HTTP** — apakah `akses ditolak dengan <kode>` dan
   `melihat error per field pada "<field>"` cukup untuk M2–M5, atau perlu diperbaiki
   selagi ongkosnya masih 27 skenario dan bukan 200.
3. **Batas strangler yang realistis** — seberapa banyak controller warisan harus
   disentuh untuk membuat satu aturan domain berlaku di jalur nyata.
