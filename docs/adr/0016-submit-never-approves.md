# 0016 — Submit tidak pernah menyetujui

**Status:** Diterima
**Tanggal:** 2026-09-06
**Menutup:** register #25

## Konteks

`UserFormController::submitForm()` memuat percabangan ini:

```php
if ($formPhaseDetail && !$formPhaseDetail->needs_review) {
    $submission->status->transitionTo(Approved::class);
} else {
    $submission->status->transitionTo(Submitted::class);
}
```

Bacaan sekilasnya masuk akal: fase yang tidak butuh review langsung disetujui. Tapi
`SubmissionStatus::config()` tidak pernah mengizinkan perpindahan itu:

```php
->allowTransition(Draft::class, Submitted::class)
->allowTransition(Submitted::class, UnderReview::class)
->allowTransition(UnderReview::class, Approved::class)
```

`Draft → Approved` tidak ada di daftar. Satu-satunya jalan masuk ke `Approved` adalah
dari `UnderReview`. Jadi setiap submit pada fase dengan `needs_review = false` melempar
`TransitionNotFound` — dan karena panggilannya berada di dalam `DB::transaction`,
**seluruh submit-nya dibatalkan**, bukan hanya transisinya.

Nol test dan nol seeder menyentuh cabang itu. Ia tidak pernah dijalankan siapa pun.

Ini mengoreksi premis yang tercatat di register #25. Entri itu menyatakan delegasi ke
`SubmitFormSubmission` tertahan karena "mendelegasikan tanpa memindahkan percabangan itu
akan mengubah perilaku form yang tidak butuh review". Tidak ada perilaku yang berubah —
tidak ada perilaku yang bekerja. Yang selama ini menahan delegasi ternyata cabang rusak
yang tak seorang pun pernah lewati.

## Keputusan

**Submit selalu berpindah ke `SUBMITTED`.** Cabang `needs_review` dicabut, dan
`UserFormController::submitForm()` mendelegasikan keputusannya ke
`SubmitFormSubmission`.

`APPROVED` hanya dicapai lewat Review. Tidak ada jalur lain, dan jalur submit tidak
memiliki pengecualian untuk fase mana pun.

## Konsekuensi

- Submit pada fase dengan `needs_review = false` berhenti melempar `TransitionNotFound`.
  Yang tadinya gagal total kini mendarat di `SUBMITTED` — dikunci `[SM-SUBMIT-18]`.
- Pengajuan pada fase seperti itu **berhenti di `SUBMITTED`** sampai dievaluasi. Kasus
  hampa BR-REV-05 (nol reviewer aktif, nol `ReviewSummary` terbuka) sudah diputuskan
  di [ADR 0017](0017-vacuous-approval-only-when-configured.md): sah disetujui secara
  hampa, tapi hanya kalau `needs_review = false` dinyatakan eksplisit.
- Satu aturan approval otomatis, satu tempat. BR-REV-05 tetap pemilik tunggalnya.

## Alternatif yang ditolak

**Memperlebar state machine dengan `allowTransition(Submitted::class, Approved::class)`
lalu auto-approve saat submit.** Ini yang paling menggoda karena membuat kode lama
"bekerja". Ditolak: BR-REV-05 **sudah** memiliki aturan approval otomatis, dan menaruh
salinannya di jalur submit melahirkan dua implementasi untuk satu aturan — persis yang
diperingatkan tabel *Aturan Cermin Lintas Konteks* di
[`ddd/README.md`](../requirement-analysis/ddd/README.md#aturan-cermin-lintas-konteks).
Konsekuensinya risiko integritas data, bukan sekadar kerapian: dua tempat yang boleh
menyetujui akan berbeda pendapat begitu salah satunya diubah.

**Membiarkan cabangnya dan mendelegasikan sebagian saja.** Mempertahankan cacat yang
sudah terbukti tidak pernah bekerja, sambil menambah percabangan di jalur yang sedang
disederhanakan. Cabang yang nol pemanggilnya bukan perilaku yang perlu dilestarikan.
