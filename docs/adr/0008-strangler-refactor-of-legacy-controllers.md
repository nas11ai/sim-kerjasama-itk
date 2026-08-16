# 0008 — Refactor legacy dengan pola strangler

**Status:** Diterima
**Tanggal:** 2026-08-16

## Konteks

Repo ini fork dari `sim-kerjasama-itk`. Controller warisannya gemuk dan memuat
aturan bisnis:

| Controller                 | Baris |
| -------------------------- | ----- |
| `SubmissionViewController` | 1081  |
| `ReviewController`         | 780   |
| `UserFormController`       | 543   |

Sebagian logikanya bertentangan dengan dokumen DDD — lihat
[0006](0006-rejection-is-manual-only.md). Sementara `lorisleiva/laravel-actions`
sudah terpasang tapi baru dipakai satu kali, dan `app/Policies/` belum ada sama
sekali meski BR-SM-07 mewajibkan scoping di setiap query.

## Keputusan

Ekstraksi bertahap, bukan penulisan ulang. Saat sebuah flow dikerjakan di
milestone tertentu, aturan bisnisnya dipindah ke `app/Actions/` dan
`app/Policies/` berikut test-nya. Kode yang tidak tersentuh milestone dibiarkan
apa adanya.

Dua batas yang mengikat:

- **Jangan menyalin pola legacy yang salah ke kode baru.** Kode baru mengikuti
  dokumen DDD, bukan mengikuti kode yang ada.
- **Jangan melakukan refactor besar yang tidak diminta.** Scope milestone yang
  menentukan apa yang disentuh.

## Konsekuensi

- Risiko regresi kecil; progres terlihat tiap milestone.
- Selama masa transisi, dua gaya hidup berdampingan — controller gemuk dan
  Action tipis. Ini disengaja dan berbatas waktu.
- Direktori legacy tetap di `excludePaths` PHPStan tier strict
  ([0001](0001-two-tier-static-analysis.md)) sampai isinya dibereskan; daftar itu
  jadi ukuran kemajuan yang bisa dihitung.

## Alternatif yang ditolak

**Rewrite bersih per bounded context** (`app/Domain/Submission/`, dst.). Paling
rapi dan paling sesuai dokumen, tapi mengubah semua route sekaligus dan menyentuh
setiap halaman Vue yang sudah ada — tim frontend kena imbas di tengah jalan,
dengan ~3–4 minggu tanpa apa pun yang bisa dites ulang.

**Tambah saja, jangan sentuh yang lama.** Paling cepat jangka pendek, tapi
membiarkan auto-reject yang melanggar BR-REV-07 dan `getFormPhaseDetail()` yang
salah tetap hidup di produksi.
