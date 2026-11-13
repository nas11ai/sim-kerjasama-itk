# Seeder Data Uji QA (Design)

Langkah ketiga urutan K1 (`docs/plans/2026-11-13-paket-a-masukan-pemilik.md`): Paket A → review UI →
**seeder data uji** → staging → matriks QA Excel.

## Maksud dan alasannya

Pemilik (chat 2026-11-10) ingin QA mencoba aplikasi di atas data yang "banyak user/data", di staging bersama
(Neon Postgres + storage S3-kompatibel, langkah berikutnya), lalu menjalankan matriks uji Excel (langkah terakhir).
Sekarang database dev hanya berisi data referensi dan satu alur kecil (`EndToEndFlowSeeder`: 4 akun, nol pengajuan),
jadi hampir setiap layar QA kosong: daftar tanpa paginasi, statistik tanpa sebaran, antrean penilai kosong,
monev dan luaran tak pernah terlihat.

QA berjumlah 3–4 orang dan **menguji serentak** (jawaban pemilik 2026-11-13). Data yang dipakai bersama akan saling
ditimpa: begitu penguji A menyetujui pengajuan X, penguji B tidak bisa lagi menguji X dalam status Menunggu Penilaian.

## Goals

- G1 Setiap penguji punya **set data sendiri** (`qa1` … `qa4`): akun per peran dan pengajuan di **setiap status**
  state machine, sehingga ia bisa menjalankan seluruh matriks tanpa menunggu penguji lain.
- G2 Ada cukup volume di layar tingkat institusi (Beranda Operator/Admin, statistik, daftar pengguna, log audit)
  supaya paginasi, filter, sebaran per fakultas/jurusan/prodi, dan perbandingan **sekarang vs tahun lalu**
  (prinsip statistik pemilik: sekarang / lalu / langkah berikutnya) benar-benar teruji.
- G3 Setiap baris data lahir dari **use case asli** (Action), jadi yang dilihat QA adalah keadaan yang memang bisa
  dicapai aplikasi: ada putaran review di balik Disetujui, ada entri log audit, ada notifikasi.
- G4 **Deterministik**: nama akun, judul pengajuan, dan status per set selalu sama, supaya matriks Excel bisa
  menyebut "masuk sebagai `qa2-peneliti1@…`, buka pengajuan `[QA2] …`".
- G5 Reset mudah: `migrate:fresh --seed` lalu satu perintah seeder mengembalikan semua set ke titik awal.

## Non-goals

- Menyiapkan staging (Neon, R2, `.env` staging): langkah berikutnya.
- Matriks QA Excel: langkah terakhir; dokumen ini hanya memastikan datanya bisa dirujuk.
- Uji beban / performa (puluhan ribu baris).
- Merapikan `DummyUserSeeder` (4 akun `@example.com` berperan lama `Super Admin`/`Mahasiswa`) dan fixture E2E
  `operator-flow.php`; dicatat sebagai bug note, tidak diubah di sini.
- Mengubah perilaku aplikasi. Kalau seeder menabrak bug aplikasi, bug itu dicatat
  (`docs/plans/2026-11-11-fase-reuse-catatan-bug.md`), bukan disiasati di seeder.

## Anti-goals (gagal walau "jalan")

- Data yang tidak mungkin lahir dari alur nyata (status ditulis langsung, Disetujui tanpa penilaian). QA akan
  melaporkan "bug" yang sebenarnya artefak data.
- Email yang bisa sampai ke orang sungguhan.
- Seeder yang gagal di tengah dan meninggalkan data separuh tanpa memberi tahu.
- Seeder yang bisa dijalankan terhadap production.

## Constraints (dari kode)

- Status hanya berubah lewat `transitionTo()` (AGENTS.md aturan 1); Action-lah yang memanggilnya.
- BR-SM-16, indeks `one_active_submission_per_period_scheme`: satu pengajuan aktif per peneliti per (periode, skema).
  Volume didapat dari banyak peneliti × beberapa skema × dua periode, bukan banyak pengajuan per peneliti.
- `SubmitFormSubmission` mensyaratkan fase terbuka (`PhaseAccessGate`), akun terverifikasi (BR-IAM-04), dan seluruh
  isian wajib terisi (BR-SM-02, Temporal Field Binding BR-FE-11). Data tahun lalu harus dibuat **pada waktu itu**.
