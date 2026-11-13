# Seeder Data Uji QA (Implementation Plan)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (native, keputusan pemilik
> 2026-11-10, "gas"/"lanjut" 2026-11-13/14). Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `php artisan db:seed --class=QaDataSeeder` mengisi database segar dengan empat set data uji per penguji
(`qa1`–`qa4`) plus volume institusi, seluruhnya lewat Action asli.

**Architecture:** satu seeder orkestrator + empat pembangun di `database/seeders/Qa/` yang masing-masing
mengembalikan objek hasil (readonly) untuk pembangun berikutnya. Jam dimundurkan lewat satu helper `QaClock`.
Tidak ada kode baru di `app/`; bila sebuah Action menolak urutan yang sah menurut DDD, itu bug aplikasi dan dicatat,
bukan disiasati.

**Tech Stack:** Laravel 13 seeders, lorisleiva actions (`::run` / `::make()->handle`), Carbon test-now, Pest.

**Spec:** `docs/design/2026-11-13-seeder-data-uji-qa-design.md` (baca dulu; tabel skenario #1–#12 di sana mengikat).

## Global Constraints

- Semua email `@simpas.test`; kata sandi = email; nama tampilan berawalan `QA{k} ` / `Latar `; judul pengajuan
  berawalan `[QA{k}] ` / `[Latar] `.
- Tahun relatif: `Y = now()->year` saat run, `L = Y - 1`. Nama periode `Periode {L}` dan `Periode {Y}`.
- Tidak ada penulisan `status` langsung, tidak ada `FormSubmission::factory()`, tidak ada `Notification::fake()`.
- Selama run: `config(['queue.default' => 'sync', 'broadcasting.default' => 'log'])`.
- Tanpa transaksi luar; exception tidak ditangkap seeder.
- Seeder menolak `app()->isProduction()` dan menolak bila `qa-admin@simpas.test` sudah ada.
- Faker `id_ID`, `seed(20261114)`.
- Teks UI yang dibuat seeder (judul, pengumuman, nama) tanpa em-dash "—".
- Komentar menyebut BR yang dipatuhi bila urutan pemanggilan Action tidak jelas dengan sendirinya.

## Review Focus

1. **Jam bocor**: exception di dalam `QaClock::at` meninggalkan jam mundur untuk sisa proses → `at()` memulihkan
   jam di `finally`. Test di Task 1.
2. **Temporal Field Binding**: pengajuan tahun lalu tidak melihat isian bila form lahir sesudahnya → katalog dibuat
   di `L-01-05`. Test di Task 5: pengajuan riwayat punya judul (`SubmissionTitleField`) terisi.
3. **Run kedua di DB yang sudah dipakai QA** → ditolak dengan pesan berisi `migrate:fresh --seed`, nol baris baru.
   Test di Task 1.
4. **Berkas ke disk sungguhan**: test tidak boleh menulis ke R2 → test memakai `Storage::fake()` untuk disk default;
   seeder sendiri memakai disk yang dikonfigurasi. Test di Task 4.
5. **Tahun statistik**: pengajuan riwayat harus terhitung tahun `L`, yang sekarang tahun `Y` → assert
   `SubmissionYear` di Task 5.

---

### Task 1: Orkestrator, penjaga, dan jam

**Files:**
- Create: `database/seeders/QaDataSeeder.php`, `database/seeders/Qa/QaClock.php`
- Test: `tests/Feature/Seeders/QaDataSeederGuardTest.php`

**Interfaces:**
- Produces: `QaClock::at(CarbonInterface $moment, Closure $work): mixed` (set test-now Carbon + CarbonImmutable,
  pulihkan di `finally`); `QaDataSeeder::ADMIN_EMAIL = 'qa-admin@simpas.test'`; `QaDataSeeder::SETS = 4`.
- `QaDataSeeder::run()` urutannya: penjaga → `config(...)` → Faker seed → buat `qa-admin` (peran `admin`, profil
  aktif di organisasi Institusi) → `QaCatalog` → `QaAccounts` per set → `QaHistory` per set →
  `QaScenarios` per set → `QaBackground` → cetak ringkasan. Task 1 hanya mengisi penjaga, config, dan admin;
  pemanggilan pembangun ditambahkan task pemiliknya.

- [ ] Test `it('menolak berjalan di production')`: `app()->detectEnvironment(fn () => 'production')`, `seed(DatabaseSeeder)`,
  lalu `expect(fn () => $this->seed(QaDataSeeder::class))->toThrow(RuntimeException::class)`, dan
  `User::where('email', 'like', '%@simpas.test')->count() === 0`.
- [ ] Test `it('menolak run kedua dan menyuruh migrate:fresh')`: buat user `qa-admin@simpas.test`; seed →
  throw `RuntimeException` dengan pesan mengandung `migrate:fresh --seed`; jumlah user tidak berubah.
- [ ] Test `it('memulihkan jam walau pekerjaan gagal')`: `QaClock::at(Carbon::parse('2020-01-01'), fn () => throw new Exception)`
  dibungkus try; sesudahnya `now()->year === (int) date('Y')` dan `CarbonImmutable::now()->year` sama.
- [ ] Jalankan → FAIL. Implementasi. Jalankan → PASS.
- [ ] Commit `feat(seed): kerangka QaDataSeeder dengan penjaga dan jam`.

### Task 2: Katalog bersama

**Files:** Create `database/seeders/Qa/QaCatalog.php`, `database/seeders/Qa/QaCatalogResult.php`;
Test `tests/Feature/Seeders/QaCatalogTest.php`.

**Interfaces:**
- `QaCatalog::build(User $admin): QaCatalogResult` dipanggil di dalam `QaClock::at(L-01-05 09:00)`.
- `QaCatalogResult` (readonly): `schemes: array{dasar: Scheme, terapan: Scheme, pengabdian: Scheme}`,
  `forms: array{penelitian: Form, pengabdian: Form}`, `steps: array<'penelitian'|'pengabdian', array{submit: FormPhaseDetail, monev1: FormPhaseDetail, output: FormPhaseDetail}>`,
  `fields: array<'penelitian'|'pengabdian', array<string, FormField>>` (kunci: `judul`, `abstrak`, `kata_kunci`,
  `skema`, `tkt`, `proposal`, `mulai`, `bidang`), `periods: array{last: SubmissionPeriod, current: SubmissionPeriod}`.
- Jalur: `SaveScheme` (3 skema, `rules` `min_reviewer_count` 2, `max_reviewer_workload` 10; pagu 75 jt / 150 jt /
  50 jt; anggota 5/5/4); `CreateForm` + `SaveFormDraft` + `PublishFormDraft`; `GrantFormAccess`
  (`submissions.create`, organisasi Institusi); `CreateEvaluationForm` + `PublishEvaluationFormVersion`
  ("Penilaian Substansi": `Skor` number wajib, `Catatan` textarea); `CreateFormPhase` + `PublishPhaseVersion`
  (langkah: Pengajuan `needs_review` + form evaluasi wajib, `submit` siklus 1, `output`);
  `CreateSubmissionPeriod` untuk `Periode {L}` (Submit `L-03-31`, Evaluasi `L-05-31`, Revisi `L-06-30`, Submit
  siklus 1 `L-09-30`, Output `L-12-01`, semua 23:59) dan, di jam sekarang, `Periode {Y}` (Submit +3 bln,
  Evaluasi +4, Revisi +5, siklus 1 +8, Output +11); `PublishAnnouncement` ×4 lalu `RetractAnnouncement` ×1.
- Bila sebuah DTO Form Builder butuh bentuk yang tidak jelas, tiru test Feature Action yang bersangkutan.

- [ ] Test `it('membangun katalog yang menawarkan dua form di periode tahun ini')`: `QaClock` + `build`; assert
  3 skema aktif, 2 form `published_at` tidak null dengan 8 isian, `SubmissionPeriodGate::assertOpen(current)` tidak
  throw, `SubmissionPeriodGate::isFullyClosed(last)` true, 3 pengumuman terbit + 1 ditarik.
- [ ] FAIL → implementasi → PASS. Sambungkan ke `QaDataSeeder::run()`.
- [ ] Commit `feat(seed): katalog data uji QA`.

### Task 3: Akun per set

**Files:** Create `database/seeders/Qa/QaAccounts.php`, `database/seeders/Qa/QaSet.php`;
Test `tests/Feature/Seeders/QaAccountsTest.php`.

**Interfaces:**
- `QaAccounts::build(int $k, QaCatalogResult $catalog, User $admin): QaSet`, di jam `L-01-05`.
- `QaSet` (readonly): `k: int`, `operator: User`, `reviewers: array{0: Reviewer, 1: Reviewer}` (internal),
  `externalReviewer: Reviewer`, `researchers: array<int, User>` (kunci 1..12, `qa{k}-peneliti{nn}`, `nn` dua digit),
  `pending: User`, `rejected: User`, `member: User`.
- Peneliti: `RegisterResearcher` (prodi diambil berurutan dari 26 prodi seeder, offset `k*3`) → `VerifyNidn` →
  `CompleteProfile` (tanpa foto). `peneliti-baru` hanya `RegisterResearcher`; `peneliti-ditolak` +
  `RejectNidnVerification`. Operator: buat user + profil aktif + `assignRole('operator')` (tidak ada Action untuk
  staf internal). Penilai: user + profil + `AppointReviewer` (`internal`/`external`, masa jabatan s.d. `Y+1-12-31`).
  `IssueInvitation` ×2 lalu `RevokeInvitation` ×1.

- [ ] Test `it('membuat akun set dengan peran dan status verifikasi yang benar')` untuk `k = 2`: 12 peneliti aktif
  dengan profil lengkap, `qa2-peneliti-baru` pending, `qa2-peneliti-ditolak` rejected, operator punya peran
  `operator`, 3 `Reviewer` aktif hari ini, 1 undangan aktif + 1 dicabut, nama berawalan `QA2 `, semua email
  berakhiran `@simpas.test`.
- [ ] FAIL → implementasi → PASS. Sambungkan ke orkestrator.
- [ ] Commit `feat(seed): akun data uji per penguji`.

### Task 4: Skenario tahun ini (#1–#12)

**Files:** Create `database/seeders/Qa/QaSubmissionFlow.php` (langkah-langkah alur yang dipakai ulang),
`database/seeders/Qa/QaScenarios.php`; Test `tests/Feature/Seeders/QaScenariosTest.php`.

**Interfaces:**
- `QaSubmissionFlow` (dibuat dengan `QaCatalogResult` + Faker), metode publik, masing-masing tepat satu atau dua
  Action:
  - `draft(User $lead, string $form, string $scheme, string $title, int $untilStep): FormSubmission`
    (`StartSubmission` + `SaveWizardStep` per langkah 2..`$untilStep`; jawaban per field dari `fields`, proposal =
    PDF minimal yang ditulis ke file sementara dan dibungkus `new UploadedFile($path, 'proposal.pdf', 'application/pdf', null, true)`)
  - `submitted(...)` = `draft(..., 7)` + `SubmitFormSubmission`
  - `team(FormSubmission $s, User $lead, User $member): void` (`AddResearchMember`, `AddStudentMember`,
    `SaveBudgetLineItem` ×2 dalam pagu, `SaveTargetOutput` ×1), dipanggil sebelum submit
  - `assign(FormSubmission $s, Reviewer ...$reviewers): void` (`AssignReviewers`)
  - `evaluate(FormSubmission $s, Reviewer $r): void` (`SubmitEvaluationAnswers` atas assignment kursinya; Skor 80)
  - `openThread(FormSubmission $s, Reviewer $r, string $note): void` (`OpenReviewThread`)
  - `finish(FormSubmission $s, Reviewer $r): void` (`FinishReviewRound`)
  - `approve` / `reject(…, string $reason)` / `withdraw(…, string $reason)` oleh operator set
  - `resubmit(FormSubmission $s): void` (`ResubmitFormSubmission`, setelah `SaveWizardStep` mengubah abstrak)
  - `monev(FormSubmission $s, User $operator, bool $send): FormSubmission` (`OpenMonevCycle`, `SaveMonevReport`,
    opsional `SubmitMonevReport`)
  - `output(FormSubmission $s, User $lead): void` (`SaveResearchOutput`, tipe pertama dari `OutputTypeDefinitionSeeder`)
- `QaScenarios::build(QaSet $set, QaSubmissionFlow $flow, User $operator): array<int, FormSubmission>` (kunci 1..12 =
  nomor baris tabel desain; peneliti `nn` = nomor skenario; form penelitian untuk ganjil, pengabdian untuk genap;
  skema mengikuti form).

- [ ] Test `it('[QA-SEED] setiap baris skenario tercapai')` dengan `Storage::fake(config('filesystems.default'))`:
  bangun katalog + set 1 + skenario; assert per kunci:
  1 `Draft` + `last_completed_step` 2; 2 `Draft` + 7 + `RequiredFieldGuard::assertComplete` tidak throw;
  3 `Submitted` + 0 kursi; 4 `Submitted` + 1 kursi aktif; 5 `UnderReview` + tepat 1 respons terkirim;
  6 `UnderReview` + `DecisionReadiness::run($s)` siap; 7 `NeedsRevision`; 8 `UnderReview` + putaran pemeriksaan
  berjalan + riwayat status memuat `Resubmitted`; 9 `Approved` + laporan monev draf + 1 luaran; 10 `Approved` +
  laporan monev `is_submitted`; 11 `Rejected`; 12 `Withdrawn`. Setiap non-draf punya `Activity` "Pengajuan
  disubmit". Berkas proposal ada di disk palsu.
- [ ] FAIL → implementasi per skenario, jalankan ulang setiap kali. Bila sebuah Action menolak urutan yang sah
  menurut DDD/BDD: catat di `docs/plans/2026-11-11-fase-reuse-catatan-bug.md` sebagai B25+ dan **berhenti, laporkan**;
  jangan menulis tabel langsung.
- [ ] Sambungkan ke orkestrator. Commit `feat(seed): skenario pengajuan per status untuk QA`.

### Task 5: Riwayat tahun lalu

**Files:** Create `database/seeders/Qa/QaHistory.php`; Test `tests/Feature/Seeders/QaHistoryTest.php`.

**Interfaces:**
- `QaHistory::build(QaSet $set, QaSubmissionFlow $flow): void`, sebelum `QaScenarios` (supaya draf tahun ini tetap
  baris terbaru wizard). Peneliti 01–04, `Periode {L}`. Jam: mulai+kirim `L-02-10`; tugaskan, nilai, selesai, putuskan
  `L-04-15`; monev (kirim) `L-08-01`; luaran `L-10-01`. Hasil: 01–03 Disetujui + monev terkirim + luaran, 04 Ditolak.

- [ ] Test `it('riwayat tahun lalu terhitung di tahun lalu dan tuntas')`: 4 pengajuan di periode `last`, 3 `Approved`
  dengan laporan monev `is_submitted` + luaran, 1 `Rejected`; `SubmissionYear` (atau kolom tahun yang dipakai statistik)
  = `L` untuk keempatnya; judul terisi (Review Focus 2); `created_at` keempatnya di tahun `L`.
- [ ] FAIL → implementasi → PASS. Commit `feat(seed): riwayat tahun lalu untuk QA`.

### Task 6: Volume institusi + ringkasan + seluruh run

**Files:** Create `database/seeders/Qa/QaBackground.php`; Modify `QaDataSeeder.php` (ringkasan);
Test `tests/Feature/Seeders/QaDataSeederTest.php`.

**Interfaces:**
- `QaBackground::build(QaCatalogResult $catalog, QaSubmissionFlow $flow, User $admin): void`: 30 akun
  `latar-peneliti{nn}@simpas.test` tersebar di ke-26 prodi, operator `latar-operator@`, 2 penilai latar; 30 pengajuan
  riwayat tahun lalu (2/3 Disetujui, sisanya Ditolak) dan 30 tahun ini (10 Menunggu Penilaian, 10 Sedang Dinilai,
  6 Disetujui, 4 Ditolak), berganti skema.
- Ringkasan: `$this->command->table(['Set', 'Akun', 'Peran'], …)` dan jumlah pengajuan per status.

- [ ] Test `it('mengisi database segar untuk empat penguji')`: `seed(DatabaseSeeder)` + `seed(QaDataSeeder)` dengan
  `Storage::fake`; assert set 1..4 masing-masing 16 pengajuan `[QA{k}]`; total pengajuan ≥ 120; setiap fakultas punya
  pengajuan tahun ini dan tahun lalu; `User` ≥ 75; tidak ada email di luar `@simpas.test` kecuali akun milik
  `DatabaseSeeder`; `jobs` kosong. Catat durasi test di laporan; bila > 120 s, laporkan (jangan pangkas skala).
- [ ] FAIL → implementasi → PASS. Commit `feat(seed): volume institusi dan ringkasan QA`.

### Task 7: Dokumentasi + gate

**Files:** Modify `docs/plans/2026-11-13-paket-a-masukan-pemilik.md` (status langkah seeder),
`docs/plans/2026-11-11-fase-reuse-catatan-bug.md` (B25: `DummyUserSeeder` peran lama, `operator-flow.php` menulis status
langsung), Create `docs/qa/data-uji.md` (cara reset, daftar akun per set dan arti `peneliti01`–`12`, tidak ada em-dash).

- [ ] Tulis dokumen. `composer run quality` (container, `COMPOSER_PROCESS_TIMEOUT=0`) dan `npm run quality` hijau.
- [ ] Jalankan seeder nyata ke DB dev (`migrate:fresh --seed` + `db:seed --class=QaDataSeeder`) bila diizinkan;
  bila classifier menolak, catat bahwa pemilik perlu menjalankannya.
- [ ] Commit `docs: cara memakai data uji QA`.
