# Desain: Fase yang Bisa Dipakai Ulang, Form Evaluasi Berversi, dan Pengaman Setup Operator

**Tanggal:** 2026-11-10
**Status:** Disetujui pemilik 2026-11-10
**Basis kode:** `feat/init-be-development` @ `061f44ae`

---

## 1. Latar belakang dan alasan

Pemilik produk menyampaikan keluhan dari sudut pandang Admin/Operator LPPM:

1. Tidak ada halaman untuk mengelola **fase** secara mandiri. Satu-satunya jalan
   ke fase adalah lewat sebuah Form (`/forms/{form}/phases`), padahal di basis
   data fase berdiri sendiri (`form_phases` tidak punya `form_id`).
2. Fase tidak bisa dipakai lagi di periode lain. Satu periode bisa punya banyak
   fase dan satu fase memang bisa ditautkan ke banyak periode
   (`submission_period_phases`), tapi **tenggat melekat di detail fase**
   (`form_phase_details.submission_date_id`, `evaluation_date_id`,
   `revision_date_id`), dan setiap `SubmissionDate` milik satu periode. Memakai
   fase di periode baru berarti memindahkan tenggatnya
   (`docs/plans/2026-11-03-fe-e3-phase.md`, K9b), sehingga angkatan lama ikut
   kehilangan tanggalnya. Contoh nyata: Monev II angkatan 2025 berjalan di 2026,
   bersamaan dengan pengajuan 2026 pada fase yang sama — hanya ada satu set
   tanggal.
3. **Form Evaluasi tidak bisa dipakai ulang.** `review_evaluation_forms` punya FK
   `form_phase_detail_id`; lembar penilaian yang sama harus dibuat ulang untuk
   setiap fase dan setiap tahun.
4. Satu fase bisa berisi beberapa jenis pengajuan (W92/W102), padahal siklus
   monev sebuah pengajuan didefinisikan sebagai "semua detail sesudah detail
   induk di fase yang sama" (`app/Queries/Monev/MonevCycles.php`). Kalau
   Penelitian berada di urutan 1 dan Pengmas di urutan 2, pengajuan Penelitian
   menganggap detail Pengmas sebagai siklus monevnya, dan kedua jenis terpaksa
   berbagi jadwal monev.
5. Operator bisa salah setup. Edit pada Form berlaku seketika bagi peneliti; tidak
   ada pratinjau, tidak ada ringkasan dampak, dan tidak ada cara memulihkan.

**Kenapa sekarang:** pemilik ingin memastikan kebutuhan bisnis terpenuhi sebelum
melanjutkan perbaikan end-to-end. Masalah 2 akan menghantam pada pergantian
periode pertama di produksi.

**Seperti apa sukses itu:** Operator menyiapkan periode baru dengan memilih fase
yang sudah ada dan mengisi tanggalnya — tanpa membangun ulang form evaluasi,
tanpa mengganggu angkatan yang masih berjalan, dan dengan sistem (bukan ingatan
operator) yang menolak setup yang cacat.

---

## 2. Tujuan, non-tujuan, anti-tujuan

**Tujuan**

- Fase adalah template tanpa tanggal yang bisa dipakai di banyak periode.
- Satu periode bisa membuka banyak fase.
- Satu Form Evaluasi bisa dipakai di banyak fase (dan karenanya banyak periode).
  Form laporan monev sudah bisa dipakai ulang karena ia Form biasa — dipertahankan.
- Satu angkatan dinilai dengan satu versi Form Evaluasi.
- Kesalahan setup operator ketahuan sebelum berdampak, dan riwayat pengajuan
  tetap terbaca seperti saat diisi.

**Non-tujuan**

- Mengubah alur keputusan review (lihat §9, pertanyaan terbuka P1).
- Versi snapshot penuh untuk Form pengajuan/monev/luaran. Pengaman berbasis waktu
  yang ada (BR-FE-11/12/18/23/24) dipertahankan.
