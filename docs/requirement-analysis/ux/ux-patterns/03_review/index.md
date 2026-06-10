# UX Pattern: Review

**Roles yang terlibat:** `Reviewer` `Researcher` `Operator` `Admin`  
**DDD Context:** Review  
**Pattern utama:** Table + Filter, Split Panel, Threaded Comments  
**Versi:** 1.0  
**Status:** Draft

> Referensi IA: `ia/03_review/index.md`

---

## Penugasan Review — List

**Pattern:** Table + Filter (ringan)  
**Layout:** Sidebar + Main Content

### Organisms

| Organism                 | Deskripsi                                                                                                            | Posisi     |
| ------------------------ | -------------------------------------------------------------------------------------------------------------------- | ---------- |
| `ReviewAssignmentFilter` | Toggle: Perlu Tindakan / Semua                                                                                       | Main, atas |
| `ReviewAssignmentList`   | List card per submission yang di-assign. Setiap card: judul, lead researcher, skema, deadline evaluasi, status badge | Main       |

### States

| State   | Trigger                         | Tampilan                                                                                          |
| ------- | ------------------------------- | ------------------------------------------------------------------------------------------------- |
| Empty   | Belum ada penugasan             | Empty state: "Belum ada penugasan review"                                                         |
| Expired | Masa tugas reviewer sudah lewat | Banner atas: "Masa tugas Anda sebagai reviewer telah berakhir. Penugasan ini bersifat read-only." |

---

## Form Evaluasi

**Pattern:** Split Panel  
**Layout:** Split 50/50 — kiri: submission, kanan: form evaluasi

### Organisms

| Organism                  | Deskripsi                                                                                      | Posisi      |
| ------------------------- | ---------------------------------------------------------------------------------------------- | ----------- |
| `SubmissionReadOnlyPanel` | Detail submission: judul, abstrak, anggota, berkas (bisa preview PDF inline)                   | Kiri        |
| `EvaluationFormPanel`     | ReviewEvaluationForm dengan semua ReviewFormField                                              | Kanan       |
| `EvaluationFormTabs`      | Jika ada lebih dari satu ReviewEvaluationForm (ReviewerFormAssignment), tampilkan sebagai tabs | Kanan, atas |

### Interaction Notes

PDF proposal bisa dibuka inline di panel kiri menggunakan PDF viewer — tidak perlu download untuk review. Reviewer bisa resize kedua panel dengan drag handle di tengah.

Saat semua field terisi, tombol "Submit Evaluasi" menjadi aktif. Ada konfirmasi sebelum submit: "Setelah submit, form tidak bisa diedit lagi."

### States

| State         | Trigger                                   | Tampilan                                                               |
| ------------- | ----------------------------------------- | ---------------------------------------------------------------------- |
| Draft         | Belum submit                              | Form editable, tombol "Simpan Draft" dan "Submit Evaluasi"             |
| Locked        | Setelah submit                            | Form read-only, semua input disabled, banner "Evaluasi telah disubmit" |
| Score visible | reviewer_internal, semua reviewer selesai | Section tambahan: "Skor Reviewer Lain" muncul di panel kanan           |

### Business Rules yang Mempengaruhi UI

- `→ ddd/core/02_review.md#BR-REV-08` — setelah submit, form locked. Tidak ada tombol "Edit" yang muncul.
- `→ ddd/core/02_review.md#BR-REV-11` — skor reviewer lain hanya terlihat oleh `reviewer_internal` dan hanya setelah semua reviewer selesai.

---

## Review Summary & Diskusi

**Pattern:** Threaded Comments  
**Layout:** Full-width, single column

### Organisms

| Organism            | Deskripsi                                                                            | Posisi      |
| ------------------- | ------------------------------------------------------------------------------------ | ----------- |
| `ReviewSummaryCard` | Status summary (open/resolved), catatan ringkasan reviewer, tombol "Tandai Resolved" | Main, atas  |
| `CommentThread`     | Nested komentar dengan form reply inline                                             | Main, bawah |

### Molecules yang Notable

- **`CommentBubble`** — bubble komentar dengan: avatar, nama, role badge (Reviewer / Researcher), timestamp relatif, isi komentar, tombol "Balas".
- **`InlineReplyForm`** — form textarea yang muncul langsung di bawah komentar saat tombol "Balas" diklik. Bukan modal.

### Interaction Notes

Reply muncul secara optimistic — langsung tampil sebelum server response, dengan indikator "Mengirim...". Jika gagal, ditampilkan error inline dengan tombol retry.

Komentar nested tidak tak terbatas — maksimum 2 level indent untuk menjaga keterbacaan (reply dari reply tetap flat, bukan nested lebih dalam).

### States

| State                | Trigger                                        | Tampilan                                                                                              |
| -------------------- | ---------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| Open                 | Ada ReviewSummary open                         | `ReviewSummaryCard` dengan header merah/orange, form reply aktif                                      |
| Resolved             | ReviewSummary resolved                         | `ReviewSummaryCard` dengan header hijau, form reply disabled, badge "Selesai"                         |
| Auto-approve pending | Semua summary resolved, semua evaluasi selesai | Banner info: "Semua catatan revisi sudah diselesaikan. Sistem sedang memproses persetujuan otomatis." |

### Business Rules yang Mempengaruhi UI

- `→ ddd/core/02_review.md#BR-REV-05` — jika kondisi auto-approve terpenuhi, tampilkan banner info di atas thread. Tidak ada tombol manual approve.

---

## Assign Reviewer

**Pattern:** List + Search + Konfirmasi  
**Layout:** Modal atau Sidebar Panel dari Detail Submission

### Organisms

| Organism                | Deskripsi                                                            | Posisi        |
| ----------------------- | -------------------------------------------------------------------- | ------------- |
| `AssignedReviewerList`  | Daftar reviewer yang sudah di-assign, dengan tombol hapus per item   | Atas          |
| `ReviewerSearchForm`    | Search by nama/NIDN, filter tipe (internal/external)                 | Tengah        |
| `ReviewerSearchResults` | Hasil search sebagai list: nama, tipe, workload, status eligibility  | Tengah, bawah |
| `AssignmentConfirmBar`  | Counter "N dari minimal M reviewer" + tombol "Konfirmasi Assignment" | Bawah         |

### States

| State         | Trigger                             | Tampilan                                                                     |
| ------------- | ----------------------------------- | ---------------------------------------------------------------------------- |
| Conflict      | Reviewer punya conflict of interest | Item di search results: badge merah "Konflik Kepentingan", tidak bisa diklik |
| High workload | Workload ≥ max                      | Item di search results: badge kuning "Workload Tinggi", masih bisa dipilih   |
| Insufficient  | Jumlah < min_reviewer_count         | `AssignmentConfirmBar` tombol disabled, counter merah                        |
| Sufficient    | Jumlah ≥ min_reviewer_count         | `AssignmentConfirmBar` tombol aktif, counter hijau                           |
