# M4 — Monev & Research Output

**20 BR bertag · 47 skenario · 2 berkas `.feature`**

Yang terjadi **setelah** pengajuan disetujui: siklus monitoring-evaluasi, dan pelaporan
luaran yang benar-benar terwujud.

Sumber: [`supporting/02_monev.md`](../../requirement-analysis/ddd/supporting/02_monev.md),
[`supporting/03_research_output.md`](../../requirement-analysis/ddd/supporting/03_research_output.md).

---

## 1. Berkas dan rentang ID

| Berkas | Rentang ID | BR |
| ------ | ---------- | -- |
| `supporting/monev.feature` | `MON-CYCLE-01..12`, `MON-REVIEW-01..13` | BR-MON-01 … BR-MON-10 |
| `supporting/research_output.feature` | `RO-OUTPUT-01..22` | BR-RO-01 … BR-RO-09 |

Semua skenario bertag `@M4`.

`monev.feature` berisi 25 skenario — tepat di ambang ~25 yang memaksa pemecahan.
Garis pisahnya sudah kelihatan kalau nanti dilewati: `MON-CYCLE-*` menjaga
**akses dan gerbang siklus**, `MON-REVIEW-*` menjaga **penugasan reviewer dan
keterlihatan hasilnya**. Rentang ID tidak perlu berurutan dengan nama berkas,
jadi memecahnya tidak menomori ulang apa pun.

---

## 1a. Dua BR baru dan satu amandemen, semuanya dari audit DDD

Sepuluh skenario tambahan di irisan ini tidak lahir dari memperdalam aturan yang
sudah ada. Semuanya lahir dari membaca dokumen DDD sampai habis dan menemukan
bagian yang **tidak pernah dinomori** — atau yang menegasikan dirinya sendiri.

| BR | Lahir dari | Register |
| -- | ---------- | -------- |
| **BR-MON-09** *(sudah ada)* | — | #10 |
| **BR-MON-10** *(baru)* | Tabel Domain Events mendaftar `MonevStageOpened` dan `MonevEvaluationSubmitted` ke Notification; activity diagram menggambar keduanya sampai ke peneliti dan reviewer. Tidak satu pun pernah diuji | — |
| **BR-RO-09** *(baru)* | `config/research_output_types.php` menandai field `required` per tipe, tapi tidak ada aturan yang menegakkannya. "Taksonomi dibatasi" karena itu baru benar separuh: tipenya terkendali, isinya tidak | — |
| BR-RO-07 *(amandemen)* | Tabel *Access Control* memberi Operator/Admin akses penuh, sementara BR-nya berbunyi "**hanya** `submitted_by` dan `co_investigator`" | #35 |

BR-RO-04 tidak diamandemen — bunyinya sudah menyebut `ip` **dan** `prototype`.
Yang kurang skenarionya: `prototype` tidak pernah diuji, sehingga implementasi
yang menuliskan `ip` saja lolos tanpa ada yang gagal. `[RO-OUTPUT-17/18]`
menutupnya.

---

## 2. Konsep yang harus dipegang dulu

Monev **bukan phase terpisah**. Ia bagian dari satu `FormPhase` yang sama dengan
pengajuan, dibedakan oleh `FormPhaseDetail` yang berurutan:

```
Detail 1  Pengajuan          researcher
Detail 2  Evaluasi Pengajuan reviewer
Detail 3  Laporan Monev I    researcher   ← child FormSubmission
Detail 4  Evaluasi Monev I   reviewer
Detail 5  Laporan Monev II   researcher
Detail 7  Upload Luaran      researcher
```

Semua laporan monev adalah **child FormSubmission** yang menunjuk ke submission
pengajuan utama lewat `parent_submission_id`. Konsekuensinya: mekanisme yang sudah
dibuktikan di M0 berlaku di sini tanpa ditulis ulang —

- **BR-MON-01 ≈ BR-FE-04** — child hanya lahir dari induk APPROVED, sudah dibuktikan
  `[FE-CHILD-01/02]`. Yang tersisa di M4 adalah **akses** ke fase monev, bukan
  mekanisme pembuatannya.
- **BR-MON-05 ≈ BR-FE-08** — deadline keras plus override, sudah dibuktikan
  `[FE-OVERRIDE-01..05]`. Yang tersisa adalah penerapannya pada fase monev.

---

## 3. Siklus monev

