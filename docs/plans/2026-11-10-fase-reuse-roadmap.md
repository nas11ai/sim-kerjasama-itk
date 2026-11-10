# Fase Reuse & Form Evaluasi Berversi — Peta Irisan

**Spec:** `docs/design/2026-11-10-fase-reuse-dan-versi-form-design.md` (disetujui pemilik 2026-11-10).
**Basis:** `feat/init-be-development` @ `dc565c5d` (P1 keputusan operator sudah masuk).

Spec mencakup delapan subsistem yang masing-masing bisa dirilis sendiri. Satu rencana untuk
semuanya akan menjadi transkrip kode; jadi spec dipecah menjadi irisan **R1–R8**, satu rencana
per irisan, ditulis tepat sebelum irisan itu dikerjakan (supaya rencana R5 membaca kode hasil R3,
bukan kode hari ini). Rencana rinci yang sudah ada: **R1** (`2026-11-10-r1-jadwal-periode.md`).

## Keputusan bentuk yang mengikat semua irisan (spec §8, diserahkan ke builder)

| # | Keputusan | Alasan |
| --- | --- | --- |
| K1 | **Versi = salin baris**, bukan snapshot JSON, untuk struktur fase (`form_phase_details` milik `form_phase_versions`) dan isi Form Evaluasi (`review_form_fields` milik `review_evaluation_form_versions`). | Pengajuan lama dan jawaban reviewer sudah ber-FK ke baris detail/field. Salin baris membuat FK lama tetap benar tanpa migrasi data jawaban, dan skor lama dihitung ulang dari baris yang sama persis. |
| K2 | **Draf = satu tabel JSON polimorfik** `configuration_drafts (draftable_type, draftable_id, payload jsonb, updated_by, updated_at)`, unik per draftable. Terbitkan = Action yang mewujudkan payload menjadi baris (versi baru, atau baris `form_fields` dengan `created_at`/`required_since` = saat terbit). | Spec §4.5: "waktu berubah = waktu diterbitkan". Draf yang tidak pernah menyentuh baris hidup otomatis memenuhinya; BR-FE-11/12/18 tidak perlu diubah. Banner draf = keberadaan baris draf. |
| K3 | **Angkatan tidak punya tabel sendiri.** Angkatan = (`submission_period_id`, `form_phase_id`). Pemakuan disimpan di tabel pemakuan masing-masing (R3: versi Form Evaluasi per angkatan × langkah × Form Evaluasi; R5: versi struktur per angkatan). | Tidak menambah state "buka periode" (spec §4.9). |
| K4 | **Langkah fase menyebut `deadline_type` + `deadline_cycle`**; tanggal dibaca dari `submission_dates` periode pengajuan lewat satu pintu, `SubmissionPeriod::deadlineFor()`. | Spec §4.3, D1. Unik (periode, jenis, siklus) sudah ada di basis data (BR-FE-25). |
| K5 | Jenis langkah tidak mendapat kolom sendiri: pengajuan = `submit` tanpa siklus, laporan monev n = `submit` siklus n, Upload Luaran = `output`. | Spec §4.2 kalimat terakhir. |
| K6 | Setiap irisan menulis aturan DDD-nya sendiri di Task 1, **setelah pemilik menyetujui** (AGENTS.md: DDD tidak diedit atas inisiatif agen). ADR 0019 ditulis sekali di R1 untuk seluruh desain; irisan berikutnya hanya menambah bagian "Status implementasi". | Spec §3, §6. |

## Irisan

