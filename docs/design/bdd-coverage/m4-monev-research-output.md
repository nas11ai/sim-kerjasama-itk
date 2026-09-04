# M4 — Monev & Research Output

**16 BR perilaku · 33 skenario · 2 berkas `.feature`**

Yang terjadi **setelah** pengajuan disetujui: siklus monitoring-evaluasi, dan pelaporan
luaran yang benar-benar terwujud.

Sumber: [`supporting/02_monev.md`](../../requirement-analysis/ddd/supporting/02_monev.md),
[`supporting/03_research_output.md`](../../requirement-analysis/ddd/supporting/03_research_output.md).

---

## 1. Berkas dan rentang ID

| Berkas | Rentang ID | BR |
| ------ | ---------- | -- |
| `supporting/monev.feature` | `MON-CYCLE-01..11`, `MON-REVIEW-01..09` | BR-MON-01, BR-MON-02, BR-MON-03, BR-MON-04, BR-MON-05, BR-MON-06, BR-MON-07, BR-MON-08, BR-MON-09 |
| `supporting/research_output.feature` | `RO-OUTPUT-01..13` | BR-RO-01, BR-RO-02, BR-RO-03, BR-RO-04, BR-RO-05, BR-RO-06, BR-RO-07 |

Semua skenario bertag `@M4`.

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
| `MON-CYCLE-03` | denial | Laporan Monev II ditolak selama `can_proceed` Monev I belum `true` | BR-MON-02 | RESEARCHER-05-02 |
| `MON-CYCLE-04` | happy | `can_proceed = true` pada Monev I membuka Monev II | BR-MON-02 | RESEARCHER-05-01 |
| `MON-CYCLE-05` | boundary | Detail yang `needs_review = false` tetap harus terisi sebelum detail berikutnya terbuka | BR-MON-02 | RESEARCHER-05-01 |
| `MON-CYCLE-06` | denial | Dua laporan untuk `FormPhaseDetail` yang sama pada satu induk ditolak | BR-MON-03 | RESEARCHER-05-02 |
| `MON-CYCLE-07` | boundary | Induk berbeda boleh punya laporan pada `FormPhaseDetail` yang sama — batasnya per induk, bukan per researcher | BR-MON-03 | RESEARCHER-05-01 |
| `MON-CYCLE-08` | denial | Lewat deadline monev tanpa override, pengisian ditolak | BR-MON-05 | RESEARCHER-05-02 |
| `MON-CYCLE-09` | happy | Override yang masih berlaku membuka kembali fase monev | BR-MON-05 | OPERATOR-02-03 |
| `MON-CYCLE-10` | denial | Submission WITHDRAWN saat monev berjalan — laporan baru tidak bisa disubmit | BR-MON-08 | RESEARCHER-05-01 |
| `MON-CYCLE-11` | boundary | Laporan monev yang sudah terkirim sebelum penarikan tetap terbaca sebagai riwayat | BR-MON-08 | RESEARCHER-05-03 |

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

`RO-OUTPUT-12` sengaja **berbeda** dari `[SM-TARGET-08]` di M1, yang melarang
Co-Investigator mengubah *target*. Mengisi hasil bukan tindakan yang sama dengan
mengubah janji — perbedaan ini disengaja dan harus tetap terlihat di kode, bukan
diseragamkan demi kerapian.

`RO-OUTPUT-09` membuktikan sifat yang membuat sistem ini fleksibel: menambah tipe
luaran adalah perubahan data, bukan perubahan skema.

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
| baru | Apa arti `can_proceed` secara operasional — ditetapkan reviewer, otomatis saat laporan disubmit, atau manual Operator | `MON-CYCLE-03/04` menguji akibatnya, bukan pemicunya. DDD tidak menyebutkan siapa yang menyetelnya |
| baru | Apakah target luaran (BR-SM-15) dan luaran realisasi wajib cocok tipenya saat kegiatan selesai | Tidak diatur BR mana pun; kalau LPPM butuh, ini aturan baru — bukan tafsir |
| baru | Siklus monev yang di-freeze karena WITHDRAWN: bisakah dibuka lagi kalau penarikan dibatalkan | BR-MON-08 dan BR-SM-11 tidak membahas pembatalan penarikan |
| ~~register #10~~ | **Terselesaikan 2026-09-04.** Monev dikecualikan lewat BR-MON-09; dikunci `[MON-REVIEW-07..09]` |
