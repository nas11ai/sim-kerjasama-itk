# Business Rule yang Bukan Perilaku

Sembilan BR di bawah tidak bisa ditulis sebagai perubahan state yang teramati dari
sudut pandang aktor. Memaksakan Gherkin untuknya hanya menghasilkan noise yang
menyamarkan skenario asli. Masing-masing tetap ditegakkan — kolomnya menyebut oleh apa.

Kriteria dan alasan lengkap: `docs/design/bdd-gherkin-baseline.md` §6.

| BR | Isi | Ditegakkan oleh |
| -- | --- | --------------- |
| BR-FE-03 | `FormFieldResponse` hanya menyimpan scalar | Skema kolom + `ModelSchemaDriftTest` |
| BR-FE-07 | `FormPhaseDetail.submission_date_id` NOT NULL | Constraint database + test migrasi |
| BR-BUD-07 | Grand total selalu computed, tak ada kolom | `ModelSchemaDriftTest` |
| BR-REV-13 | `ReviewSummary.status` hanya `open`/`resolved` | Tipe enum + schema drift |
| BR-SCH-06 | Menambah rule ke JSONB tanpa migration | Sifat kolom JSONB — tidak ada yang bisa gagal |
| BR-SCH-09 | `scheme_selector` menulis ke kolom, bukan EAV | ADR 0005 + schema drift |
| BR-RPT-01 | Reporting read-only | Review arsitektur |
| BR-RPT-06 | Statistik live query, tanpa cache | Keputusan arsitektur |
| BR-NOT-05 | Unread count di-cache | Implementation detail |

> **Penegakan tambahan untuk BR-REV-14.** Aturannya sendiri **bersifat perilaku** dan
> dibuktikan `[REV-OUTCOME-08]` — ia tidak dipindahkan ke sini. Tapi satu skenario hanya
> membuktikan satu jalur, sedangkan BR-REV-14 menyatakan sebuah *ketiadaan*: "tidak boleh
> ada jalur kode yang men-transisikan submission ke REJECTED secara otomatis". Karena itu
> ia mendapat penjagaan kedua yang bersifat struktural — pencarian
> `transitionTo(Rejected::class)` di luar Action `RejectSubmission` harus nol.
> Ini **pelengkap, bukan pengganti** skenarionya: ia mencari apa yang tidak bisa dilihat
> oleh satu contoh.

## Memindahkan BR ke sini

Boleh, tapi wajib disertai alasan tertulis di baris tabelnya dan dibahas di PR.
Perpindahan ke arah sebaliknya — dari sini ke skenario — tidak butuh pembahasan.
