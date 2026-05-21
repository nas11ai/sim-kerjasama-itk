## Jenis Perubahan

<!-- Pilih yang relevan -->

- [ ] `type: feature` — Fitur baru
- [ ] `type: bug` — Bug fix
- [ ] `type: chore` — Refactor / upgrade / config
- [ ] `type: test` — Penambahan atau perbaikan test
- [ ] `type: docs` — Perubahan dokumentasi
- [ ] `type: breaking-change` — Ada perubahan yang tidak backward-compatible

---

## Issue yang Diselesaikan

<!-- Link ke Story atau Task yang di-resolve oleh PR ini -->

Closes #

---

## Ringkasan Perubahan

<!-- Jelaskan apa yang berubah dan kenapa. Fokus ke "apa" dan "kenapa", bukan "bagaimana" (itu sudah ada di kode). -->

---

## Cara Test

<!-- Langkah untuk memverifikasi perubahan ini secara manual -->

1.
2.
3.

---

## Test Coverage

<!-- Lampirkan bukti test coverage / hasil test -->

### Coverage Summary

| Metric    | Sebelum | Sesudah |
| --------- | ------- | ------- |
| Lines     | %       | %       |
| Functions | %       | %       |
| Classes   | %       | %       |
| Branches  | %       | %       |

### Bukti Test Coverage

<!-- Upload screenshot hasil coverage atau paste output command -->

#### PHPUnit / Pest

```bash
php artisan test --coverage
```

#### Vitest

```bash
npm run test:coverage
```

### Screenshot / Output

| Coverage Report                           |
| ----------------------------------------- |
| ![Coverage Screenshot](upload-image-here) |

---

## Screenshot / Recording

<!-- Wajib untuk perubahan UI. Hapus section ini jika tidak ada perubahan UI. -->

| Sebelum | Sesudah |
| ------- | ------- |
|         |         |

---

## Breaking Changes

<!-- Hapus section ini jika tidak ada breaking change -->

**Apa yang berubah:**

**Migration yang diperlukan:**

**Config yang perlu diupdate:**

---

## Checklist

- [ ] Acceptance criteria dari Story sudah terpenuhi
- [ ] Pest / Vitest test ditulis atau diupdate
- [ ] Test coverage dilampirkan
- [ ] Larastan tidak ada error baru (`./vendor/bin/phpstan analyse`)
- [ ] Pint tidak ada violation (`./vendor/bin/pint --test`)
- [ ] ESLint tidak ada violation (`npm run lint`)
- [ ] Tidak ada `console.log`, `dd()`, atau `dump()` tertinggal
- [ ] Migration sudah dicek di PostgreSQL (bukan hanya di MySQL)
- [ ] PR title mengikuti konvensi: `[Type] Deskripsi singkat` (contoh: `[Feature] Add budget line item input`)

```

```