- Memindahkan penilaian yang sedang berjalan ke versi baru secara otomatis.
- Pemulihan ke terbitan sebelumnya (§4.8) — boleh menyusul, tidak wajib di proyek
  ini.

**Anti-tujuan** (gagal walaupun "jalan")

- Perubahan yang **diam-diam tidak berlaku** karena operator lupa satu langkah
  (misalnya menduplikat lalu lupa mengganti rujukan). Ini alasan pemilik menolak
  pendekatan "kunci lalu duplikat" untuk Form Evaluasi.
- Dua proposal dalam satu angkatan dinilai dengan lembar berbeda tanpa ada yang
  memutuskannya secara sadar — skor dipakai operator sebagai masukan keputusan
  pendanaan.
- Angkatan lama berubah tanggal, lembar, atau tampilan karena angkatan baru
  disiapkan.

---

## 3. Batasan

- Aturan repo di `AGENTS.md` dan `.github/CODING_STANDARDS.md` berlaku penuh:
  business logic di `app/Actions/`, kueri baca di `app/Queries/`, status hanya
  lewat `transitionTo()`, setiap kueri submission di-scope (BR-SM-07), PostgreSQL,
  tanpa menyiasati PHPStan, kode BR disebut di komentar.
- **Dokumen DDD dan ADR adalah kesepakatan.** Desain ini mengubah beberapa aturan
  (daftar di §6). Perubahannya ditulis sebagai ADR baru dan revisi DDD **setelah
  pemilik menyetujuinya secara eksplisit**; agen tidak mengedit
  `docs/requirement-analysis/ddd/` atas inisiatif sendiri.
- Skenario BDD baru ditulis di `docs/requirement-analysis/bdd/` bersama test-nya.
  `pending.txt` dan `uncovered.txt` hanya boleh menyusut.
- Migrasi harus aman untuk data yang sudah ada, dan tidak dijalankan terhadap
  database berisi data sungguhan oleh agen.
- Gate verifikasi: `composer run quality` dan `npm run quality`.

---

## 4. Desain

### 4.1 Pustaka: Form, Form Evaluasi, Fase

Tiga jenis objek konfigurasi yang **tidak membawa tanggal** dan bisa dipakai ulang:

| Objek | Isi | Dipakai ulang oleh |
| --- | --- | --- |
| **Form** | form pengajuan, laporan monev, luaran | banyak fase |
| **Form Evaluasi** | lembar penilaian proposal / monev | banyak langkah di banyak fase |
| **Fase** | urutan langkah | banyak periode |

Setiap **langkah** fase (sekarang `FormPhaseDetail`) menunjuk satu Form lewat aturan
akses (seperti sekarang) dan **nol atau lebih Form Evaluasi** (sekarang 0..*, dengan
`is_required` dan `order` — sifat ini dipertahankan, tapi pindah ke relasi
langkah ↔ Form Evaluasi karena Form Evaluasi tidak lagi milik satu langkah).

Menu Admin dipisah: **Form**, **Form Evaluasi**, **Fase**, **Periode**. Halaman fase
tidak lagi bersarang di bawah satu Form.

### 4.2 Satu fase = satu jenis pengajuan

- Langkah pertama sebuah fase adalah **form pengajuan**. Langkah sesudahnya adalah
  laporan monev atau Upload Luaran.
- Sistem menolak fase yang memuat lebih dari satu **Form** pengajuan. Penelitian dan
  Pengmas berada di fase yang berbeda.
- Yang dihitung adalah Form, bukan baris langkah. Satu Form pengajuan boleh muncul
  di beberapa langkah pertama dengan aturan akses berbeda (mis. satu per fakultas;
  satu Form terbuka lewat dua kontrol akses sudah didukung `ResearcherStartOptions`).
  Langkah-langkah itu diperlakukan sebagai satu posisi "pengajuan" oleh cek kesiapan
  dan oleh `MonevCycles` (**keputusan penulis**).
- Konsekuensinya, `MonevCycles::details()` ("detail sesudah detail induk") tetap
  benar tanpa kasus khusus W92.