- Monev hanya untuk induk Disetujui dengan siklus terbuka (BR-FE-04, BR-MON-01/02/08, `MonevAccessGate`).
- Notifikasi memakai kanal dari tabel (`NotificationChannelSeeder`); antrean `database`; broadcast `reverb`.
- PostgreSQL; tier strict PHPStan berlaku untuk kode di `app/` baru, bukan `database/seeders/` (tetap harus lolos
  PHPStan level proyek).

## Approaches

| | Cara | Untung | Rugi |
|---|---|---|---|
| **A (dipilih)** | Seeder memanggil Action asli untuk setiap perubahan state; factory hanya untuk bahan mentah (nama, teks jawaban). Data tahun lalu dibuat dengan memundurkan jam (`Carbon::setTestNow`). | Data selalu sah (G3); seeder ikut rusak bila invarian berubah, jadi ia juga menjadi uji integrasi. | Paling lambat ditulis dan dijalankan (target < 2 menit); Action yang butuh `UploadedFile` perlu berkas buatan. |
| B | Factory dengan `status` langsung, seperti `operator-flow.php`. | Cepat ditulis, status apa pun bisa. | Melanggar aturan `transitionTo()`; tanpa putaran review, audit, notifikasi: persis anti-goal pertama. |
| C | Dump SQL dari data yang diklik manual. | Sangat realistis. | Busuk di setiap migration; tidak bisa diregenerasi; tidak deterministik. |

Pemilik menyerahkan "cara"-nya ("langsung jalan pakai usulanmu"), jadi A adalah keputusan saya.

## Rancangan

### Titik masuk

`database/seeders/QaDataSeeder.php`, dijalankan setelah `DatabaseSeeder`:

```
php artisan migrate:fresh --seed
php artisan db:seed --class=QaDataSeeder
```

- Menolak berjalan bila `app()->isProduction()` (pesan jelas, keluar tanpa menulis). Staging memakai
  `APP_ENV=staging`, bukan `production` (keputusan saya; dicatat untuk langkah staging).
- **Tidak idempoten, sengaja**: bila akun `qa-admin@simpas.test` sudah ada, ia berhenti dengan pesan "jalankan
  migrate:fresh --seed dulu". Reset = database segar (G5); ini lebih sederhana dan lebih jujur daripada
  `firstOrCreate` atas ratusan baris yang sebagian sudah berubah status di tangan QA.
- **Tanpa transaksi luar** (keputusan saya, setelah builder check). `Deliver::to()` menelan setiap `Throwable`;
  di dalam satu transaksi PostgreSQL, tulisan notifikasi yang gagal membatalkan transaksi diam-diam dan seeder baru
  meledak jauh kemudian dengan 25P02. Sebagai gantinya `qa-admin` dibuat **paling awal**, sehingga run yang gagal di
  tengah tetap ditolak saat diulang dan pesannya menyuruh `migrate:fresh --seed`. Exception tidak pernah ditangkap
  seeder: gagal = gagal keras (anti-goal ketiga). Action sendiri tetap memakai transaksinya.
- Seeder tidak memalsukan notifikasi. Selama run ia memasang `queue.default = sync` dan
  `broadcasting.default = log` lewat `config()` (bukan cabang lingkungan): notifikasi ber-antrean langsung terkirim
  dengan jam yang sedang dimundurkan, jadi lonceng notifikasi riwayat bertanggal benar dan tidak ada ratusan job
  basi di tabel `jobs`. Mail staging/dev memakai `log`, dan semua email ber-domain `simpas.test` (TLD `.test`
  dicadangkan RFC 6761, tidak pernah terkirim).
- **Tahun relatif terhadap jam run** (keputusan saya): periode "tahun ini" = `now()->year`, periode "tahun lalu" =
  tahun sebelumnya. Tahun statistik dibaca dari audit "Pengajuan disubmit" (`SubmissionYear`), jadi reset di tahun
  mana pun tetap memberi perbandingan sekarang vs lalu. Teks di dokumen ini menyebut 2025/2026 sebagai contoh run 2026.
- **Urutan waktu** (keputusan saya, dari Temporal Field Binding `FormField::created_at`): katalog, akun, dan profil
  dibuat lebih dulu dengan jam di **awal tahun lalu**; lalu riwayat tahun lalu; lalu jam kembali ke sekarang untuk
  periode tahun ini. Akibatnya tanggal "Terdaftar" akun QA jatuh di tahun lalu, dan itu wajar untuk dosen.
- Faker `id_ID` dengan seed tetap untuk teks bebas (G4).
- Akhir run mencetak tabel akun per set dan jumlah pengajuan per status.

