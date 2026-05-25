# UX Pattern: Monev

**Roles yang terlibat:** `Researcher` `Reviewer` `Operator` `Admin`  
**DDD Context:** Monev, Form Engine  
**Pattern utama:** Phase Progression, Simple Form  
**Versi:** 1.0  
**Status:** Draft

> Referensi IA: `ia/05_monev/index.md`

---

## Daftar Monev — Researcher View

**Pattern:** Phase Progression  
**Layout:** Sidebar + Main Content

### Organisms

| Organism | Deskripsi | Posisi |
|----------|-----------|--------|
| `SubmissionMonevCard` | Card per submission APPROVED, berisi phase progression visual + aksi per siklus | Main |

### Molecules yang Notable

- **`PhaseProgressionBar`** — timeline horizontal atau vertikal yang menampilkan semua stage dalam lifecycle submission: Pengajuan → Evaluasi → Monev I → Evaluasi Monev I → Monev II → dst. Setiap node memiliki status visual.

**Status visual per node:**

| Status | Visual |
|--------|--------|
| Selesai | Node hijau dengan checkmark |
| Aktif | Node biru/primary dengan animasi pulse |
| Menunggu Giliran | Node gray dengan lock icon |
| Deadline Lewat | Node merah dengan icon expired |
| Dibekukan (Withdrawn) | Node gray dengan icon freeze |

### States

| State | Trigger | Tampilan |
|-------|---------|----------|
| Empty | Tidak ada submission APPROVED | Empty state: "Belum ada pengajuan yang disetujui" |
| All locked | Semua siklus belum diaktifkan operator | PhaseProgressionBar dengan semua node abu-abu setelah node Pengajuan |
| Withdrawn | Submission di-withdraw saat monev aktif | Banner merah di card: "Pengajuan ini telah ditarik. Monev dibekukan." |

### Business Rules yang Mempengaruhi UI

- `→ ddd/supporting/02_monev.md#BR-MON-02` — node siklus berikutnya ditampilkan sebagai locked (ikon gembok) jika siklus sebelumnya belum selesai.
- `→ ddd/supporting/02_monev.md#BR-MON-05` — siklus yang deadline-nya lewat tanpa override ditampilkan sebagai "Ditutup" (merah), bukan locked.
- `→ ddd/supporting/02_monev.md#BR-MON-08` — submission WITHDRAWN: semua node monev yang belum selesai berubah ke status "Dibekukan".

---

## Isi Laporan Monev

**Pattern:** Simple Form  
**Layout:** Sidebar + Main Content (full)

### Organisms

| Organism | Deskripsi | Posisi |
|----------|-----------|--------|
| `MonevContextHeader` | Info konteks: nama submission parent, nama siklus, deadline, sisa waktu | Main, atas |
| `MonevFormFields` | Render FormFields dari FormPhaseDetail yang aktif | Main |
| `MonevFileUpload` | Upload file kelengkapan laporan (jika ada field tipe file) | Main |
| `DeadlineCountdown` | Countdown deadline dengan warna merah jika < 48 jam | Main, atas (sticky) |

### States

| State | Trigger | Tampilan |
|-------|---------|----------|
| Loading | Pertama buka | Skeleton form |
| Deadline lewat — no override | Deadline terlewat | Full-page block: "Batas waktu pengisian laporan telah berakhir. Hubungi operator untuk perpanjangan." |
| Deadline lewat — ada override | Ada FormSubmissionOverride aktif | Banner kuning: "Anda mendapatkan perpanjangan akses hingga [tanggal]. Akses diberikan oleh operator." |
| Success | Submit berhasil | Redirect ke Daftar Monev dengan toast sukses |

### Business Rules yang Mempengaruhi UI

- `→ ddd/generic/01_form_engine.md#BR-FE-08` — deadline lewat tanpa override: halaman di-block total, bukan form disabled.
- `→ ddd/generic/01_form_engine.md` — `FormSubmissionOverride` visibility: researcher bisa lihat status override tapi tidak bisa lihat reason-nya.

---

## Aktivasi Siklus & Assign Reviewer — Operator

**Pattern:** Phase Progression + List  
**Layout:** Sidebar + Main Content

### Organisms

| Organism | Deskripsi | Posisi |
|----------|-----------|--------|
| `MonevPhaseTimeline` | Phase progression vertikal untuk satu submission — lebih detail dari versi researcher | Main, kiri |
| `MonevStagePanel` | Panel detail siklus yang dipilih: status laporan researcher, form assign reviewer | Main, kanan |

### Interaction Notes

Klik node di `MonevPhaseTimeline` untuk switch `MonevStagePanel` ke siklus tersebut. Layout ini mirip dengan master-detail tapi dalam satu halaman.

Saat assign reviewer, sistem menampilkan suggestion reviewer dari pengajuan awal dengan badge "Reviewer Sebelumnya" — dibedakan secara visual dari reviewer lain.

### States

| State | Trigger | Tampilan |
|-------|---------|----------|
| Siklus belum aktif | Operator belum aktifkan | `MonevStagePanel` menampilkan tombol "Aktifkan Siklus" |
| Siklus aktif, belum ada reviewer | Sudah diaktifkan | `MonevStagePanel` menampilkan form assign reviewer |
| Siklus aktif, ada reviewer | Reviewer sudah di-assign | `MonevStagePanel` menampilkan daftar reviewer + status laporan researcher |