| ID | Kelas | Yang dibuktikan | BR | Frame |
| -- | ----- | --------------- | -- | ----- |
| `MON-CYCLE-01` | denial | Fase monev tertutup selama pengajuan induk belum APPROVED | BR-MON-01 | RESEARCHER-05-01 |
| `MON-CYCLE-02` | happy | Induk APPROVED membuka fase Laporan Monev I | BR-MON-01 | RESEARCHER-05-01 |
| `MON-CYCLE-03` | denial | Laporan Monev II ditolak selama Monev I belum disubmit | BR-MON-02 | RESEARCHER-05-02 |
| `MON-CYCLE-04` | happy | Monev I yang disubmit **dan** evaluasinya selesai tanpa summary `open` membuka Monev II — `needs_review = true` | BR-MON-02 | RESEARCHER-05-01 |
| `MON-CYCLE-05` | boundary | Detail `needs_review = false` cukup disubmit — tidak menunggu evaluasi siapa pun | BR-MON-02 | RESEARCHER-05-01 |
| `MON-CYCLE-06` | denial | Dua laporan untuk `FormPhaseDetail` yang sama pada satu induk ditolak | BR-MON-03 | RESEARCHER-05-02 |
| `MON-CYCLE-07` | boundary | Induk berbeda boleh punya laporan pada `FormPhaseDetail` yang sama — batasnya per induk, bukan per researcher | BR-MON-03 | RESEARCHER-05-01 |
| `MON-CYCLE-08` | denial | Lewat deadline monev tanpa override, pengisian ditolak | BR-MON-05 | RESEARCHER-05-02 |
| `MON-CYCLE-09` | happy | Override yang masih berlaku membuka kembali fase monev | BR-MON-05 | OPERATOR-02-03 |
| `MON-CYCLE-10` | denial | Submission WITHDRAWN saat monev berjalan — laporan baru tidak bisa disubmit | BR-MON-08 | RESEARCHER-05-01 |
| `MON-CYCLE-11` | boundary | Laporan monev yang sudah terkirim sebelum penarikan tetap terbaca sebagai riwayat | BR-MON-08 | RESEARCHER-05-03 |
| `MON-CYCLE-12` | denial | Siklus yang di-freeze **tidak bisa dibuka lagi** — penarikan tidak dapat dibatalkan (BR-SM-11) | BR-MON-08 | RESEARCHER-05-01 |

Ketiganya menguji `canProceed()` sebagai **turunan keadaan**, bukan sebagai saklar yang
disetel seseorang. `MON-CYCLE-04` dan `MON-CYCLE-05` sengaja berpasangan: keduanya
membuktikan `needs_review` yang membedakan kapan evaluasi ikut jadi syarat.

`MON-CYCLE-07` menutup salah baca yang paling mungkin pada BR-MON-03: batasnya
**per parent**, bukan per researcher. Satu researcher yang memimpin dua penelitian
berhak mengisi laporan Monev I untuk keduanya.

`MON-CYCLE-11` menjaga integritas data pada kasus yang paling mudah dilanggar —
membekukan siklus tidak berarti menghapus apa yang sudah dilaporkan.

---

## 4. Reviewer monev

Reviewer monev **tidak diwarisi** dari pengajuan awal. Ia di-assign manual per siklus,
dengan daftar reviewer pengajuan awal sekadar sebagai saran.

| ID | Kelas | Yang dibuktikan | BR | Frame |
| -- | ----- | --------------- | -- | ----- |
| `MON-REVIEW-01` | happy | Operator menugaskan reviewer untuk siklus monev secara manual | BR-MON-04 | OPERATOR-05-02 |
| `MON-REVIEW-02` | boundary | Tidak ada reviewer yang otomatis ikut dari siklus sebelumnya | BR-MON-04 | OPERATOR-05-02 |
| `MON-REVIEW-03` | happy | Penugasan membuat `SubmissionReviewer` **baru** untuk siklus itu | BR-MON-06 | OPERATOR-05-02 |
| `MON-REVIEW-04` | boundary | Record `SubmissionReviewer` siklus sebelumnya tetap ada dan tidak dipakai ulang | BR-MON-06 | REVIEWER-03-02 |
| `MON-REVIEW-05` | boundary | Reviewer pengajuan awal yang `end_date`-nya lewat tidak muncul di daftar saran | BR-MON-07 | OPERATOR-05-02 |
| `MON-REVIEW-06` | happy | Reviewer awal yang masih aktif muncul sebagai saran, tapi Operator bebas memilih yang lain | BR-MON-07 | OPERATOR-05-02 |

`MON-REVIEW-04` penting: memakai ulang record lama akan menimpa evaluasi siklus
sebelumnya, dan jejak penilaian monev pertama hilang tanpa jejak.