Seeder dipecah per bagian supaya tiap kelas pendek dan bisa dibaca: `QaDataSeeder` (orkestrasi + penjaga),
`Qa/QaCatalog` (skema, periode, form, fase, form evaluasi, pengumuman), `Qa/QaAccounts` (akun per set),
`Qa/QaSubmissionScenarios` (pengajuan per status), `Qa/QaBackground` (volume institusi). Nama kelas akhir
diserahkan ke builder.

### Katalog bersama (sekali)

- Skema: 3, yaitu Penelitian Dasar, Penelitian Terapan, Pengabdian kepada Masyarakat, masing-masing dengan
  `rules.min_reviewer_count` = 2, batas anggaran dan anggota berbeda.
- Periode: **Periode 2025** (tertutup; seluruh tanggalnya di masa lalu) dan **Periode 2026** (terbuka: Batas Submit
  +3 bulan, Batas Evaluasi +4 bulan, Batas Revisi, Batas Laporan Monev 1, Batas Akhir luaran).
- Form pengajuan: 2 (Penelitian, Pengabdian), diterbitkan lewat jalur Form Builder, dengan isian yang mewakili tipe
  yang ada: judul (`submission_title`), abstrak (`textarea`), kata kunci (`text`), skema (`scheme_selector`),
  TKT (`trl_selector`), berkas proposal (`file`, wajib), tanggal mulai (`date`), bidang (`select`).
- Fase per form: langkah Pengajuan (direview, form evaluasi wajib), langkah Laporan Monev 1 (`submit` siklus 1),
  langkah Luaran (`output`). Diterbitkan lewat `PublishPhaseVersion`; periode ditautkan lewat
  `CreateSubmissionPeriod`.
- Form evaluasi: "Penilaian Substansi" berisi skor (`number`) dan catatan (`textarea`).
- Akses form: `submissions.create` untuk Institusi (berlaku ke seluruh pohon, ADR 0013).
- Pengumuman: 3 terbit, 1 ditarik.
- Admin bersama: `qa-admin@simpas.test` (peran `admin`).

### Akun per set (`k` = 1..4)

| Akun | Peran | Catatan |
|---|---|---|
| `qa{k}-operator@simpas.test` | operator | |
| `qa{k}-penilai1@`, `qa{k}-penilai2@` | reviewer_internal | baris `Reviewer` aktif |
| `qa{k}-penilai-luar@` | reviewer_external | |
| `qa{k}-peneliti01@` … `qa{k}-peneliti12@` | researcher, profil lengkap, terverifikasi | satu peneliti per skenario (nomor = baris tabel pengajuan), supaya BR-SM-16 dan "satu draf terbaru per form+periode" (`ResolveWizardSubmission`) tidak saling bentur; prodi berbeda, tersebar di 3 fakultas |
| `qa{k}-peneliti-baru@` | researcher, profil **pending** | antrean verifikasi NIDN |
| `qa{k}-peneliti-ditolak@` | researcher, NIDN **ditolak** | |
| `qa{k}-anggota@` | researcher | anggota tim di pengajuan peneliti1 |

Kata sandi = email (konvensi `EndToEndFlowSeeder`). Plus per set: 1 undangan aktif, 1 undangan dicabut.
Nama tampilan berawalan set ("QA2 Dr. Rina …") supaya Operator, yang melihat semua set, langsung tahu milik siapa.

### Pengajuan per set (Periode 2026, judul berawalan `[QA{k}]`)

| # | Status akhir | Bagaimana dicapai |
|---|---|---|
| 1 | Draf, langkah 2 dari 7 | `StartSubmission` + `SaveWizardStep` |
| 2 | Draf lengkap, siap dikirim | wizard sampai langkah 7, belum `Submit` |
| 3 | Menunggu Penilaian (belum ada penilai) | `SubmitFormSubmission` |
| 4 | Menunggu Penilaian, **1 dari 2** penilai ditugaskan | + `AssignReviewers` satu penilai (dua penilai = `min_reviewer_count` langsung memindahkan ke Sedang Dinilai, `AssignReviewer::moveToReviewWhenMinimumReached`) |
| 5 | Sedang Dinilai, 1 dari 2 penilai sudah mengirim | + dua penilai + `SubmitEvaluationAnswers` (satu) |
| 6 | Sedang Dinilai, **putaran siap diputuskan** (bukan status sendiri; dicek lewat `DecisionReadiness`) | kedua penilai mengirim + keduanya `FinishReviewRound` |
| 7 | Perlu Revisi | satu penilai mengirim evaluasi lalu `OpenReviewThread` selagi kursinya belum selesai; kemudian kedua penilai `FinishReviewRound` → `ConcludeReviewRound` |
| 8 | Sedang Dinilai, **putaran pemeriksaan ulang** | #7 + `ResubmitFormSubmission`. Status Diajukan Ulang hanya transien: dengan penilai aktif, `returnToReviewQueue` langsung memulai `StartRecheckRound` → Sedang Dinilai. Seeder tidak bisa (dan tidak boleh) memarkirnya; QA melihat jejaknya di riwayat status dan log audit. |
| 9 | Disetujui, laporan monev draf, luaran tercatat | `ApproveSubmission` + `OpenMonevCycle` + `SaveMonevReport` + `SaveResearchOutput` |
| 10 | Disetujui, laporan monev terkirim | + `SubmitMonevReport` |
| 11 | Ditolak | `RejectSubmission` dengan alasan |
| 12 | Ditarik Kembali | Disetujui lalu `WithdrawSubmission` |

