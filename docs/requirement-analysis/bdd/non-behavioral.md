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

## Memindahkan BR ke sini

Boleh, tapi wajib disertai alasan tertulis di baris tabelnya dan dibahas di PR.
Perpindahan ke arah sebaliknya — dari sini ke skenario — tidak butuh pembahasan.