### Keterlihatan hasil evaluasi monev — BR-MON-09

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `MON-REVIEW-07` | happy | Researcher melihat skor dan catatan monev **begitu reviewer submit**, tanpa menunggu siklus berakhir | RESEARCHER-05-03 |
| `MON-REVIEW-08` | denial | Sebelum reviewer submit, hasil evaluasi belum terlihat | RESEARCHER-05-03 |
| `MON-REVIEW-10` | happy | Pembukaan siklus memberi tahu **peneliti** — laporannya yang ditunggu | RESEARCHER-05-01 |
| `MON-REVIEW-11` | happy | Pembukaan siklus memberi tahu **reviewer yang ditugaskan** — penilaiannya yang ditunggu | REVIEWER-03-01 |
| `MON-REVIEW-12` | happy | Submit evaluasi monev memberi tahu peneliti | RESEARCHER-05-03 |
| `MON-REVIEW-13` | boundary | Siklus yang **belum dibuka** tidak memberi tahu siapa pun | RESEARCHER-05-01 |
| `MON-REVIEW-09` | boundary | Gerbang ini **tidak** berlaku untuk pengajuan awal — di sana skor tetap ditahan sampai APPROVED/REJECTED (`[REV-VIS-04]`) | RESEARCHER-02-03 |

BR-MON-09 adalah **pengecualian eksplisit** atas BR-REV-12, bukan tafsir bebas.
`MON-REVIEW-09` ada justru untuk menjaga pengecualian itu tetap sempit: satu
implementasi yang menyamaratakan keduanya akan membocorkan skor pengajuan awal lebih
cepat dari yang dibolehkan.

---

## 5. Research Output — luaran terealisasi

Bedakan dari **Target Luaran** (M1, BR-SM-14/15) yang merupakan luaran yang
*dijanjikan*. Taksonominya sama, momennya berbeda.

| ID | Kelas | Yang dibuktikan | BR | Frame |
| -- | ----- | --------------- | -- | ----- |
| `RO-OUTPUT-01` | denial | Menambah luaran pada submission yang belum APPROVED ditolak | BR-RO-01 | RESEARCHER-06-02 |
| `RO-OUTPUT-02` | happy | Submission APPROVED bisa menerima luaran | BR-RO-01 | RESEARCHER-06-02 |
| `RO-OUTPUT-03` | denial | `output_type = 'pks'` ditolak pada submission yang bukan CommunityService | BR-RO-02 | RESEARCHER-06-02 |
| `RO-OUTPUT-04` | happy | `pks` diterima pada submission CommunityService | BR-RO-02 | RESEARCHER-06-02 |
| `RO-OUTPUT-05` | boundary | `metadata.end_date` boleh kosong untuk PKS yang masih berjalan | BR-RO-03 | RESEARCHER-06-03 |
| `RO-OUTPUT-06` | denial | `ip` atau `prototype` tanpa satu pun berkas ditolak | BR-RO-04 | RESEARCHER-06-02 |
| `RO-OUTPUT-07` | happy | `ip` dengan minimal satu berkas tersimpan | BR-RO-04 | RESEARCHER-06-02 |
| `RO-OUTPUT-08` | denial | `output_type` di luar `output_type_definitions` ditolak — bukan string bebas | BR-RO-05 | RESEARCHER-06-02 |
| `RO-OUTPUT-09` | happy | Tipe baru yang sudah terdaftar di `output_type_definitions` langsung bisa dipakai tanpa migration | BR-RO-05 | ADMIN-08-10 |
| `RO-OUTPUT-10` | boundary | Luaran tetap ada sebagai data historis setelah submission WITHDRAWN | BR-RO-06 | RESEARCHER-06-01 |
| `RO-OUTPUT-11` | happy | `submitted_by` menambah dan menyunting luaran | BR-RO-07 | RESEARCHER-06-03 |
| `RO-OUTPUT-12` | happy | Co-Investigator juga boleh menambah dan menyunting luaran | BR-RO-07 | RESEARCHER-06-03 |
| `RO-OUTPUT-13` | denial | Member biasa hanya bisa membaca | BR-RO-07 | RESEARCHER-06-01 |
| `RO-OUTPUT-17` | denial | Tipe `prototype` tanpa berkas ditolak — BR-RO-04 menyebut dua tipe, bukan satu | BR-RO-04 | RESEARCHER-06-02 |
| `RO-OUTPUT-18` | happy | `prototype` dengan satu berkas tersimpan | BR-RO-04 | RESEARCHER-06-02 |
| `RO-OUTPUT-19` | denial | Reviewer hanya bisa membaca luaran — satu-satunya peran di tabel Access Control yang belum pernah punya skenario | BR-RO-07 | RESEARCHER-06-01 |
| `RO-OUTPUT-20` | boundary | Operator pemegang `outputs.manage` boleh menyunting — register #35 | BR-RO-07 | OPERATOR-02-03 |
| `RO-OUTPUT-21` | denial | Metadata yang ditandai `required` tidak boleh kosong | BR-RO-09 | RESEARCHER-06-02 |
| `RO-OUTPUT-22` | boundary | Field yang tidak `required` boleh kosong — termasuk `end_date` PKS berjalan | BR-RO-09 + BR-RO-03 | RESEARCHER-06-03 |