- Cara sistem membedakan "langkah pengajuan" dari "langkah laporan monev" dan
  "Upload Luaran" saat ini tersirat di jenis/siklus tenggat (`DeadlineType`,
  `cycle`). Karena langkah tetap menyebut jenis tenggat + siklusnya (§4.3), penanda
  itu tetap ada di langkah: Batas Submit tanpa siklus = pengajuan, dengan siklus n =
  laporan monev n, Batas Akhir = luaran.

### 4.3 Jadwal per periode

Tanggal tidak lagi disimpan di langkah fase. **Periode memegang satu kalender
tenggat** yang berlaku untuk semua fase di dalamnya (keputusan pemilik D1).

- Kalender periode tetap berbentuk `SubmissionDate` per jenis tenggat + siklus,
  seperti sekarang. BR-FE-25 (paling banyak satu Batas Submit per periode)
  dipertahankan.
- Langkah fase tidak menunjuk tanggal tertentu, tapi **menyebut jenis tenggat dan
  siklusnya**, misalnya "Batas Submit", "Batas Laporan Monev 1 + Batas Evaluasi
  Monev 1", atau "Batas Akhir". Tanggal sebenarnya diambil dari kalender periode
  milik pengajuan.
- Kalau dua jenis pengajuan butuh jadwal berbeda (mis. Penelitian dan Pengmas),
  operator membuat **dua periode**. Satu periode = satu kalender.
- Satu periode punya banyak fase; satu fase muncul di banyak periode, masing-masing
  memakai kalender periodenya sendiri.
- Setiap gerbang waktu (BR-FE-01/07/08/17/26, BR-MON-05, `MonevAccessGate`,
  `SubmissionPeriodGate`, `FormPhaseDetail::isWithinDeadline`, `StartSubmission`,
  `ResearcherStartOptions`) membaca tenggat dari **periode milik pengajuan + langkah
  yang sedang dikerjakan**, bukan dari langkah saja.
- `FormSubmissionOverride` (per pengajuan + langkah) tidak berubah bentuk:
  pengecualian waktu tetap satu mekanisme (ADR 0014).
- BR-SCH-07 (force close hanya memblokir tahap pengajuan, bukan siklus monev)
  tetap berlaku.
- Angkatan lama memakai tanggal periodenya sendiri walaupun tanggal itu jatuh di
  tahun berikutnya.

### 4.3a Struktur fase berversi, dipaku per angkatan

Keputusan pemilik D2. Tanpa ini, menyiapkan fase untuk 2026 bisa mengubah angkatan
2025 yang monevnya masih berjalan, karena pengajuan merujuk langkah secara langsung
(`form_submissions.form_phase_detail_id`) dan siklus monev dihitung saat dibaca
(`MonevCycles::details`).

- Setiap penerbitan Fase (§4.5) membentuk **versi struktur** yang beku: daftar
  langkah, urutannya, Form tiap langkah beserta aturan aksesnya, jenis tenggat +
  siklus tiap langkah, `needs_review`, dan Form Evaluasi yang terpasang (wajib/tidak,
  urutan).
- Struktur dipaku untuk sebuah angkatan saat **pengajuan pertama di angkatan itu
  dibuat**. Sebelum itu, angkatan memakai versi terbit terbaru, jadi perbaikan fase
  sebelum pengajuan pertama masuk tetap terbawa.
- Terbitan sesudahnya berlaku untuk angkatan yang belum punya pengajuan.
- **Tidak ada** aksi "terapkan struktur fase baru ke angkatan berjalan". Mengubah
  urutan langkah di tengah jalan terlalu berisiko.
- Siklus monev sebuah pengajuan, gerbang langkah (BR-MON-02), dan cek kesiapan
  membaca **versi struktur angkatan pengajuan itu**, bukan fase terkini.
- Ini terpisah dari versi Form Evaluasi (§4.4). Versi struktur fase menentukan
  **Form Evaluasi mana** yang terpasang di sebuah langkah; §4.4 menentukan **versi
  isi** lembar itu yang dipakai.

### 4.4 Form Evaluasi berversi, dipaku per angkatan