Pengajuan Disetujui membawa anggota (`AddResearchMember`, `AddStudentMember`), rencana anggaran
(`SaveBudgetLineItem`), dan target luaran (`SaveTargetOutput`). Berkas proposal adalah PDF kecil buatan seeder,
diunggah ke disk yang dikonfigurasi (R2 di staging), lewat jalur unggah yang sama dengan wizard.

Ditambah **riwayat Periode 2025** per set: 4 pengajuan (3 Disetujui, 1 Ditolak) dibuat dengan jam dimundurkan ke
masa periode itu, supaya statistik "tahun lalu" dan riwayat peneliti terisi. Yang Disetujui dituntaskan
(keputusan saya): laporan Monev 1 terkirim dan luaran tercatat, masing-masing pada jam tahun lalu, supaya papan
monev Operator tidak berisi siklus yatim bertenggat lewat yang akan dilaporkan sebagai bug.

Total per set: 16 pengajuan; empat set: 64.

### Volume institusi (sekali, tidak untuk diuji per penguji)

Akun `latar-peneliti{n}@simpas.test` (sekitar 30, tersebar di seluruh 26 prodi), 2 penilai latar, dan sekitar
60 pengajuan lintas dua periode dan tiga skema dengan sebaran status yang wajar (kebanyakan Menunggu
Penilaian/Disetujui). Gunanya hanya G2: paginasi (lebih dari satu halaman di setiap daftar), grafik per fakultas,
perbandingan tahun. Judulnya berawalan `[Latar]`, dan QA diminta tidak mengubahnya; tidak ada kerugian bila mereka
melakukannya.

Total kira-kira: 75 akun, 125 pengajuan.

### Verifikasi

- Test Pest `tests/Feature/Seeders/QaDataSeederTest.php`: menjalankan `DatabaseSeeder` + `QaDataSeeder` di database
  uji dan memastikan, per set, setiap baris tabel di atas tercapai (status akhir + kondisi putaran untuk #4–#8;
  Diajukan Ulang dicek lewat riwayat transisi #8, bukan status akhir); setiap pengajuan non-draf punya
  entri log audit; semua email berakhiran `@simpas.test`; seeder menolak run kedua; seeder menolak di production.
- Tidak ada cabang `if testing` di seeder; test menjalankan skala yang sama dengan staging (target < 2 menit;
  bila lebih, builder melaporkannya, tidak memangkas skala diam-diam).
- Gate penuh `composer run quality` dan `npm run quality`.

## Yang diputuskan

- Satu set per penguji, `qa1`–`qa4` (pemilik, 2026-11-13: 3–4 QA, menguji serentak).
- Semua state lewat Action (A), deterministik, reset lewat database segar (keputusan saya).
- Pemilik menyetujui desain ("gas", 2026-11-13). Keputusan saya sesudah builder check: baris #4/#6/#8 didefinisikan
  ulang sesuai yang bisa dicapai kode, tahun relatif, katalog lahir di awal tahun lalu, tanpa transaksi luar, antrean
  `sync` selama run, riwayat tahun lalu dituntaskan sampai monev dan luaran, staging `APP_ENV=staging`.

## Diserahkan ke builder

- Nama dan pembagian kelas seeder; urutan pemanggilan Action; bentuk DTO jawaban per langkah wizard.
- Cara membuat PDF kecil yang lolos validasi berkas.
- Isi teks Faker dan nama orang (realistis, Indonesia, bukan nama tokoh nyata).
- Sebaran status pengajuan latar.
