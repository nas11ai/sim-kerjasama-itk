# Architecture Decision Records

Catatan keputusan arsitektur SIMPAS v2. Satu keputusan penting = satu file.

## Kapan menulis ADR

Tulis ADR kalau keputusannya **mahal untuk dibatalkan** atau **akan ditanyakan
ulang enam bulan lagi**:

- Perubahan skema database yang menyimpang dari dokumen DDD
- Pemilihan atau penolakan dependency
- Pola arsitektur (layering, batas bounded context, strategi refactor)
- Konfigurasi tooling yang mengikat cara kerja tim (quality gate, versi runtime)
- Urutan pengerjaan yang punya konsekuensi teknis

**Tidak perlu ADR** untuk: penamaan variabel, perbaikan bug rutin, penyesuaian
UI, atau apa pun yang bisa diubah dalam satu PR tanpa efek berantai.

## Format

Nomor urut empat digit, judul kebab-case:

```
docs/adr/0001-two-tier-static-analysis.md
```

Setiap ADR memuat: **Status**, **Konteks**, **Keputusan**, **Konsekuensi**, dan
**Alternatif yang ditolak**. Bagian terakhir itu yang paling sering menyelamatkan
—ia mencegah tim mengulang diskusi yang sudah selesai.

## Status

| Status         | Arti                                                     |
| -------------- | -------------------------------------------------------- |
| `Diusulkan`    | Sedang dibahas, belum disepakati                         |
| `Diterima`     | Berlaku sekarang                                         |
| `Digantikan`   | Sudah tidak berlaku — sebutkan ADR penggantinya          |
| `Ditinggalkan` | Dibatalkan sebelum sempat diterapkan                     |

ADR yang sudah `Diterima` **tidak diedit** kalau keputusannya berubah. Buat ADR
baru yang menggantikannya, lalu ubah status yang lama jadi `Digantikan`. Riwayat
keputusan sama berharganya dengan keputusan itu sendiri.

## Daftar

| ADR                                                              | Judul                                                        | Status   |
| ---------------------------------------------------------------- | ------------------------------------------------------------ | -------- |
| [0001](0001-two-tier-static-analysis.md)                         | Static analysis dua tier                                     | Diterima |
| [0002](0002-agent-instructions-single-source.md)                 | Instruksi agent bersumber tunggal                            | Diterima |
| [0003](0003-pin-php-84-across-environments.md)                   | PHP 8.4 diseragamkan di semua environment                    | Diterima |
| [0004](0004-form-phase-detail-id-on-submissions.md)              | `form_phase_detail_id` di `form_submissions`                 | Diterima |
| [0005](0005-scheme-selector-writes-to-column.md)                 | `scheme_selector` menulis ke kolom, bukan EAV                | Diterima |
| [0006](0006-rejection-is-manual-only.md)                          | Rejection hanya manual, auto-reject dihapus                  | Diterima |
| [0007](0007-inertia-props-as-frontend-contract.md)               | Kontrak frontend berupa Inertia props + tipe TypeScript      | Diterima |
| [0008](0008-strangler-refactor-of-legacy-controllers.md)         | Refactor legacy dengan pola strangler                        | Diterima |
| [0009](0009-milestone-sequencing-critical-path-first.md)         | Urutan milestone: critical path dulu                         | Diterima |
| [0010](0010-defer-legacy-data-migration.md)                      | Migrasi data legacy ditunda ke spec terpisah                 | Diterima |
| [0011](0011-refactoring-vocabulary.md)                           | Kosakata refactoring sebagai standar review                  | Diterima |
| [0012](0012-wizard-driven-submission-lifecycle.md)               | Siklus pengajuan mengikuti wizard tujuh langkah              | Diterima |
| [0013](0013-form-access-control-matches-ancestor-organizations.md) | Akses Form ditentukan leluhur organisasi, bukan keturunannya  | Diterima |
| [0014](0014-time-boxed-access-single-override-mechanism.md)      | Akses berbatas waktu: tiga sumbu, satu mekanisme pengecualian | Diterima |
| [0015](0015-gate-before-never-bypasses-domain-invariants.md)     | Gate::before tidak melewati invarian domain                   | Diterima |
| [0016](0016-submit-never-approves.md)                            | Submit tidak pernah menyetujui                                | Diterima |
| [0017](0017-vacuous-approval-only-when-configured.md)             | Persetujuan hampa hanya kalau dikonfigurasi begitu            | Digantikan 0018 |
| [0018](0018-review-outcome-at-round-end-operator-approves.md)     | Hasil review dihitung di akhir putaran; persetujuan oleh Operator | Diterima |
