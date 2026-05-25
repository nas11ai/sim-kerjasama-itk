## Jenis Perubahan

- [x] `type: bug` — Bug fix

---

## Issue yang Diselesaikan

Closes #42

---

## Ringkasan Perubahan
Menambahkan field non-agregasi (seperti `faculties.id`, `study_programs.id`) pada klausa `GROUP BY` untuk memenuhi standar mode _strict_ pada PostgreSQL untuk keempat query di `StatController.php`. Selain itu juga ditambahkan Test Case pada Pest untuk memproteksi _endpoint_ `get-form-submissions` agar terhindar dari error regresi serupa di masa depan.

---

## Cara Test

1. Jalankan aplikasi di environment lokal dengan koneksi PostgreSQL (pastikan _strict mode_ aktif).
2. Login sebagai Admin dan buka Dashboard (atau akses _endpoint_ `/stats/get-form-submissions` dan `/stats/get-reviewers`).
3. Pastikan data statistik tampil dengan benar tanpa adanya error 500 terkait syntax SQL _GROUP BY_.

---

## Test Coverage

### Coverage Summary

| Metric    | Sebelum | Sesudah |
| --------- | ------- | ------- |
| Total     | -       | 4.3%    |

### Bukti Test Coverage

#### PHPUnit / Pest

```bash
$env:XDEBUG_MODE="coverage"; php artisan test --coverage
```

### Screenshot / Output

| Coverage Report                           |
| ----------------------------------------- |
| *(Telah dilampirkan via screenshot manual oleh tester/developer)* |

---

## Checklist

- [x] Acceptance criteria dari Story sudah terpenuhi
- [x] Pest / Vitest test ditulis atau diupdate
- [x] Test coverage dilampirkan
- [x] Larastan tidak ada error baru (`./vendor/bin/phpstan analyse`)
- [x] Pint tidak ada violation (`./vendor/bin/pint --test`)
- [ ] ESLint tidak ada violation (`npm run lint`)
- [x] Tidak ada `console.log`, `dd()`, atau `dump()` tertinggal
- [ ] Migration sudah dicek di PostgreSQL (bukan hanya di MySQL)
- [x] PR title mengikuti konvensi: `[Type] Deskripsi singkat` (contoh: `[Feature] Add budget line item input`)
