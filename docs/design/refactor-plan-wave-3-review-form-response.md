# Gelombang 3 — ReviewFormResponseController keluar dari daftar legacy

**Status:** selesai — `ReviewFormResponseController` keluar dari `quality-legacy.txt` (plafon 100 → 99).
Diukur pada `80d373a`.

## Pengukuran awal

452 baris; `submit()` 65 baris, `showOrCreate()` 50, `generateSummaryContent()` 45; blok muat-relasi
tersalin tiga kali; transaksi manual dengan `catch (\Exception)` yang membocorkan pesannya ke layar.

## Temuan — diverifikasi di kode

**F1 — Admin bisa menjawab atas nama reviewer.** `authorizeAssignment()` meloloskan `users.manage` dan
dipakai juga oleh `saveDraft()` dan `submit()`. Super Admin lolos lewat `Gate::before`. Admin yang sekadar
membuka form juga membuat draft baru.

**F2 — Kirim menolak jawaban segar.** `canBeSubmitted()` dicek sebelum jawaban baru ditulis, jadi
kelengkapan dinilai dari draft terakhir. Layar mengaktifkan tombol kirim dari isian di peramban:
reviewer yang mengisi lalu langsung mengirim ditolak "Harap lengkapi" sampai simpan otomatis 30 detik.

**F3 — Jawaban untuk field form lain diterima.** Kunci `responses` tidak dicek kepemilikannya; jawaban
tersimpan di draft ini dan menaikkan persentase kelengkapan. Kunci bukan angka → galat foreign key yang
pesan SQL-nya tampil di toast.

**F4 — Tenggat bawaan dari periode sembarang.** Periode dicari lewat `form_id` dengan `first()` tanpa
urutan; form dipakai ulang lintas periode, jadi tenggatnya bisa periode lama yang sudah lewat.

**F5 — Form nonaktif (BR-FE-22) masih bisa dimulai** sebagai penugasan baru.

**F6 — Tanpa audit** (BR-RPT-09) untuk mulai form, draft dibuat, dan draft disimpan.

**F7 — Catatan akhir terhapus.** Layar menginisialisasi `final_notes` dengan `''`, jadi catatan
yang tersimpan tak dimuat ulang dan simpan berikutnya mengosongkannya.

**F8 — Field opsional tidak divalidasi tipenya**; email atau angka yang keliru lolos.

## Keputusan

- **K1** — `ReviewerFormAssignmentPolicy`: `view` (admin atau reviewer aktif) dan `evaluate` (hanya
  reviewer aktif, lewat `ActiveReviewerAssignment`). `evaluate` masuk `INVARIANT_ABILITIES` (ADR 0015).
  Admin membaca dengan `canEdit`/`canSubmit` false dan tanpa draft yang dibuat.
- **K2** — Action beraudit di `Actions/Review/`: `StartEvaluationForm`, `OpenEvaluationResponse`,
  `SaveEvaluationDraft`, `SubmitEvaluationAnswers`. Penulisan jawaban di
  `Services/Review/EvaluationAnswerWriter` (menolak field asing, BR-FE-05).
- **K3** — Kirim menulis jawaban lalu menilai kelengkapannya dalam satu transaksi; yang ditolak tidak
  meninggalkan apa pun. Galat lewat `ValidationException`, tampil sebagai toast `AlertContainer`.
- **K4** — Baca lewat `Queries/Review/`: `EvaluationDueDate` (periode pengajuan itu sendiri),
  `EvaluationAnswerErrors` (semua field terisi), `EvaluationSummaryText`, `EvaluationAssignmentProgress`.
- **K5** — `Reviewer/EvaluationForm/ShowPage.vue` memuat `final_notes` tersimpan; perbaikan perilaku,
  bukan refactor layar legacy.

Test: `tests/Feature/Review/EvaluationResponseTest.php`.