**Angkatan** = pasangan Periode × Fase.

- Form Evaluasi berdiri sendiri dan punya riwayat versi. Setiap **penerbitan**
  (§4.5) membentuk versi baru yang isinya beku (field, opsi, bobot, aturan validasi).
- Kunci versi berlaku **per angkatan × langkah × Form Evaluasi** (**keputusan
  penulis**). Lembar penilaian proposal terkunci saat penilaian proposal pertama di
  angkatan itu dimulai; lembar Monev I baru terkunci saat penilaian Monev I pertama
  dimulai, setahun kemudian, sehingga perbaikan lembar monev sebelum itu tetap
  terbawa.
- Selama belum ada reviewer yang **mulai mengisi** (draf penilaian pertama
  tersimpan) pada langkah itu di angkatan itu, yang dipakai adalah versi terbit
  terbaru.
- Begitu penilaian pertama dimulai, versi itu terkunci. Semua reviewer langkah itu
  di angkatan itu, termasuk yang ditugaskan belakangan, memakai versi yang sama.
- Terbitan sesudahnya berlaku untuk angkatan atau langkah yang belum mulai.
- **Aksi eksplisit "Terapkan versi baru ke angkatan ini"** (operator, per langkah):
  menampilkan berapa penilaian yang terdampak, lalu memindahkan langkah itu di
  angkatan itu ke versi terbaru. Penilaian yang sudah disubmit **dibuka ulang** untuk
  diisi lagi; jawaban lama disimpan sebagai riwayat dan tetap terbaca. Ini
  **pengecualian atas BR-REV-08** (respons tidak bisa diedit setelah submitted) dan
  harus tercatat sebagai aturan baru. Reviewer yang terdampak diberi tahu.
- Aksi itu **ditolak** bila pada langkah itu di angkatan itu sudah ada pengajuan
  yang hasil review-nya ditetapkan (keluar dari menunggu keputusan; untuk siklus
  monev: evaluasinya selesai). Penolakan menyebut berapa pengajuan yang sudah
  diputuskan. Tidak ada transisi status baru dan keputusan yang sudah diambil tidak
  dibatalkan (**keputusan penulis**; aksinya diperkirakan jarang).
- Setiap skor diberi label versinya di layar (reviewer, operator, peneliti sesuai
  BR-REV-11/12, BR-MON-09) dan di ekspor.
- Keputusan APPROVED/NEEDS_REVISION saat ini tidak memakai skor (BR-REV-05/06), jadi
  pencampuran versi tidak memengaruhi keputusan otomatis. Pengaman ini untuk
  kebandingan skor yang dibaca operator.
- Data yang ada: setiap Form Evaluasi menjadi **versi 1**, dan setiap angkatan ×
  langkah yang sudah punya penilaian dipaku ke versi 1.

### 4.5 Draf → Pratinjau → Terbitkan

Berlaku untuk **Form, Form Evaluasi, dan Fase**.

- Edit masuk ke draf; yang sedang berjalan tetap memakai terbitan terakhir.
- **Pratinjau** menampilkan form persis seperti yang dilihat peneliti/reviewer.
- **Terbitkan** menampilkan ringkasan dampak sebelum dikonfirmasi, misalnya:
  "2 field baru wajib — berlaku untuk pengajuan yang dibuat sesudah ini; 14 draf
  berjalan tidak terpengaruh; label 'Anggaran' diubah pada field yang sudah dijawab
  30 pengajuan."
- Draf yang belum diterbitkan ditandai banner yang terlihat di halaman daftar dan
  halaman editnya. Lupa menerbitkan itu terlihat dan tidak merusak.
- Untuk Form pengajuan/monev/luaran, aturan waktu yang ada tetap berlaku, tapi
  **"waktu berubah" = waktu diterbitkan**, bukan waktu diketik. `created_at`,
  `required_since`, dan soft delete yang dibaca BR-FE-11/12/18 harus mencerminkan
  saat penerbitan.
- BR-FE-13 (prompt notifikasi ke peneliti yang punya draf aktif) berpindah ke
  momen penerbitan.