| Irisan | Isi (bagian spec) | Bergantung pada | Siap dirilis karena |
| --- | --- | --- | --- |
| **R1 — Jadwal milik periode** | §4.3, §5 butir 1–2; masalah 2 (paling mendesak) | — | Fase lama tetap jalan; hanya sumber tanggalnya pindah. Migrasi gagal-keras dan ter-rollback bila tanggal yang dibaca pengajuan berubah. |
| **R2 — Pustaka Fase + satu jenis pengajuan per fase** | §4.1 (menu Fase mandiri `/admin/phases`), §4.2, §5 butir "tidak dipecah otomatis", P2 | R1 | Halaman fase lama di bawah Form dialihkan; aturan baru hanya menolak penyimpanan baru, fase lama yang melanggar ditandai. |
| **R3 — Form Evaluasi mandiri & berversi** | §4.1 (menu Form Evaluasi), §4.4 kecuali aksi "terapkan", §5 butir 4–5; draf/terbit untuk Form Evaluasi (K2) | R2 | Data lama menjadi versi 1 dan dipaku; reviewer melihat lembar yang sama. Label versi di layar & ekspor. |
| **R4 — Terapkan versi baru ke angkatan** | §4.4 aksi eksplisit, pengecualian BR-REV-08, notifikasi reviewer | R3 | Aksi baru yang jarang; tidak mengubah perilaku lain. |
| **R5 — Struktur fase berversi, dipaku per angkatan** | §4.3a, §5 butir 3 | R2, R3 | Setiap fase menjadi versi 1; `MonevCycles` membaca versi angkatan. Belum ada cara membuat versi 2 sampai R6, jadi perilaku tidak berubah saat rilis. |
| **R6 — Draf → Pratinjau → Terbitkan untuk Form & Fase** | §4.5, §4.6, BR-FE-13 pindah ke penerbitan | R5 (terbit fase = versi struktur baru) | Form/fase lama dianggap sudah terbit; edit berikutnya masuk draf. |
| **R7 — Rekam teks saat submit** | §4.7 | — (boleh kapan saja sesudah R1) | Kolom snapshot nullable; pengajuan lama tetap memakai teks terkini. |
| **R8 — Cek kesiapan angkatan** | §4.9 (tanpa isi tanggal dari periode sebelumnya — dibuang pemilik) | R1–R6 | Hanya memblokir pengajuan **baru** di angkatan yang gagal; angkatan berjalan cuma diberi peringatan. |
| (R9 — Pemulihan terbitan) | §4.8 | R6 | Opsional, tidak direncanakan kecuali pemilik meminta. |

Urutan R1 → R8 sesuai kolom ketergantungan. R7 boleh disisipkan lebih awal bila ada sela.

## Jadwal dipadatkan (pemilik 2026-11-10: selesai paling lambat 2 minggu, sebisanya minggu ini)

**Tanpa migrasi data.** Aplikasi belum produksi, jadi setiap irisan hanya mengubah skema; DB dev
di-reset `migrate:fresh --seed`. Akibatnya hilang: pemeriksaan selisih tanggal di R1, backfill
"versi 1" di R3/R5, dan penandaan fase lama yang melanggar di R2 (§5 spec tidak berlaku lagi).
`EndToEndFlowSeeder` menjadi satu-satunya sumber data dev dan wajib mengikuti skema baru di tiap gelombang.

Irisan digabung menjadi empat gelombang. Satu rencana per gelombang; satu review menyeluruh di akhir
gelombang, bukan per task.

| Gelombang | Irisan | Perkiraan session | Target |
| --- | --- | --- | --- |
| G1 | R1 + R2 — jadwal milik periode, pustaka Fase, satu jenis pengajuan per fase | 2–3 | Rab 11 – Kam 12 Nov |
| G2 | R3 + R4 — Form Evaluasi mandiri & berversi, aksi terapkan versi | 2–3 | Kam 12 – Jum 13 Nov |
| G3 | R5 + R6 — struktur fase berversi, Draf → Pratinjau → Terbitkan | 3 | Sab 14 – Sen 16 Nov |
| G4 | R7 + R8 — rekam teks saat submit, cek kesiapan angkatan | 1–2 | Sel 17 Nov |
| — | Cadangan untuk temuan review & gate | 2 | sampai Selasa 24 Nov |

**Keputusan pemilik 2026-11-10 (sesudah jadwal dipadatkan):**
- Semua perubahan DDD/ADR di §6 spec **disetujui sekaligus**. Task dokumen tiap gelombang langsung dikerjakan
  tanpa menunggu; pemilik memeriksanya di diff commit. Ini menggantikan K6 bagian "setelah pemilik menyetujui".
- **Isi tanggal periode baru dari periode sebelumnya + 1 tahun (§4.9) dibuang.**
- Eksekusi **native, multi-session** (hemat token): satu session mengerjakan satu potongan rencana
  (mis. R1-a lalu R1-b), lalu pemilik membuka session baru dengan "lanjut".

Total 8–11 session. "Minggu ini" (sampai Minggu 15 Nov) hanya tercapai bila setiap gelombang lolos
review pertama; batas dua minggu (24 Nov) aman. Gate dijalankan berurutan (WSL OOM), jadi gelombang
tidak dikerjakan paralel.


## Yang dibawa setiap rencana irisan

- Header writing-plans, Global Constraints, Review Focus, tabel kesesuaian BDD (pola rencana P1).
- Task 1 = dokumen (ADR/DDD/BDD) bila irisan itu mengubah aturan; ditandai **butuh persetujuan pemilik**.
- Task terakhir = gate penuh (`composer run quality`, `npm run quality`) dengan pemeriksaan RAM dulu
  (memori proyek: WSL OOM, jalankan berurutan di bawah watchdog).
- Migrasi tidak dijalankan agen terhadap DB berisi data; pemilik menjalankan `php artisan migrate` sendiri.
