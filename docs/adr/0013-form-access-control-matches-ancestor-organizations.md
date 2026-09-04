# 0013 — Akses Form ditentukan leluhur organisasi, bukan keturunannya

**Status:** Diterima
**Tanggal:** 2026-09-04
**Membatalkan:** pseudocode `canAccessForm` dan CTE rekursif di
`docs/requirement-analysis/ddd/generic/02_identity_access.md`, serta bunyi BR-FE-02
di `docs/requirement-analysis/ddd/generic/01_form_engine.md` yang menyebut
"org subtree user".

## Konteks

BR-FE-02 berbunyi "User hanya bisa akses Form jika ada FormAccessControl yang match
permission user **DAN org subtree user**". Pseudocode di DDD menerjemahkannya
menjadi `Organization::subtreeIds($user->profile->organization_id)` — himpunan
**keturunan** organisasi user — lalu memeriksa apakah `FormAccessControl.organization_id`
ada di dalamnya. `app/Models/FormAccessControl.php` mengimplementasikan persis itu.

Arah tersebut membuat fitur tidak berfungsi. Untuk Form se-institut yang di-scope ke
node akar `ITK`:

| Organisasi user | `subtreeIds(user)` | `ITK` ada di dalamnya? |
| --------------- | ------------------ | ---------------------- |
| ITK             | {ITK, FTI, Informatika, …} | ya → akses |
| FTI             | {FTI, Informatika} | **tidak → ditolak** |
| Informatika     | {Informatika}      | **tidak → ditolak** |

Form yang di-scope paling luas justru hanya terlihat oleh user yang organisasinya
persis node akar, sementara seluruh peneliti — yang duduk di prodi dan departemen —
terkunci di luar. Makin luas niat scoping-nya, makin sedikit yang bisa melihat. Itu
kebalikan dari yang diminta LPPM.

Yang membuatnya sulit terlihat: dengan data uji yang semua user-nya duduk di node
akar, kedua arah berperilaku sama. Divergensinya baru muncul begitu ada hierarki
sungguhan.

## Keputusan

`FormAccessControl.organization_id` harus merupakan **ancestor-or-self** dari
organisasi user. Dibaca sebagai kalimat: *Form yang dipasang pada sebuah organisasi
terlihat oleh organisasi itu dan semua unit di bawahnya.*

Penelusuran naik, bukan turun:

```sql
WITH RECURSIVE org_ancestors AS (
    SELECT id, parent_id FROM organizations WHERE id = $1
    UNION ALL
    SELECT o.id, o.parent_id FROM organizations o
    INNER JOIN org_ancestors oa ON o.id = oa.parent_id
    WHERE o.is_active = true
)
SELECT id FROM org_ancestors;
```

Jalurnya `Organization::ancestorIds(int $orgId): list<int>`, sejajar `subtreeIds()`
yang sudah ada dan memakai pola cache yang sama (`org_ancestors_{id}`, TTL 30 menit).

`subtreeIds()` **tidak dihapus** — ia tetap jawaban yang benar untuk pertanyaan
"organisasi apa saja yang berada di bawah saya", yang dipakai pelaporan agregat.
Yang keliru bukan fungsinya, melainkan pemakaiannya di jalur access control.

Berapa dalam pun hierarkinya, jumlah leluhur sebuah node sama dengan kedalamannya —
untuk ITK tidak lebih dari empat atau lima baris. Penelusuran naik justru lebih murah
daripada penelusuran turun yang harus memindai seluruh keturunan.

## Konsekuensi

- `FormAccessControl::organizationSubtreeFor()` dan `scopeAccessibleBy()`
  (`app/Models/FormAccessControl.php:95–125`) memakai arah yang salah dan **wajib
  dikoreksi** saat BR-FE-02 diimplementasikan. Sampai itu terjadi, Form ber-scope luas
  tidak terlihat oleh peneliti di prodi.
- Invalidasi cache berbalik arah: cache `subtreeIds` sebuah node dibersihkan saat
  keturunannya berubah, sedangkan cache `ancestorIds` harus dibersihkan saat
  **leluhurnya** berubah — yaitu saat `parent_id` sebuah organisasi dipindahkan.
- Skenario `[FE-ACCESS-02]` dan `[FE-ACCESS-03]` mengunci arah ini. Keduanya sudah
  tertulis dan terdaftar di `pending.txt`.
- Memindahkan sebuah organisasi ke induk baru mengubah siapa yang bisa melihat Form
  yang dipasang di leluhur lama maupun baru. Ini konsekuensi yang dikehendaki, tapi
  perlu disebut di UI Organization Management supaya tidak mengagetkan.

## Alternatif yang ditolak

**Membiarkan arah DDD dan menyuruh Operator memasang FormAccessControl di setiap
prodi.** Untuk Form se-institut itu berarti puluhan baris ACL yang harus dirawat
manual setiap kali ada prodi baru, dan satu prodi yang terlewat berarti peneliti di
sana tidak bisa mengajukan tanpa ada yang tahu kenapa. Hierarki organisasi ada
justru supaya hal ini tidak perlu dikerjakan tangan.

**Menyimpan closure table `organization_paths`.** Membuat kedua arah jadi query
sekali jalan tanpa CTE, tapi menambah tabel yang wajib dijaga konsisten lewat
trigger atau observer. Kedalaman pohon ITK tidak sebanding dengan ongkos itu;
kalau nanti pohonnya jauh lebih dalam, keputusan ini bisa ditinjau ulang tanpa
mengubah pemanggilnya.

**Menaruh daftar organisasi yang diizinkan langsung di Form sebagai array.**
Menghapus pertanyaan hierarki sama sekali, tapi membuang informasi struktur yang
sudah dimiliki sistem dan memindahkan perawatannya ke tangan Operator.