- Form/fase yang **belum pernah dipakai** boleh diterbitkan tanpa ringkasan dampak,
  tapi tetap lewat alur yang sama supaya operator hanya mempelajari satu cara.

### 4.6 Peringatan pada field yang sudah dijawab

- Tipe field sudah terkunci (BR-FE-23) — tetap.
- Mengubah **label** field atau **label opsi** yang sudah dijawab memunculkan
  peringatan di editor: "Hanya untuk memperbaiki ketikan. Kalau pertanyaannya
  berbeda, tambahkan field baru — jawaban lama tetap tercatat di field lama."
- Peringatan, bukan blokir.

### 4.7 Rekam yang dilihat pengisi

- Saat pengajuan, laporan monev, atau luaran **disubmit**, teks pertanyaan dan label
  opsi yang terlihat saat itu ikut disimpan bersama jawabannya.
- Tampilan pengajuan yang sudah disubmit memakai rekaman itu, sehingga perubahan
  label sesudahnya tidak mengubah apa yang terbaca.
- Draf (belum disubmit) memakai teks terkini.
- Pengajuan lama yang sudah disubmit sebelum fitur ini ada: memakai teks terkini
  seperti sekarang (tidak ada yang bisa direkonstruksi).
- Form Evaluasi tidak memerlukan ini karena versinya sendiri sudah beku.

### 4.8 Pemulihan terbitan (menyusul, opsional di proyek ini)

Karena setiap penerbitan tercatat, operator bisa memulihkan isi terbitan sebelumnya
sebagai draf baru, lalu menerbitkannya lewat alur §4.5.

### 4.9 Cek kesiapan angkatan

Sekarang periode tidak punya aksi "buka"; statusnya turunan dari Batas Submit
(`SubmissionPeriodGate`, `SubmissionPeriodList`). Desain ini **tidak menambah state
atau aksi "Buka periode"** (**keputusan penulis**). Cek kesiapan dijalankan **per
angkatan (Periode × Fase)**:

- Angkatan yang gagal cek **tidak menerima pengajuan baru**: `StartSubmission` dan
  `ResearcherStartOptions` menolaknya, sama seperti fase nonaktif sekarang. Angkatan
  lain di periode yang sama tetap berjalan.
- Operator melihat hasilnya sebagai **daftar cek** per angkatan di halaman periode,
  bukan galat yang muncul satu per satu.
- Angkatan yang **sudah punya pengajuan** tidak pernah diblokir oleh cek ini. Cacat
  yang muncul belakangan (mis. dari migrasi) ditampilkan sebagai peringatan, supaya
  peneliti yang sedang mengisi tidak terputus.

Minimal yang diperiksa:

- setiap fase yang ditautkan punya tanggal untuk semua langkahnya;
- setiap fase memuat tepat satu form pengajuan sebagai langkah pertama;
- langkah yang `needs_review` punya minimal satu Form Evaluasi wajib yang terbit;
- urutan tanggal masuk akal (BR-FE-17: evaluasi tidak sebelum submit, revisi tidak
  sebelum evaluasi, siklus monev berurutan);
- tidak ada Form/Form Evaluasi/Fase yang dipakai periode itu masih berupa draf yang
  belum pernah terbit.

Tanggal periode baru boleh diisikan dari periode sebelumnya lalu digeser setahun,
sebagai titik awal yang diperiksa operator (**keputusan penulis**, belum diminta
pemilik — boleh dibuang kalau membebani).

---

## 5. Migrasi data

- `SubmissionDate` yang ada sudah milik periode dan tetap di tempatnya. Rujukan
  tanggal di `form_phase_details` diganti dengan jenis tenggat + siklus dari tanggal
  yang dirujuk.
- Pengajuan yang sudah ada tidak selalu berada di periode pemilik tanggal langkahnya
  (keadaan antara K9b). Untuk setiap pengajuan, tenggat yang dibaca setelah migrasi
  harus sama dengan yang dibacanya sebelum migrasi. Kalau kalender periode
  pengajuan tidak punya tanggal untuk jenis + siklus yang dibutuhkan, migrasi
  melaporkannya, bukan diam-diam mengarang tanggal.
