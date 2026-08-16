# 0005 — `scheme_selector` menulis ke kolom, bukan EAV

**Status:** Diterima
**Tanggal:** 2026-08-16
**Membatalkan:** pernyataan di `docs/requirement-analysis/ddd/supporting/04_scheme.md`
bahwa "Scheme ID tidak disimpan di `form_submissions` — hanya di
`form_field_responses`. Ini single source of truth."

## Konteks

DDD menetapkan skema sebuah submission disimpan sebagai jawaban form di
`form_field_responses.value` (varchar), dipilih lewat FormField bertipe
`scheme_selector`. Tidak ada kolom `scheme_id` di `form_submissions`.

Empat masalah muncul dari situ:

**Migrasi data.** Tabel legacy `submissions` punya `scheme_id` sebagai kolom FK
`NOT NULL`, berikut `submission_period_id`, `submission_type_id`, dan
`level_of_technology_readiness_id`. Untuk memindahkannya ke EAV, ETL harus
menentukan Form v2 padanannya, mencari FormField `scheme_selector` di Form itu,
dan **kalau field itu belum dikonfigurasi, datanya tidak bisa direpresentasikan
sama sekali**. Data lama menyandera konfigurasi Form yang belum dibuat.

**Integritas.** `value` hanya varchar. Tidak ada FK, jadi skema bisa terhapus
tanpa ada yang protes. BR-SCH-01 ("scheme tidak bisa dinonaktifkan kalau masih
ada submission aktif") jadi mahal untuk dicek.

**BR-SM-01 tidak bisa ditegakkan database.** "Satu submission aktif per period
per scheme" hanya sekuat disiplin developer.

**Biaya query.** `FormSubmission::scheme()` yang ada sekarang melakukan
`whereHas` bersarang ke `field_types.name = 'scheme_selector'` lalu
`Scheme::find($response->value)` — tiga join plus magic string, per submission.
N+1 di setiap halaman daftar.

Yang menentukan arah: README DDD **sudah** menetapkan preseden bahwa sebagian
field type tidak menulis ke EAV —

> **RepeatableField** = UI pattern saja — data tetap ke extension tables, bukan
> `form_field_responses`

## Keputusan

`scheme_selector` dan `trl_selector` mengikuti preseden RepeatableField: keduanya
**UI pattern** yang menulis ke kolom sungguhan. Tidak ada baris
`form_field_responses` untuk skema — jadi tidak ada duplikasi, bukan sekadar
duplikasi yang dijaga.

```sql
ALTER TABLE form_submissions
  ADD scheme_id            BIGINT REFERENCES schemes(id),
  ADD trl_id               BIGINT REFERENCES technology_readiness_levels(id),
  ADD submission_period_id BIGINT NOT NULL REFERENCES submission_periods(id);

CREATE UNIQUE INDEX one_active_submission_per_period_scheme
  ON form_submissions (submitted_by, submission_period_id, scheme_id)
  WHERE parent_submission_id IS NULL
    AND is_submitted = true
    AND status NOT IN ('rejected', 'withdrawn');
```

**Index hanya berlaku untuk submission yang sudah disubmit.** Alasannya wizard:
`scheme_id` masih NULL sepanjang langkah 1–2 (skema baru dipilih di langkah 3),
sehingga index yang ikut menghitung DRAFT akan menolak draft kedua sebelum
penggunanya sempat memilih skema — padahal BR-SM-01 justru mengizinkan dua
pengajuan ke skema berbeda. Ini juga pembacaan yang lebih tepat atas kata
"**active**" di BR-SM-01.

`COALESCE(scheme_id, 0)` sengaja **tidak** dipakai. Index ini per
`(user, period)`, bukan per Form; kalau satu period punya dua Form tanpa skema,
`COALESCE` akan memblokir pengajuan ke Form kedua secara keliru. Dengan NULL
dibiarkan distinct, pengajuan tanpa skema tidak saling menghalangi — sesuai bunyi
harfiah "per Scheme".

## Konsekuensi

- Satu salinan data, bukan dua. Tidak ada sinkronisasi, tidak ada drift, tidak
  ada test konsistensi.
- `$submission->scheme` jadi relasi biasa yang bisa di-eager-load. Semua rule
  (`max_budget`, `min_reviewer_count`, `max_members`) tetap di JSONB, jadi
  menambah rule baru tetap tanpa migration.
- Migrasi legacy jadi pemetaan kolom ke kolom yang bisa diverifikasi `COUNT`.
- Form renderer butuh penanganan khusus untuk kedua field type ini — tetapi
  penanganan itu **sudah harus ada** untuk RepeatableField.
- Dokumen DDD `04_scheme.md` dan `02_ubiquitous_language.md` harus diperbarui
  (bump 2.3 → 2.4).

## Alternatif yang ditolak

**Patuh DDD apa adanya (EAV saja).** Gugur karena migrasi: ayam-telur antara data
legacy dan konfigurasi Form.

**Kolom proyeksi — EAV tetap sumber utama, `scheme_id` salinan turunan.** Dapat
integritas dan performa, tapi menyisakan dua salinan yang bisa menyimpang plus
test konsistensi berkala. Tidak ada manfaat yang tidak juga didapat oleh
keputusan di atas.

**Kolom, tapi BR-SM-01 hanya di Action.** Kehilangan jaring pengaman database
tanpa imbalan; jalur kode baru yang lupa mengecek akan lolos diam-diam.
