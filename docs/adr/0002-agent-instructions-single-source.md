# 0002 — Instruksi agent bersumber tunggal

**Status:** Diterima
**Tanggal:** 2026-08-16

## Konteks

Sebagian pekerjaan dikerjakan coding agent — Claude Code, dan `dsc` (Claude Code
dengan settings DeepSeek). Keduanya membaca file instruksi dari root repo.

`CONTRIBUTING.md` sepanjang 735 baris sudah memuat konvensi kode yang bagus
(controller tipis, FormRequest → DTO → Action, PostgreSQL, `isAccessibleBy()`,
optimistic locking), tapi semuanya prosa yang tidak ditegakkan apa pun. Risiko
yang jelas: menyalinnya ke file instruksi agent akan menghasilkan dua dokumen
yang perlahan berbeda isi.

## Keputusan

Tiga file dengan peran yang tidak tumpang tindih:

| File                          | Isi                                                     |
| ----------------------------- | ------------------------------------------------------- |
| `.github/CODING_STANDARDS.md` | Rujukan tunggal aturan kode — manusia dan agent          |
| `AGENTS.md`                   | Hal yang tidak bisa disimpulkan agent dari membaca kode  |
| `CONTRIBUTING.md`             | Proses: branch, commit, PR, review, rilis                |

`CLAUDE.md` hanya berisi `@AGENTS.md`. `CONTRIBUTING.md` bagian konvensi kode
diganti tautan (735 → 533 baris).

`AGENTS.md` sengaja pendek dan memuat hal yang mesin tidak bisa tegakkan: batas
bounded context, kewajiban menyebut kode business rule di komentar, larangan
menyiasati PHPStan, dan **kewajiban menjalankan quality gate sebelum mengklaim
selesai**.

## Konsekuensi

- Aturan hidup di satu tempat; agent dan manusia membaca sumber yang sama.
- CI jadi jaring pengaman untuk yang bisa ditegakkan mesin
  ([0001](0001-two-tier-static-analysis.md)); `AGENTS.md` menutupi sisanya.
- Menambah aturan berarti menyentuh `CODING_STANDARDS.md`, dan menilai apakah
  `AGENTS.md` perlu ikut diperbarui.

## Alternatif yang ditolak

**Salin aturan ke `AGENTS.md`.** Dua dokumen yang pasti berbeda dalam beberapa
bulan, tanpa cara mengetahui mana yang benar.

**Ekstrak jadi paket preset yang bisa dipakai ulang lintas repo.** Manfaatnya
baru terasa kalau memang ada repo lain yang menyusul; belum ada.