- Setiap fase menjadi **versi struktur 1**, dan setiap angkatan yang sudah punya
  pengajuan dipaku ke versi 1.
- Setiap `review_evaluation_forms` menjadi Form Evaluasi mandiri versi 1; relasi ke
  langkahnya dipertahankan lewat relasi langkah ↔ Form Evaluasi.
- `ReviewerFormAssignment` / `ReviewFormResponse` yang sudah ada mengacu ke versi 1.
- Fase lama yang memuat lebih dari satu form pengajuan **tidak dipecah otomatis**.
  Fase itu ditandai dan muncul di cek kesiapan sampai Admin merapikannya
  (**keputusan penulis**).
- Form dan fase yang ada dianggap sudah terbit (terbitan pertama = keadaan saat
  migrasi).
- Migrasi harus bisa diuji dengan seeder `EndToEndFlowSeeder` dan data berbentuk
  produksi tanpa kehilangan pengajuan, penilaian, override, atau jawaban.

---

## 6. Dampak pada DDD, ADR, dan BDD

Perubahan berikut **membutuhkan persetujuan pemilik** sebelum dokumen DDD diedit.
Usulannya: satu ADR baru ("Fase sebagai template, jadwal milik periode, Form
Evaluasi berversi per angkatan") ditambah revisi bagian DDD berikut.

| Dokumen | Yang berubah |
| --- | --- |
| `ddd/generic/01_form_engine.md` | activity diagram konfigurasi (Form → akses → Fase dibalik menjadi pustaka + jadwal); class diagram (`FormPhaseDetail` kehilangan FK tanggal; `ReviewEvaluationForm` lepas dari detail, punya versi); `FormPhaseDetail` menyebut jenis tenggat + siklus, bukan menunjuk tanggal; BR-FE-07, BR-FE-17, BR-FE-22, BR-FE-26 dan BR-FE-27 ditulis ulang terhadap kalender periode; BR-FE-25 tetap; aturan baru untuk versi struktur fase yang dipaku per angkatan; BR-FE-13 pindah ke penerbitan; aturan baru untuk satu-jenis-pengajuan-per-fase, draf/terbit, rekaman teks saat submit, cek kesiapan |
| `ddd/core/02_review.md` | aturan baru pemakuan versi per angkatan; pengecualian BR-REV-08 untuk aksi "terapkan versi baru" |
| `ddd/supporting/02_monev.md` | diagram "Satu FormPhase, Satu Lifecycle" — tenggat dibaca dari jadwal periode |
| `ddd/supporting/04_scheme.md` | BR-SCH-07 dibaca terhadap jadwal (substansinya tetap) |
| `ddd/02_ubiquitous_language.md` | istilah baru: Angkatan, Jadwal, Versi Form Evaluasi, Terbitkan |
| `docs/adr/0014-…` | sumbu "kapan" kini dibaca dari jadwal periode; substansi satu mekanisme override tetap |

Skenario BDD baru (ID diberikan saat ditulis) minimal untuk: fase dipakai dua
periode dengan tanggal berbeda; angkatan lama tidak berubah saat angkatan baru
disiapkan; penolakan dua form pengajuan dalam satu fase; pemakuan versi per angkatan;
aksi terapkan versi baru; draf tidak berlaku sebelum terbit; ringkasan dampak; label
direkam saat submit; cek kesiapan menolak pengajuan baru di angkatan yang cacat
tanpa memblokir angkatan yang sudah berjalan.

---

## 7. Keputusan yang sudah diambil pemilik (2026-11-10)

1. Satu fase hanya berisi satu jenis pengajuan; Penelitian dan Pengmas di fase
   berbeda.
2. Satu periode bisa punya banyak fase; satu fase bisa dipakai di periode lain.
3. Form Evaluasi dan form monev bisa dipakai di lebih dari satu fase.
4. Form Evaluasi memakai **versi**, bukan "kunci lalu duplikat", karena operator bisa
   lupa mengganti rujukan.
5. Satu angkatan dinilai dengan satu versi; ada aksi eksplisit untuk menerapkan versi
   baru ke angkatan berjalan.
6. Approve dan tolak adalah keputusan operator; skor reviewer adalah masukan untuk
   keputusan itu (lihat P1 — sistem sekarang belum begitu).
7. Alur Draf → Pratinjau → Terbitkan disetujui sebagai pengaman kesalahan setup.
8. **D1:** satu kalender tenggat per periode untuk semua fase di dalamnya; jadwal
   berbeda = periode berbeda.
9. **D2:** struktur fase berversi dan dipaku per angkatan saat pengajuan pertama
   dibuat; tidak ada aksi menerapkan struktur baru ke angkatan berjalan.

### Keputusan penulis (untuk diperiksa pemilik)

- Kunci versi Form Evaluasi per angkatan × langkah × Form Evaluasi (§4.4).
- "Terapkan versi baru" ditolak bila sudah ada pengajuan yang diputuskan (§4.4).
- Tidak ada aksi "Buka periode"; cek kesiapan per angkatan, angkatan berjalan tidak
  diblokir (§4.9).
- "Satu jenis pengajuan" dihitung per Form, bukan per baris langkah (§4.2).
- Tanggal periode baru bisa diisikan dari periode sebelumnya + 1 tahun (§4.9).
- Fase lama berisi beberapa Form pengajuan tidak dipecah otomatis (§5).

## 8. Diserahkan ke builder

- Bentuk tabel/kolom untuk versi struktur fase, pemakuan angkatan, versi Form Evaluasi, relasi langkah ↔ Form
  Evaluasi, rekaman teks saat submit, dan penanda jenis langkah.
- Cara menyimpan versi struktur fase dan versi Form Evaluasi (salin baris vs snapshot
  JSON), selama versi terbit tidak bisa berubah, skor lama bisa dihitung ulang
  persis, dan pengajuan lama tetap menunjuk langkah yang sama.
- Tata letak layar dan navigasi, mengikuti sistem desain FE v2
  (`docs/design/fe-v2-design.md`).
- Urutan pengerjaan dan pemecahan menjadi irisan, selama setiap irisan bisa dirilis
  tanpa merusak data.
- Kapan tepatnya "mulai mengisi" terjadi (aksi mana yang membuat draf penilaian
  pertama), selama maknanya draf pertama tersimpan.

## 9. Pertanyaan terbuka

- **P1 (dikerjakan sebagai proyek terpisah, 2026-11-10 — `2026-11-10-keputusan-operator-design.md`):** pemilik menyatakan approve
  adalah keputusan operator dengan skor reviewer sebagai masukan. Sistem dan DDD
  sekarang **meng-approve otomatis** begitu semua reviewer selesai dan tidak ada
  ReviewSummary `open` (BR-REV-05, `app/Actions/Review/EvaluateSubmissionOutcome.php`);
  hanya penolakan yang manual (BR-REV-07, ADR 0006).
- **P2 (terverifikasi dan diperbaiki terpisah, 2026-11-10 — `FormPhaseDetail::opensSubmission()`, `tests/Feature/Monev/MonevCycleNotStartableTest.php`):** `ResearcherStartOptions` dan
  `StartSubmission` hanya mengecualikan langkah Upload Luaran. Kalau form laporan
  monev berbeda dari form pengajuan, form itu mungkin ditawarkan sebagai "pengajuan
  baru". Desain §4.2 menutupnya, tapi perlu dipastikan dan diberi test.
- **P3:** pemilik menyebut monev "70%" dan "100%"; sistem hanya mengenal Siklus I/II
  tanpa persentase. Pemilik juga menyebut reviewer mengisi form monev "beserta
  kelengkapannya", sedangkan DDD menetapkan peneliti mengisi laporan + kelengkapan dan
  reviewer mengisi lembar penilaian. Perlu dikonfirmasi; tidak mengubah desain ini.
