# 0009 — Urutan milestone: critical path dulu

**Status:** Diterima
**Tanggal:** 2026-08-16

## Konteks

Dokumen `01_domain_map.md` mencantumkan urutan implementasi sendiri:

```
Phase 1 Foundation (System Config, Identity & Access, File, Observability)
Phase 2 Platform   (Form Engine, Scheme)
Phase 3 Core       (Submission, Budget)
Phase 4 Review
Phase 5 Post-Approval (Monev, Research Output, Reporting)
Phase 6 Notification
```

Masalahnya, Phase 1 dan 2 justru bagian yang **sudah paling jadi** di codebase —
Form Engine, Scheme, Organization tree, dan Spatie Permission semuanya sudah
berjalan. Mengikuti urutan itu berarti berminggu-minggu mengerjakan yang sudah
ada, sementara loop intinya (submit → assign → evaluasi → approve) tetap rusak.

Notification juga ditaruh paling akhir, padahal domain event dibutuhkan sejak
Submission dan seterusnya.

## Keputusan

```
M0  Fondasi     form_phase_detail_id + scheme_id, Policy + isAccessibleBy,
                Events/Listeners, Laravel Notification, StorageService,
                field type scheme_selector/trl_selector, optimistic locking
M1  Submission  research_members, student_members, partners, additional_files,
                SubmitFormSubmission asli (temporal binding + PDF + deadline),
                Budget lock/max_budget/audit, transfer ownership, withdrawal
M2  Review      EvaluateSubmissionOutcome, AssignReviewers (min_count +
                workload + COI), reassignment, visibilitas skor
     ↑ setelah M2 sistem fungsional end-to-end
M3  IAM         tiga jalur registrasi, verifikasi NIDN, deaktivasi + guard
M4  Monev + Luaran
M5  Reporting
```

IAM ditunda karena user bisa di-seed; registrasi bukan penghalang untuk menguji
apa pun.

## Konsekuensi

- Sistem bisa didemokan utuh setelah M2, bukan setelah Phase 4.
- M0 tidak menghasilkan fitur yang terlihat — perlu dikomunikasikan supaya tidak
  dibaca sebagai kemandekan.
- Urutan ini menyimpang dari dokumen DDD, jadi `01_domain_map.md` bagian
  *Implementation Priority* perlu diperbarui agar tidak jadi sumber kebingungan.

## Alternatif yang ditolak

**Ikut urutan dokumen DDD.** Konsisten dengan dokumen dan enak dijelaskan ke
stakeholder, tapi menunda perbaikan loop inti sampai Phase 4 sambil mengerjakan
ulang bagian yang sudah ada.

**Vertical slice per peran** (Researcher tuntas, lalu Reviewer, dst.). Tiap slice
langsung bisa didemokan, tapi perbaikan fondasi tersebar dan gerbang lintas-peran
(auto-approve butuh Researcher + Reviewer + Operator sekaligus) terpaksa dipecah
canggung — `form_phase_detail_id` dan Policy akan tersentuh berulang kali.
