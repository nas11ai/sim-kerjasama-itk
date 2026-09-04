# 0015 — `Gate::before` tidak melewati invarian domain

**Status:** Diterima
**Tanggal:** 2026-09-04
**Menutup:** register #1

## Konteks

`AppServiceProvider` memasang idiom Laravel yang lazim:

```php
Gate::before(fn ($user, $ability) => $user->hasRole('Super Admin') ? true : null);
```

Satu baris itu memberi Super Admin **setiap** ability tanpa syarat — termasuk `submit`.
Sementara itu `SubmitFormSubmission::run()` menegakkan BR-SM-04 sendiri: hanya
`submitted_by` yang boleh submit. Dua lapis berkata berbeda tentang hal yang sama.

Akibatnya konkret, bukan teoretis: objek `can` yang dikirim ke frontend menyatakan Super
Admin boleh submit, jadi tombolnya tampil — lalu Action menolaknya saat ditekan. Tombol
yang dijamin gagal adalah bug yang baru ketahuan di produksi.

Yang membuat ini menarik: **Action-nya sudah benar.** Yang keliru justru lapis
authorization yang lebih longgar dari invarian domainnya.

## Keputusan

**Ability yang menyandang invarian domain tidak pernah dilewati `Gate::before`.**
Daftarnya eksplisit dan bernama:

```php
private const INVARIANT_ABILITIES = ['submit', 'resubmit'];
```

Alasannya bukan soal seberapa tinggi kewenangan seseorang, melainkan soal apa yang
sedang dinyatakan. Submit adalah tindakan `submitted_by` **mengikat dirinya sendiri**
secara kontraktual. Super Admin boleh mengurus apa pun di sistem ini, tapi ia tidak bisa
*menjadi* orang lain — membiarkan bypass berarti menghasilkan record yang menyatakan
seorang peneliti mengajukan proposal padahal bukan dia yang melakukannya.

**Kalau admin memang harus mengajukan untuk orang lain**, jalurnya sudah ada: transfer
kepemilikan (BR-SM-09/BR-IAM-10). Jalur itu meninggalkan jejak audit dan membuat
`submitted_by` tetap jujur. Menambah bypass diam-diam menghapus keduanya.

### Kenapa daftar-tolak, bukan daftar-izin

Daftar-tolak memang lebih rapuh secara teori: ability baru yang menyandang invarian harus
diingat untuk didaftarkan. Tapi menggantinya dengan daftar-izin berarti mencabut bypass
untuk **seluruh** ability sekaligus, dan Super Admin bisa kehilangan akses ke banyak hal
tanpa ada yang menyadarinya sampai seseorang mengeluh.

Konstanta bernama beserta komentarnya membuat penambahan berikutnya jadi tindakan sadar,
dan `SuperAdminSubmitDivergenceTest` menjaga keempat ability `FormSubmissionPolicy` —
yang di-bypass maupun yang tidak — tetap berperilaku seperti yang dinyatakan di sini.

## Konsekuensi

- `submit` dan `resubmit` kini dievaluasi Policy sungguhan untuk semua orang, termasuk
  Super Admin. `view`, `update`, dan `withdraw` tetap di-bypass.
- `SuperAdminSubmitDivergenceTest` berubah sifat: dari memotret divergensi menjadi
  **menegakkan** aturannya, plus menjaga ability yang sengaja tetap di-bypass.
- Objek `can` yang dikirim ke frontend kini jujur — tombol Submit tidak lagi muncul untuk
  orang yang pasti ditolak.
- Setiap invarian domain baru yang diwujudkan sebagai ability harus ditambahkan ke
  `INVARIANT_ABILITIES`, dan itu keputusan sadar yang terlihat di diff.

## Alternatif yang ditolak

**Membiarkan divergensi dan memperbaiki UI saja** (menyembunyikan tombol lewat cek
tambahan di frontend). Menempatkan invarian domain di lapis presentasi; klien lain —
API, skrip, mobile — tetap melihat izin yang salah.

**Melonggarkan BR-SM-04 supaya Super Admin ikut boleh submit.** Menyelesaikan
pertentangan dengan mengorbankan kejujuran data. `submitted_by` berhenti berarti "yang
mengajukan", dan setiap laporan yang mengandalkannya jadi meragukan.

**Menghapus `Gate::before` sepenuhnya.** Paling benar secara arsitektur, tapi mencabut
bypass untuk seluruh ability sekaligus di codebase yang test admin-flow-nya masih tipis.
Layak ditinjau ulang saat cakupan test lapis admin sudah memadai.