Kecocokan tipe antara target dan realisasi **sengaja tidak diuji sebagai penolakan** —
tidak ada skenario yang menolak luaran karena tipenya beda dari yang dijanjikan.
Penelitian memang menyimpang, dan memaksanya cocok mendorong peneliti berbohong di
target atau menyembunyikan luaran nyata. Pemenuhan janji diukur di pelaporan.

`RO-OUTPUT-12` sengaja **berbeda** dari `[SM-TARGET-08]` di M1, yang melarang
Co-Investigator mengubah *target*. Mengisi hasil bukan tindakan yang sama dengan
mengubah janji — perbedaan ini disengaja dan harus tetap terlihat di kode, bukan
diseragamkan demi kerapian.

`RO-OUTPUT-09` membuktikan sifat yang membuat sistem ini fleksibel: menambah tipe
luaran adalah perubahan data, bukan perubahan skema.

### Batas waktu luaran — BR-RO-08

Luaran tunduk pada deadline `FormPhaseDetail` "Upload Luaran", bukan pada mekanisme
waktu tersendiri ([ADR 0014](../../adr/0014-time-boxed-access-single-override-mechanism.md)).

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `RO-OUTPUT-14` | denial | Lewat deadline "Batas Akhir", menambah atau menyunting luaran ditolak | RESEARCHER-06-02 |
| `RO-OUTPUT-15` | boundary | Lewat deadline, luaran lama **tetap terbaca** read-only — membaca tidak butuh override (BR-FE-15) | RESEARCHER-06-01 |
| `RO-OUTPUT-16` | happy | `FormSubmissionOverride` yang masih berlaku membuka kembali penyuntingan luaran | OPERATOR-02-03 |

`RO-OUTPUT-15` memisahkan dua hal yang sering disamakan: **membaca** setelah tenggat
sudah dijamin BR-FE-15 dan tidak perlu izin khusus; yang butuh override hanya
**menulis**. Membedakannya memangkas jumlah override yang harus diterbitkan Operator.

---

## 6. Frame yang tercakup

RESEARCHER-05-01 · RESEARCHER-05-02 · RESEARCHER-05-03 · RESEARCHER-06-01 ·
RESEARCHER-06-02 · RESEARCHER-06-03 · OPERATOR-05-01 · OPERATOR-05-02 ·
OPERATOR-02-03 · REVIEWER-03-02 · ADMIN-08-10

OPERATOR-05-01 (Manajemen Siklus Monev) tercakup lewat `MON-CYCLE-04` dan
`MON-REVIEW-01`.

---

## 7. Yang menunggu keputusan

| # | Perkara | Dampak |
| - | ------- | ------ |
| ~~baru~~ | **Terselesaikan 2026-09-04.** Pertanyaannya salah bentuk — `canProceed()` method, bukan kolom; tidak ada yang menyetelnya. BR-MON-02 diperjelas; register #13 |
| ~~#19~~ | **Terselesaikan 2026-09-04** — sengaja **tidak** ditegakkan. Batasi yang harus benar (taksonomi), ukur yang sebaiknya benar (pemenuhan). Pemenuhan jadi metrik pelaporan, bukan validasi lintas-aggregate |
| ~~#20~~ | **Terselesaikan 2026-09-04** — tidak; WITHDRAWN terminal, state machine dijaga asiklik. BR-SM-11 diperluas, dikunci `[SM-WITHDRAW-05]`/`[MON-CYCLE-12]` |
| ~~register #10~~ | **Terselesaikan 2026-09-04.** Monev dikecualikan lewat BR-MON-09; dikunci `[MON-REVIEW-07..09]` |
| ~~#35~~ | **Terselesaikan 2026-09-08** — BR-RO-07 dan tabel Access Control bicara pada sumbu berbeda; bunyi BR-nya yang kabur. Sengaja **tidak** mengikuti preseden ADR 0015: anggaran komitmen, luaran laporan fakta. Dikunci `[RO-OUTPUT-19/20]` |
