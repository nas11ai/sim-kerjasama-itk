# UX Pattern: Authentication & Onboarding

**Roles yang terlibat:** `Researcher` `Reviewer` `Operator` `Admin`  
**DDD Context:** Identity & Access  
**Pattern utama:** Simple Form, Dashboard — Statistics  
**Versi:** 1.0  
**Status:** Draft

> Referensi IA: `ia/01_auth/index.md`

---

## Login

**Pattern:** Simple Form  
**Layout:** Centered card, full-height page  
**Accessible by:** Semua (unauthenticated)

### Organisms

| Organism    | Deskripsi                                                 | Posisi |
| ----------- | --------------------------------------------------------- | ------ |
| `LoginForm` | Form email + password + tombol login + link lupa password | Center |

### States

| State               | Trigger                            | Tampilan                                                |
| ------------------- | ---------------------------------- | ------------------------------------------------------- |
| Idle                | Halaman pertama dibuka             | Form kosong                                             |
| Loading             | Tombol login diklik                | Tombol berubah jadi spinner, input disabled             |
| Error — credentials | Email/password salah               | Inline error di bawah form: "Email atau password salah" |
| Error — pending     | Login berhasil tapi status pending | Redirect ke `/pending-verification`                     |
| Success             | Login berhasil, status active      | Redirect ke `/dashboard`                                |

---

## Register — Self

**Pattern:** Simple Form  
**Layout:** Centered card, full-height page  
**Accessible by:** Unauthenticated

### Organisms

| Organism           | Deskripsi                                      | Posisi |
| ------------------ | ---------------------------------------------- | ------ |
| `SelfRegisterForm` | Form registrasi dosen ITK dengan OrgTreePicker | Center |

### Molecules yang Notable

- **`OrgTreePicker`** — dropdown bertingkat untuk pilih unit organisasi (fakultas → prodi). Bisa search by nama. Setelah pilih, tampilkan breadcrumb organisasi yang dipilih.

### Interaction Notes

Validasi email domain `@itk.ac.id` dilakukan **client-side on-blur** sebelum submit — user langsung tahu tanpa harus nunggu response server. NIDN uniqueness dicek server-side saat submit.

### States

| State                 | Trigger                            | Tampilan                            |
| --------------------- | ---------------------------------- | ----------------------------------- |
| Loading               | Submit diklik                      | Tombol spinner, form disabled       |
| Error — email domain  | Email bukan `@itk.ac.id` (on blur) | Inline error di field email         |
| Error — NIDN duplikat | Server reject                      | Inline error di field NIDN          |
| Success               | Register berhasil                  | Redirect ke `/pending-verification` |

### Business Rules yang Mempengaruhi UI

- `→ ddd/generic/02_identity_access.md#BR-IAM-01` — validasi domain email real-time on-blur.

---

## Register — via Invitation

**Pattern:** Simple Form (pre-filled)  
**Layout:** Centered card, full-height page

### Organisms

| Organism                 | Deskripsi                                                                        | Posisi |
| ------------------------ | -------------------------------------------------------------------------------- | ------ |
| `InvitationInfoBanner`   | Banner read-only: "Anda diundang bergabung sebagai [permission] di [organisasi]" | Top    |
| `InvitationRegisterForm` | Form email (bebas) + password + konfirmasi password                              | Center |

### States

| State         | Trigger                             | Tampilan                                                              |
| ------------- | ----------------------------------- | --------------------------------------------------------------------- |
| Token invalid | Token expired atau used_count habis | Full-page error: "Link undangan tidak valid" — form tidak ditampilkan |
| Success       | Register berhasil                   | Redirect langsung ke `/dashboard`                                     |

---

## Pending Verification

**Pattern:** Informational (bukan form)  
**Layout:** Centered card, full-height page

### Organisms

| Organism            | Deskripsi                                                        | Posisi |
| ------------------- | ---------------------------------------------------------------- | ------ |
| `PendingStatusCard` | Ilustrasi + judul + penjelasan + NIDN yang diinput + kontak LPPM | Center |

### Interaction Notes

Halaman ini statis — tidak ada aksi. Jika user refresh setelah operator memverifikasi, mereka otomatis redirect ke dashboard. Bisa tambahkan polling ringan (setiap 30 detik) untuk check status tanpa manual refresh.

---

## Dashboard (semua role)

**Pattern:** Dashboard — Statistics  
**Layout:** Sidebar + Main Content  
**Accessible by:** Semua (post-login)

### Organisms

| Organism               | Deskripsi                                                                | Posisi                |
| ---------------------- | ------------------------------------------------------------------------ | --------------------- |
| `WelcomeBanner`        | Sapaan dengan nama user + pesan kontekstual per role                     | Main, atas            |
| `SummaryCards`         | 3–4 kartu angka ringkasan yang berbeda per role                          | Main, baris pertama   |
| `RecentActivityList`   | 5 aktivitas terbaru yang relevan                                         | Main, kiri bawah      |
| `UpcomingDeadlines`    | Deadline yang mendekat dalam 7 hari                                      | Main, kanan bawah     |
| `PendingActionsBanner` | Khusus Operator: "N pengajuan butuh reviewer", "N user perlu verifikasi" | Main, atas (jika ada) |

### Konten SummaryCards per Role

| Role       | Cards                                                                                |
| ---------- | ------------------------------------------------------------------------------------ |
| Researcher | Submission Aktif, Dalam Revisi, Disetujui, Notifikasi Belum Dibaca                   |
| Reviewer   | Perlu Dievaluasi, Evaluasi Selesai, Deadline Terdekat                                |
| Operator   | Total Submission Aktif, Perlu Assign Reviewer, Perlu Verifikasi User, Export Selesai |
| Admin      | Sama dengan Operator + total user aktif                                              |

### States

| State               | Trigger                   | Tampilan                                                 |
| ------------------- | ------------------------- | -------------------------------------------------------- |
| Loading             | Pertama buka              | Skeleton loader di semua card dan list                   |
| Empty — no activity | User baru, belum ada data | Empty state di RecentActivityList: "Belum ada aktivitas" |

---

## Profil Saya

**Pattern:** Simple Form  
**Layout:** Sidebar + Main Content (halaman reguler, bukan centered)

### Organisms

| Organism               | Deskripsi                                          | Posisi      |
| ---------------------- | -------------------------------------------------- | ----------- |
| `ProfilePhotoUploader` | Upload + preview foto profil dengan crop sederhana | Main, kiri  |
| `ProfileForm`          | Form edit data profil                              | Main, kanan |
| `ChangePasswordForm`   | Form ganti password (terpisah dari profil)         | Main, bawah |

### States

| State                       | Trigger                 | Tampilan                            |
| --------------------------- | ----------------------- | ----------------------------------- |
| Success — profil            | Simpan berhasil         | Toast: "Profil berhasil diperbarui" |
| Success — password          | Ganti password berhasil | Toast: "Password berhasil diubah"   |
| Error — password lama salah | Server reject           | Inline error di field password lama |
