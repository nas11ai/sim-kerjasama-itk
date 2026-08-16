# 0011 — Kosakata refactoring sebagai standar review

**Status:** Diterima
**Tanggal:** 2026-08-16

## Konteks

`CODING_STANDARDS.md` mengatur **di mana** kode diletakkan (controller tipis,
FormRequest → DTO → Action, Policy) tetapi tidak mengatur **bentuk** kode di dalam
satu unit. Akibatnya review PR terjebak pada penilaian rasa — "kayaknya
kepanjangan" — yang tidak bisa didebat maupun disepakati.

Codebase warisan fork punya contoh konkretnya: `SubmissionViewController` 1081
baris, `ReviewController` 780 baris, rantai `if/elseif` status di
`updateStatusBasedOnReviews()`, dan id skema yang disimpan sebagai `varchar`.

## Keputusan

Katalog [refactoring.guru](https://refactoring.guru/refactoring) dipakai sebagai
kosakata bersama. Review PR menyebut nama smell dan nama teknik, bukan kesan.

Yang dicatat di `CODING_STANDARDS.md`:

1. **Pemetaan pola repo ini ke teknik bernama** — Action = *Replace Method with
   Method Object*, DTO = *Introduce Parameter Object*, Policy = *Extract Class*,
   `spatie/laravel-model-states` = *Replace Type Code with State/Strategy*,
   `scheme_id` kolom sungguhan = *Replace Data Value with Object*.
2. **Tabel smell yang paling relevan** beserta wujud konkretnya di codebase ini
   dan obatnya.
3. **Aturan praktis**: guard clause bukan `if` bersarang, satu Action satu use
   case, komentar menjelaskan *kenapa* bukan *apa*, magic number diberi nama, dan
   larangan *Speculative Generality*.

Refactor dan perubahan perilaku tidak dicampur dalam satu commit — konvensi
Conventional Commits sudah memisahkan `refactor:` dari `feat:`/`fix:`.

## Konsekuensi

- Keputusan arsitektur jadi bisa dijelaskan sebagai teknik baku, bukan selera.
  [ADR 0005](0005-scheme-selector-writes-to-column.md) misalnya adalah
  penyembuhan *Primitive Obsession*.
- Agent diminta menyebut nama teknik saat mengusulkan perubahan bentuk kode,
  sehingga usulannya bisa dinilai.
- Kosakata ini **tidak** ditegakkan mesin. Ia alat bantu review, bukan quality
  gate — berbeda dari [ADR 0001](0001-two-tier-static-analysis.md).
- Risiko yang disadari: kosakata refactoring bisa berubah jadi dalih untuk
  refactor yang tidak diminta. [ADR 0008](0008-strangler-refactor-of-legacy-controllers.md)
  tetap mengikat — refactor kode yang sedang disentuh, bukan yang kebetulan
  terlihat.

## Alternatif yang ditolak

**Tegakkan lewat metrik otomatis** (batas panjang method, cyclomatic complexity
via PHPStan atau PHPMD). Angka mudah diakali dengan memecah method secara asal
tanpa memperbaiki desain, dan menghasilkan kegagalan CI pada kode legacy yang
[ADR 0008](0008-strangler-refactor-of-legacy-controllers.md) sengaja biarkan.
Bisa ditinjau ulang setelah `app/Http` dan `app/Models` dibereskan.

**Tidak usah dibakukan.** Mengembalikan review ke penilaian rasa, dan pada tim
yang sebagian pekerjaannya dikerjakan agent, ketiadaan kosakata bersama membuat
umpan balik tidak bisa ditindaklanjuti.
