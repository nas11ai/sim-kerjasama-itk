"""Isi matriks uji QA: alur, kasus, dan pembagian penguji.

Dibaca oleh buat_matriks_uji.py. Desain: docs/design/2026-11-14-matriks-uji-qa-design.md.

Aturan menulis kasus:
- Pola Gherkin: diketahui (kondisi awal), ketika (yang dilakukan), maka (yang harus terlihat).
- Bahasa sehari-hari; nama tombol dan menu persis seperti di layar, ditulis dalam tanda kutip.
- {k} = nomor set penguji (1-4). Akun: qa{k}-peneliti01@simpas.test, kata sandi = alamat email.
- "#3" = pengajuan nomor 3 di set penguji (lihat lembar "Akun & Data").
- Jangan memakai tanda pisah panjang (em dash); generator menolaknya.
"""

# Lokasi halaman: jalur menu, lalu alamat relatif. "…" = angka yang berbeda tiap data.
BERANDA = "Menu kiri › Beranda\n/dashboard"
DAFTAR = "Menu kiri › Pengajuan › Daftar Pengajuan\n/user/submissions"
DETAIL = "Menu kiri › Pengajuan › Daftar Pengajuan › klik judul pengajuan\n/user/submissions/…"
WIZARD = "Daftar Pengajuan › klik judul draf › \"Lanjutkan draf\"\n/user/submissions/…/wizard/…"
BUAT = "Menu kiri › Pengajuan › Buat Pengajuan Baru\n/user/submissions/create"
REVISI = "Detail pengajuan › \"Ubah Pengajuan\"\n/user/submissions/…/revision"
TUGAS = "Menu kiri › Penilaian › Penugasan Penilaian\n/reviewer/assignments"
EVAL = "Penugasan Penilaian › kartu pengajuan › \"Mulai Evaluasi\"\n/reviewer/assignments/…/evaluation"
RIWAYAT_NILAI = "Menu kiri › Penilaian › Riwayat Penilaian\n/reviewer/history"
SEMUA = "Menu kiri › Manajemen › Semua Pengajuan\n/admin/submissions"
SEMUA_DETAIL = "Menu kiri › Manajemen › Semua Pengajuan › klik judul pengajuan\n/admin/submissions/…"
PERIODE = "Menu kiri › Manajemen › Periode Pengajuan\n/admin/submission-periods"
KELOLA_PENGUMUMAN = "Menu kiri › Manajemen › Kelola Pengumuman\n/admin/announcements"
PENGUMUMAN = "Menu kiri › Pengumuman\n/user/announcements"
PENILAI = "Menu kiri › Manajemen › Penilai\n/admin/reviewers"
PENGGUNA = "Menu kiri › Manajemen › Pengguna & Verifikasi\n/admin/users"
PENGGUNA_DETAIL = "Menu kiri › Manajemen › Pengguna & Verifikasi › cari nama › \"Detail\"\n/admin/users/…"
VERIFIKASI = "Pengguna & Verifikasi › tombol \"Antrian Verifikasi NIDN\"\n/admin/verifications"
UNDANGAN = "Pengguna & Verifikasi › tombol \"Undangan Registrasi\"\n/admin/invitations"
MONEV = "Menu kiri › Monev › Daftar Monev\n/monev"
LAPORAN_MONEV = "Menu kiri › Monev › Daftar Monev › \"Lanjutkan Laporan\" / \"Isi Laporan\"\n/monev/reports/…/fill"
KELOLA_MONEV = "Menu kiri › Monev › Kelola Siklus Monev\n/monev/manage"
KELOLA_MONEV_DETAIL = "Menu kiri › Monev › Kelola Siklus Monev › \"Kelola\" pada pengajuan\n/monev/manage/…"
LUARAN = "Menu kiri › Luaran Penelitian\n/research-outputs"
LUARAN_TAMBAH = "Menu kiri › Luaran Penelitian › \"Tambah Luaran\"\n/research-outputs/create"
STATISTIK = "Menu kiri › Laporan › Statistik\n/reporting/stats"
EKSPOR = "Menu kiri › Laporan › Ekspor Data\n/reporting/export"
AUDIT = "Menu kiri › Laporan › Log Audit\n/reporting/audit"
FORMULIR = "Menu kiri › Konfigurasi › Pembuat Formulir\n/admin/forms"
FORMULIR_EVALUASI = "Menu kiri › Konfigurasi › Formulir Evaluasi\n/admin/evaluation-forms"
TAHAP = "Menu kiri › Konfigurasi › Tahap\n/admin/phases"
SKEMA = "Menu kiri › Konfigurasi › Skema Penelitian\n/admin/schemes"
ORGANISASI = "Menu kiri › Konfigurasi › Organisasi\n/admin/organizations"
KONFIGURASI = "Menu kiri › Konfigurasi › Konfigurasi Umum\n/admin/reference"
NOTIFIKASI = "Menu kiri › Notifikasi\n/notifications"
PROFIL = "Klik nama Anda di bagian bawah menu kiri\n/profile"
MASUK = "Halaman masuk\n/login"
DAFTAR_AKUN = "Halaman masuk › tautan daftar\n/register"
SAMBUTAN = "Alamat utama situs (belum masuk)\n/"
PALET = "Kotak pencarian di bagian atas layar (atau tekan Ctrl+K)"

PENELITI = "qa{k}-peneliti{n}@simpas.test"
OPERATOR = "qa{k}-operator@simpas.test"
PENILAI1 = "qa{k}-penilai1@simpas.test"
PENILAI2 = "qa{k}-penilai2@simpas.test"
PENILAI_LUAR = "qa{k}-penilai-luar@simpas.test"
ANGGOTA = "qa{k}-anggota@simpas.test"
ADMIN = "qa-admin@simpas.test"


def peneliti(n: int) -> str:
    return PENELITI.replace("{n}", f"{n:02d}")


ALUR = [
    ("A01", "Masuk, navigasi, dan pencarian"),
    ("A02", "Pendaftaran akun dan verifikasi NIDN"),
    ("A03", "Undangan registrasi"),
    ("A04", "Mengisi pengajuan baru (7 langkah)"),
    ("A05", "Mengirim draf dan siapa yang boleh melihat"),
    ("A06", "Menugaskan penilai"),
    ("A07", "Menilai pengajuan"),
    ("A08", "Keputusan Operator: setujui dan tolak"),
    ("A09", "Revisi dan pemeriksaan ulang"),
    ("A10", "Penarikan pengajuan"),
    ("A11", "Laporan Monev"),
    ("A12", "Luaran Penelitian"),
    ("A13", "Pembuat Formulir, Tahap, dan Periode"),
    ("A14", "Skema, Organisasi, dan Konfigurasi Umum"),
    ("A15", "Pengguna, penilai, dan sesi"),
    ("A16", "Pengumuman dan notifikasi"),
    ("A17", "Statistik, ekspor, dan log audit"),
    ("A18", "Tampilan dan bahasa"),
]

# Usulan pembagian untuk 4 penguji. Satu alur dikerjakan satu orang dari atas ke bawah.
PEMBAGIAN = {
    "QA1": ["A01", "A04", "A05", "A16"],
    "QA2": ["A06", "A07", "A08", "A09", "A18"],
    "QA3": ["A03", "A10", "A11", "A12", "A17"],
    "QA4": ["A02", "A13", "A14", "A15"],
}
# Bila hanya 3 penguji: alur yang pindah dari pembagian 4 orang. Set 4 tidak dipakai.
PEMBAGIAN_TIGA = {"A02": "QA1", "A03": "QA2", "A13": "QA2", "A14": "QA3", "A15": "QA3"}

KASUS: list[dict] = []


def kasus(alur, skenario, akun, lokasi, diketahui, ketika, maka, bdd="", prioritas="Sedang", ubah="Ya"):
    KASUS.append(
        dict(
            alur=alur,
            skenario=skenario,
            akun=akun,
            lokasi=lokasi,
            diketahui=diketahui,
            ketika=ketika,
            maka=maka,
            bdd=bdd,
            prioritas=prioritas,
            ubah=ubah,
        )
    )


# ---------------------------------------------------------------- A01
kasus("A01", "Halaman sambutan tampil sebelum masuk", "(belum masuk)", SAMBUTAN,
      "Anda belum masuk ke SIMPAS",
      "Buka alamat utama situs staging",
      "Halaman sambutan SIMPAS tampil dalam bahasa Indonesia\nDan ada tombol untuk masuk dan mendaftar",
      prioritas="Tinggi", ubah="Tidak")
kasus("A01", "Masuk dengan kata sandi benar", peneliti(1), MASUK,
      "Akun peneliti01 set Anda sudah ada",
      "Isi email dan kata sandi (sama dengan email), lalu tekan tombol masuk",
      "Anda masuk ke Beranda\nDan nama Anda (berawalan QA{k}) terlihat di bagian bawah menu kiri",
      prioritas="Tinggi", ubah="Tidak")
kasus("A01", "Masuk dengan kata sandi salah ditolak", peneliti(1), MASUK,
      "Anda belum masuk",
      "Isi email peneliti01 dengan kata sandi yang salah, lalu tekan tombol masuk",
      "Anda tetap di halaman masuk\nDan muncul pesan bahwa email atau kata sandi salah (bahasa Indonesia)",
      ubah="Tidak")
kasus("A01", "Menu kiri sesuai peran Peneliti", peneliti(1), BERANDA,
      "Anda masuk sebagai Peneliti",
      "Lihat semua menu di sisi kiri",
      "Ada Beranda, Pengumuman, Notifikasi, Daftar Pengajuan, Buat Pengajuan Baru\nDan TIDAK ada menu Manajemen, Laporan, atau Konfigurasi",
      bdd="SM-ACCESS-03", prioritas="Tinggi", ubah="Tidak")
kasus("A01", "Menu kiri sesuai peran Operator", OPERATOR, BERANDA,
      "Anda masuk sebagai Operator",
      "Lihat semua menu di sisi kiri",
      "Ada grup Manajemen (Semua Pengajuan, Periode Pengajuan, Kelola Pengumuman, Penilai, Pengguna & Verifikasi), Laporan, dan Konfigurasi\nDan TIDAK ada menu Buat Pengajuan Baru",
      bdd="SM-ACCESS-04", prioritas="Tinggi", ubah="Tidak")
kasus("A01", "Menu kiri sesuai peran Penilai", PENILAI1, BERANDA,
      "Anda masuk sebagai Penilai internal",
      "Lihat semua menu di sisi kiri",
      "Ada grup Penilaian (Penugasan Penilaian, Riwayat Penilaian)\nDan TIDAK ada menu Manajemen atau Konfigurasi",
      prioritas="Tinggi", ubah="Tidak")
kasus("A01", "Peneliti membuka alamat halaman Operator", peneliti(1), "Ketik alamat langsung di browser\n/admin/submissions",
      "Anda masuk sebagai Peneliti",
      "Ketik alamat /admin/submissions di kolom alamat browser lalu tekan Enter",
      "Muncul halaman \"akses ditolak\" (403) berbahasa Indonesia\nDan tidak ada data pengajuan yang terlihat",
      bdd="SM-ACCESS-03", ubah="Tidak")
kasus("A01", "Alamat yang tidak ada", peneliti(1), "Ketik alamat langsung di browser\n/halaman-tidak-ada",
      "Anda masuk sebagai Peneliti",
      "Ketik alamat /halaman-tidak-ada lalu tekan Enter",
      "Muncul halaman \"tidak ditemukan\" (404) berbahasa Indonesia dengan jalan kembali ke Beranda",
      prioritas="Rendah", ubah="Tidak")
kasus("A01", "Kotak pencarian menemukan halaman", OPERATOR, PALET,
      "Anda masuk sebagai Operator",
      "1. Tekan Ctrl+K (atau klik kotak pencarian di atas)\n2. Ketik \"periode\"",
      "Muncul pilihan halaman Periode Pengajuan\nDan memilihnya membuka halaman itu",
      ubah="Tidak")
kasus("A01", "Kotak pencarian menemukan fitur", OPERATOR, PALET,
      "Anda masuk sebagai Operator",
      "1. Tekan Ctrl+K\n2. Ketik \"undangan\" lalu \"pengumuman\"",
      "Muncul pilihan fitur (mis. membuat undangan, membuat pengumuman), bukan hanya nama halaman\nDan memilihnya membawa ke tempat fitur itu",
      ubah="Tidak")
kasus("A01", "Kotak pencarian menemukan pengajuan dari judul", peneliti(5), PALET,
      "Anda pemilik #5 \"Model Prediksi Banjir Rob Berbasis Pembelajaran Mesin\"",
      "1. Tekan Ctrl+K\n2. Ketik \"banjir rob\"",
      "Pengajuan [QA{k}] Model Prediksi Banjir Rob ... muncul di hasil\nDan mengkliknya membuka detail pengajuan itu",
      bdd="SM-TITLE-05", ubah="Tidak")
kasus("A01", "Pencarian tidak menampilkan pengajuan orang lain", peneliti(5), PALET,
      "Anda peneliti05; pengajuan #6 milik peneliti06",
      "1. Tekan Ctrl+K\n2. Ketik \"hidroponik\" (judul #6)",
      "Pengajuan #6 TIDAK muncul di hasil pencarian Anda",
      bdd="SM-ACCESS-03", ubah="Tidak")
kasus("A01", "Keluar dari aplikasi", peneliti(1), "Tombol keluar di sebelah nama Anda, bagian bawah menu kiri",
      "Anda sedang masuk",
      "Klik tombol keluar, lalu pilih \"Keluar\" di kotak konfirmasi",
      "Anda kembali ke halaman masuk atau sambutan\nDan menekan tombol Back browser tidak membuka data lagi",
      ubah="Tidak")
kasus("A01", "Lupa kata sandi", "(belum masuk)", "Halaman masuk › tautan lupa kata sandi\n/forgot-password",
      "Anda belum masuk",
      "Isi email qa{k}-peneliti01@simpas.test lalu kirim",
      "Muncul pesan bahwa tautan atur ulang sudah dikirim (bahasa Indonesia)\nCatatan: email di staging tidak benar-benar terkirim; pemilik VM bisa melihat isinya di log",
      prioritas="Rendah", ubah="Tidak")

# ---------------------------------------------------------------- A02
kasus("A02", "Daftar akun dengan email ITK", "(belum masuk)", DAFTAR_AKUN,
      "Anda belum masuk\nDan Anda memakai email baru yang belum pernah dipakai, misalnya qa{k}-dosenbaru@itk.ac.id",
      "1. Buka halaman daftar\n2. Isi nama, email @itk.ac.id, NIDN 10 digit yang belum dipakai, organisasi, dan kata sandi\n3. Kirim",
      "Akun terbuat dan Anda diminta memverifikasi email\nCatatan: minta pemilik VM membacakan tautan verifikasi dari log, lalu buka tautan itu",
      bdd="IAM-REG-01, IAM-REG-05, IAM-REG-12", prioritas="Tinggi")
kasus("A02", "Daftar dengan email di luar ITK ditolak", "(belum masuk)", DAFTAR_AKUN,
      "Anda belum masuk",
      "Isi formulir daftar dengan email qa{k}-luar@gmail.com lalu kirim",
      "Pendaftaran ditolak dengan pesan bahwa email harus berakhiran @itk.ac.id",
      bdd="IAM-REG-02", prioritas="Tinggi", ubah="Tidak")
kasus("A02", "Daftar dengan email yang hanya mirip ITK ditolak", "(belum masuk)", DAFTAR_AKUN,
      "Anda belum masuk",
      "Isi formulir daftar dengan email qa{k}@itk.ac.id.palsu.com lalu kirim",
      "Pendaftaran ditolak seperti email luar ITK",
      bdd="IAM-REG-18", prioritas="Rendah", ubah="Tidak")
kasus("A02", "Daftar dengan NIDN yang sudah dipakai ditolak", "(belum masuk)", DAFTAR_AKUN,
      "NIDN akun qa{k}-peneliti01 terlihat di halaman Profil akun itu (minta rekan atau buka sendiri sebelumnya)",
      "Isi formulir daftar dengan email @itk.ac.id baru tetapi NIDN milik peneliti01, lalu kirim",
      "Pendaftaran ditolak dengan pesan bahwa NIDN sudah terdaftar",
      bdd="IAM-REG-04", ubah="Tidak")
kasus("A02", "Akun baru belum bisa membuat pengajuan sebelum profil lengkap", "akun baru dari kasus pertama alur ini", BUAT,
      "Anda masuk dengan akun yang baru didaftarkan dan profilnya belum lengkap",
      "Klik menu \"Buat Pengajuan Baru\"",
      "Anda diarahkan ke halaman Lengkapi Profil yang menjelaskan kenapa harus dilengkapi dulu\nDan menu itu tidak abu-abu atau hilang",
      bdd="IAM-VERIFY-04, IAM-VERIFY-12", prioritas="Tinggi", ubah="Tidak")
kasus("A02", "Melengkapi profil", "akun baru dari kasus pertama alur ini", "Halaman Lengkapi Profil\n/profile/complete",
      "Anda berada di halaman Lengkapi Profil",
      "Isi semua isian wajib, unggah foto profil, lalu tekan \"Simpan Profil\"",
      "Profil tersimpan\nDan menu \"Buat Pengajuan Baru\" sekarang membuka halaman pengajuan",
      bdd="IAM-VERIFY-05, IAM-VERIFY-06")
kasus("A02", "Operator mendapat notifikasi pendaftar baru", OPERATOR, NOTIFIKASI,
      "Akun baru dari kasus pertama alur ini sudah mendaftar",
      "Buka halaman Notifikasi",
      "Ada notifikasi bahwa pendaftar baru menunggu verifikasi NIDN",
      bdd="IAM-EVENT-01", ubah="Tidak")
kasus("A02", "Menolak verifikasi tanpa alasan tidak bisa", OPERATOR, VERIFIKASI,
      "Akun baru Anda ada di antrean verifikasi",
      "Klik \"Tolak\" pada akun itu, kosongkan alasan, lalu coba simpan",
      "Penolakan tidak bisa disimpan sebelum alasan diisi",
      bdd="IAM-VERIFY-08", ubah="Tidak")
kasus("A02", "Menolak verifikasi dengan alasan", OPERATOR, VERIFIKASI,
      "Akun baru Anda ada di antrean verifikasi",
      "Klik \"Tolak\" pada akun itu, isi alasan \"NIDN tidak cocok dengan PDDikti\", lalu tekan \"Tolak Pendaftaran\"",
      "Akun pindah ke tab \"Ditolak\" beserta alasannya",
      bdd="IAM-VERIFY-07", prioritas="Tinggi")
kasus("A02", "Akun yang ditolak tidak bisa mengirim pengajuan", "akun baru dari kasus pertama alur ini", BUAT,
      "Verifikasi NIDN akun ini sudah ditolak",
      "Buat draf pengajuan dan coba kirim di langkah terakhir",
      "Pengiriman ditolak dengan pesan bahwa NIDN belum terverifikasi\nDan alasan penolakan terlihat oleh pemilik akun",
      bdd="IAM-VERIFY-09, IAM-VERIFY-02")
kasus("A02", "Verifikasi NIDN peneliti yang menunggu", OPERATOR, VERIFIKASI,
      "Akun qa{k}-peneliti-baru ada di antrean verifikasi",
      "Klik \"Verifikasi\" pada akun QA{k} peneliti-baru",
      "Akun hilang dari antrean menunggu\nDan pemilik akun mendapat notifikasi bahwa akunnya terverifikasi",
      bdd="IAM-VERIFY-03, IAM-EVENT-02", prioritas="Tinggi")
kasus("A02", "Peneliti yang baru diverifikasi bisa mengirim", "qa{k}-peneliti-baru@simpas.test", BUAT,
      "NIDN akun Anda baru saja diverifikasi",
      "Buat pengajuan baru sampai langkah 7 \"Periksa & Kirim\", lalu tekan \"Kirim Pengajuan\"",
      "Pengajuan terkirim dengan status Menunggu Penilaian",
      bdd="IAM-VERIFY-03")
kasus("A02", "Koreksi NIDN akun yang ditolak", OPERATOR, VERIFIKASI + "\n(tab \"Ditolak\")",
      "Akun qa{k}-peneliti-ditolak ada di tab Ditolak",
      "Klik \"Koreksi NIDN\", isi NIDN baru 10 digit yang belum dipakai, tekan \"Simpan Koreksi\", lalu \"Verifikasi\"",
      "NIDN berubah dan akun menjadi terverifikasi",
      bdd="IAM-VERIFY-13, IAM-VERIFY-10")
kasus("A02", "Koreksi ke NIDN yang sudah dipakai ditolak", OPERATOR, VERIFIKASI + "\n(tab \"Ditolak\")",
      "Akun baru Anda (dari awal alur ini) ada di tab Ditolak",
      "Klik \"Koreksi NIDN\" dan isi NIDN milik qa{k}-peneliti01, lalu simpan",
      "Koreksi ditolak dengan pesan bahwa NIDN sudah dipakai akun lain",
      bdd="IAM-VERIFY-14", ubah="Tidak")
kasus("A02", "Akun yang menunggu verifikasi tetap bisa masuk dan menyiapkan draf", "akun baru lain (daftar ulang dengan email @itk.ac.id baru)", BUAT,
      "Akun ini masih menunggu verifikasi NIDN",
      "Masuk, lengkapi profil, lalu buat draf pengajuan sampai langkah 2",
      "Anda bisa masuk dan draf tersimpan\nDan di langkah 7 ada keterangan bahwa pengiriman menunggu verifikasi",
      bdd="IAM-VERIFY-01, IAM-VERIFY-06, IAM-VERIFY-11", prioritas="Rendah")

# ---------------------------------------------------------------- A03
kasus("A03", "Operator membuat undangan registrasi", OPERATOR, UNDANGAN,
      "Anda masuk sebagai Operator",
      "1. Tekan \"Buat Undangan Registrasi\"\n2. Pilih organisasi tujuan, isi \"Maks. Pemakaian\" = 2 dan \"Berlaku Sampai\" besok\n3. Simpan",
      "Muncul kotak \"Undangan diterbitkan\" berisi tautan undangan\nDan undangan tampil di daftar",
      bdd="IAM-INVITE-01, IAM-INVITE-09", prioritas="Tinggi")
kasus("A03", "Tautan undangan membuka formulir daftar", "(belum masuk; jendela samaran/incognito)", "Tautan undangan yang disalin\n/register/invitation/…",
      "Anda menyalin tautan undangan dari kasus sebelumnya",
      "Buka tautan itu di jendela samaran (incognito)",
      "Formulir daftar tampil dengan organisasi yang sudah terisi dari undangan",
      bdd="IAM-INVITE-07", prioritas="Tinggi", ubah="Tidak")
kasus("A03", "Daftar lewat undangan boleh memakai email luar ITK", "(belum masuk; jendela samaran)", "Tautan undangan\n/register/invitation/…",
      "Formulir daftar undangan sedang terbuka",
      "Isi dengan email qa{k}-mitra@gmail.com lalu kirim",
      "Akun terbuat tanpa diminta NIDN\nDan akun ini TIDAK muncul di Antrian Verifikasi NIDN",
      bdd="IAM-REG-03, IAM-REG-08, IAM-REG-14")
kasus("A03", "Undangan dipakai sampai kuota habis", "(belum masuk; jendela samaran)", "Tautan undangan\n/register/invitation/…",
      "Undangan berkuota 2 sudah dipakai 1 kali",
      "1. Daftar lagi lewat tautan yang sama dengan email lain (berhasil)\n2. Coba buka tautan itu untuk ketiga kalinya",
      "Pendaftaran kedua berhasil\nDan pembukaan ketiga mendarat di halaman \"undangan tidak berlaku\"",
      bdd="IAM-REG-09, IAM-REG-06, IAM-INVITE-08")
kasus("A03", "Operator mencabut undangan", OPERATOR, UNDANGAN,
      "Buat satu undangan baru berkuota 5",
      "Klik \"Cabut\" pada undangan itu lalu konfirmasi",
      "Undangan tetap terlihat di daftar dengan tanda dicabut\nDan membuka tautannya mendarat di halaman \"undangan tidak berlaku\"",
      bdd="IAM-INVITE-03, IAM-INVITE-06, IAM-REG-16")
kasus("A03", "Mencabut undangan tidak menghapus akun yang sudah terdaftar", OPERATOR, PENGGUNA,
      "Undangan pertama alur ini sudah dipakai dan kemudian dicabut (cabut sekarang bila belum)",
      "Cari akun qa{k}-mitra di daftar pengguna",
      "Akun masih ada dan masih bisa masuk",
      bdd="IAM-INVITE-04", ubah="Tidak")
kasus("A03", "Peneliti tidak bisa membuka halaman undangan", peneliti(1), "Ketik alamat langsung\n/admin/invitations",
      "Anda masuk sebagai Peneliti",
      "Buka alamat /admin/invitations",
      "Akses ditolak (403)",
      bdd="IAM-INVITE-02, IAM-INVITE-05", ubah="Tidak")

# ---------------------------------------------------------------- A04
kasus("A04", "Melanjutkan draf dari langkah terakhir", peneliti(1), DAFTAR,
      "#1 milik Anda adalah draf yang baru sampai langkah 2",
      "Klik judul #1 lalu \"Lanjutkan draf\"",
      "Wizard terbuka di langkah 2 \"Informasi Dasar\"\nDan isian yang sudah ada masih terisi",
      bdd="SM-WIZARD-07, SM-WIZARD-02", prioritas="Tinggi", ubah="Tidak")
kasus("A04", "Pindah langkah tidak menghilangkan isian", peneliti(1), WIZARD,
      "Draf #1 terbuka di wizard",
      "1. Ubah Abstrak di langkah 2, tekan \"Lanjut\"\n2. Tekan \"Sebelumnya\" untuk kembali ke langkah 2",
      "Abstrak yang baru masih ada",
      bdd="SM-WIZARD-02")
kasus("A04", "Anggaran terkunci sebelum skema dipilih", peneliti(1), WIZARD,
      "Draf #1 belum memilih skema",
      "Klik langkah 5 \"Rencana Anggaran\" di deretan langkah atas",
      "Langkah 5 belum bisa dipakai, dengan keterangan bahwa skema harus dipilih dulu dan tombol menuju langkah 3",
      bdd="SM-WIZARD-04")
kasus("A04", "Pilihan TKT mengikuti skema", peneliti(1), WIZARD + "\n(langkah 3 \"Skema, TKT & Target Luaran\")",
      "Draf #1 ada di langkah 3",
      "1. Pilih skema Penelitian Dasar, lihat pilihan TKT\n2. Ganti ke Penelitian Terapan, lihat lagi",
      "Penelitian Dasar hanya menawarkan TKT 1 sampai 3\nDan Penelitian Terapan hanya TKT 4 sampai 6",
      bdd="SCH-RULE-10, SCH-RULE-08", prioritas="Tinggi")
kasus("A04", "Menambah target luaran", peneliti(1), WIZARD + "\n(langkah 3)",
      "Skema Penelitian Dasar sudah dipilih",
      "Tekan \"Tambah Target\", pilih Artikel Jurnal, isi jumlah 1, simpan",
      "Target luaran muncul di daftar dan bisa dihapus lagi",
      bdd="SM-TARGET-01, SM-TARGET-05, SM-TARGET-10")
kasus("A04", "Menambah anggota dosen", peneliti(1), WIZARD + "\n(langkah 4 \"Anggota Penelitian\")",
      "Draf #1 di langkah 4",
      "Cari \"QA{k} Peneliti 02\" (atau nama peneliti02), pilih, lalu \"Tambah\"",
      "Peneliti02 muncul di \"Anggota saat ini\"",
      bdd="SM-MEMBER-02", prioritas="Tinggi")
kasus("A04", "Mengeluarkan anggota", peneliti(1), WIZARD + "\n(langkah 4)",
      "Peneliti02 sudah menjadi anggota",
      "Tekan \"Hapus\" pada peneliti02 lalu konfirmasi",
      "Peneliti02 hilang dari daftar anggota\nDan diri Anda sendiri (Ketua) tidak punya tombol hapus",
      bdd="SM-MEMBER-04, SM-MEMBER-06")
kasus("A04", "Mahasiswa dibatasi 3 orang", peneliti(1), WIZARD + "\n(langkah 4, kartu mahasiswa)",
      "Draf #1 di langkah 4",
      "Tekan \"Tambah Mahasiswa\" dan isi 4 mahasiswa dengan NIM berbeda",
      "Mahasiswa ke-1 sampai ke-3 tersimpan\nDan mahasiswa ke-4 ditolak dengan pesan batas skema (maksimal 3)",
      bdd="SM-STUDENT-01, SM-STUDENT-02")
kasus("A04", "NIM yang sama tidak bisa dicatat dua kali", peneliti(1), WIZARD + "\n(langkah 4, kartu mahasiswa)",
      "Sudah ada mahasiswa dengan NIM 11221001",
      "Hapus satu mahasiswa, lalu tambah mahasiswa baru dengan NIM 11221001 lagi",
      "Ditolak dengan pesan bahwa NIM sudah tercatat di pengajuan ini",
      bdd="SM-STUDENT-04, SM-STUDENT-06")
kasus("A04", "Menambah dan mencabut mitra", peneliti(1), WIZARD + "\n(langkah 4, kartu mitra)",
      "Draf #1 di langkah 4",
      "1. \"Tambah Mitra\" PT Contoh Mitra\n2. Tambah lagi mitra dengan nama institusi yang sama\n3. \"Cabut\" mitra pertama",
      "Mitra pertama tersimpan, mitra kedua yang sama ditolak, lalu pencabutan berhasil",
      bdd="SM-PARTNER-01, SM-PARTNER-02, SM-PARTNER-05")
kasus("A04", "Item anggaran dengan volume nol ditolak", peneliti(1), WIZARD + "\n(langkah 5 \"Rencana Anggaran\")",
      "Skema Penelitian Dasar (pagu Rp75.000.000) sudah dipilih",
      "\"Tambah Item\" dengan volume 0 dan harga satuan 100000, simpan",
      "Ditolak dengan pesan bahwa volume harus lebih dari nol",
      bdd="BUD-ITEM-01", ubah="Tidak")
kasus("A04", "Total anggaran melebihi pagu ditolak", peneliti(1), WIZARD + "\n(langkah 5)",
      "Skema Penelitian Dasar (pagu Rp75.000.000)",
      "\"Tambah Item\" volume 1, harga satuan 80000000, simpan",
      "Ditolak dengan pesan bahwa total melebihi batas skema",
      bdd="BUD-LIMIT-01", prioritas="Tinggi", ubah="Tidak")
kasus("A04", "Item anggaran sah menaikkan total", peneliti(1), WIZARD + "\n(langkah 5)",
      "Belum ada item anggaran",
      "1. \"Tambah Item\" volume 2, harga 5000000\n2. Ubah item itu menjadi volume 3\n3. Tambah item kedua lalu hapus",
      "Total Anggaran menjadi Rp15.000.000 setelah diubah\nDan menghapus item kedua menurunkan total kembali",
      bdd="BUD-ITEM-02, BUD-ITEM-05, BUD-ITEM-07, BUD-LIMIT-02", prioritas="Tinggi")
kasus("A04", "Langkah 7 menyebut isian yang belum lengkap", peneliti(1), WIZARD + "\n(langkah 7 \"Periksa & Kirim\")",
      "Berkas Proposal belum diunggah di langkah 6",
      "Buka langkah 7 \"Periksa & Kirim\"",
      "Halaman menyebut Berkas Proposal masih kurang, dengan tombol \"Ke Langkah 6\"\nDan tombol \"Kirim Pengajuan\" belum bisa dipakai",
      bdd="SM-WIZARD-06, SM-WIZARD-08, SM-SUBMIT-07, FE-TEMPORAL-09", prioritas="Tinggi", ubah="Tidak")
kasus("A04", "Unggah proposal lalu kirim", peneliti(1), WIZARD + "\n(langkah 6 \"Unggah Berkas\", lalu langkah 7)",
      "Siapkan satu berkas PDF kecil (di bawah 5 MB)",
      "1. Di langkah 6 unggah PDF sebagai Berkas Proposal\n2. Buka langkah 7, tekan \"Kirim Pengajuan\" dan konfirmasi",
      "Status #1 menjadi Menunggu Penilaian\nDan isian tidak bisa disunting lagi",
      bdd="SM-SUBMIT-01, SM-SUBMIT-08, SM-STATE-01", prioritas="Tinggi")
kasus("A04", "Pengajuan kedua ke skema yang sama ditolak", peneliti(1), BUAT,
      "#1 milik Anda (skema Penelitian Dasar) sudah terkirim",
      "Buat pengajuan baru, pilih skema Penelitian Dasar lagi, isi lengkap, lalu coba kirim",
      "Pengiriman ditolak dengan pesan yang menyebut pengajuan aktif Anda yang sudah ada\nDan draf tetap bisa disunting",
      bdd="SM-SUBMIT-02, SM-SUBMIT-15, SM-SUBMIT-16", prioritas="Tinggi")
kasus("A04", "Draf ke skema lain tetap boleh", peneliti(1), WIZARD + "\n(langkah 3)",
      "Draf dari kasus sebelumnya masih ada",
      "Ganti skema draf itu ke Penelitian Terapan, lengkapi, lalu kirim",
      "Pengiriman berhasil (skema berbeda)",
      bdd="SM-SUBMIT-03, SM-SUBMIT-04")
kasus("A04", "Melompat mundur beberapa langkah", peneliti(1), WIZARD,
      "Draf baru (buat lagi bila perlu) sedang di langkah 5",
      "Klik langkah 2 langsung di deretan langkah atas",
      "Wizard membuka langkah 2 tanpa kehilangan isian langkah 3 sampai 5",
      bdd="SM-WIZARD-10", prioritas="Rendah", ubah="Tidak")

# ---------------------------------------------------------------- A05
kasus("A05", "Mengirim draf yang sudah lengkap", peneliti(2), WIZARD + "\n(langkah 7)",
      "#2 milik Anda adalah draf lengkap yang siap dikirim",
      "Buka #2, lanjutkan ke langkah 7, tekan \"Kirim Pengajuan\" dan konfirmasi",
      "Status #2 menjadi Menunggu Penilaian\nDan riwayat di halaman detail mencatat pengiriman",
      bdd="SM-SUBMIT-09, SM-STATE-01", prioritas="Tinggi")
kasus("A05", "Pengajuan terkirim terkunci untuk disunting", peneliti(2), DETAIL,
      "#2 sudah terkirim",
      "Buka detail #2 dan cari tombol untuk mengubah isian",
      "Tidak ada tombol untuk mengubah isian atau anggaran",
      bdd="SM-STATE-04", ubah="Tidak")
kasus("A05", "Peneliti lain tidak bisa membuka pengajuan orang lain", peneliti(3), "Ketik alamat detail #2\n/user/submissions/…",
      "Salin alamat detail #2 saat masuk sebagai peneliti02",
      "Masuk sebagai peneliti03 lalu buka alamat yang disalin",
      "Akses ditolak (403)",
      bdd="SM-ACCESS-03", prioritas="Tinggi", ubah="Tidak")
kasus("A05", "Anggota tim bisa melihat pengajuan yang ia ikuti", ANGGOTA, DAFTAR,
      "Akun anggota adalah anggota tim #9 sampai #12",
      "Buka Daftar Pengajuan lalu buka #9",
      "#9 sampai #12 tampil di daftar\nDan detail #9 terbuka dengan peran Anda sebagai anggota",
      bdd="SM-ACCESS-02", prioritas="Tinggi", ubah="Tidak")
kasus("A05", "Anggota biasa hanya bisa membaca anggaran", ANGGOTA, DETAIL + " (#9)",
      "Anda anggota biasa (bukan ketua) di #9",
      "Buka bagian Anggaran di detail #9",
      "Anggaran terlihat\nDan tidak ada tombol tambah, ubah, atau hapus item",
      bdd="BUD-WHO-03, BUD-ITEM-10", ubah="Tidak")
kasus("A05", "Penilai yang tidak ditugaskan tidak bisa membuka pengajuan", PENILAI_LUAR, "Ketik alamat langsung\n/reviewer/assignments/…/evaluation",
      "Penilai-luar tidak ditugaskan ke #2",
      "1. Masuk sebagai peneliti02, buka detail #2, catat ANGKA di akhir alamat /user/submissions/ANGKA\n2. Masuk sebagai Penilai Luar dan buka /reviewer/assignments/ANGKA/evaluation",
      "Akses ditolak (403)",
      bdd="SM-ACCESS-06", ubah="Tidak")
kasus("A05", "Pengajuan yang Ditolak tidak menghalangi pengajuan baru", peneliti(11), BUAT,
      "#11 milik Anda Ditolak",
      "Buat pengajuan baru dengan skema yang sama seperti #11, isi lengkap, lalu kirim",
      "Pengiriman berhasil karena pengajuan lama sudah selesai (Ditolak)",
      bdd="SM-SUBMIT-05, SM-WIZARD-12")
kasus("A05", "Penyaring dan pencarian Daftar Pengajuan", ANGGOTA, DAFTAR,
      "Anda melihat beberapa pengajuan",
      "Pakai penyaring status dan kotak cari di atas tabel",
      "Daftar menyempit sesuai penyaring\nDan hanya pengajuan yang boleh Anda lihat yang muncul",
      prioritas="Rendah", ubah="Tidak")

# ---------------------------------------------------------------- A06
kasus("A06", "Penilai pertama belum memindahkan status", OPERATOR, SEMUA_DETAIL + " (#3)",
      "#3 Menunggu Penilaian tanpa penilai; skema butuh minimal 2 penilai",
      "Di bagian \"Aksi Operator\" tekan \"Tugaskan Penilai\", pilih QA{k} Penilai 1, simpan",
      "Penilai 1 tercatat\nDan status #3 TETAP Menunggu Penilaian",
      bdd="REV-ASSIGN-01, REV-ASSIGN-02", prioritas="Tinggi")
kasus("A06", "Penilai yang sama tidak bisa ditugaskan dua kali", OPERATOR, SEMUA_DETAIL + " (#3)",
      "Penilai 1 sudah ditugaskan ke #3",
      "Buka \"Tugaskan Penilai\" lagi dan cari Penilai 1",
      "Penilai 1 tidak bisa dipilih lagi (atau ditolak saat disimpan)",
      bdd="REV-ASSIGN-05", ubah="Tidak")
kasus("A06", "Beban penilai berlebih memberi peringatan, bukan larangan", OPERATOR, SEMUA_DETAIL + " (#3)",
      "Akun latar-penilai1 sudah memegang sekitar 20 penilaian (batas skema 10)",
      "Buka \"Tugaskan Penilai\" dan pilih latar-penilai1, tetapi JANGAN simpan; lalu batalkan",
      "Muncul peringatan beban penilai melebihi batas\nDan tombol simpan tetap bisa dipakai (hanya peringatan)",
      bdd="REV-ASSIGN-06", ubah="Tidak")
kasus("A06", "Penilai terakhir memindahkan ke Sedang Dinilai", OPERATOR, SEMUA_DETAIL + " (#3)",
      "#3 punya 1 dari 2 penilai",
      "\"Tugaskan Penilai\", pilih QA{k} Penilai Luar (penilai eksternal), simpan",
      "Status #3 menjadi Sedang Dinilai",
      bdd="REV-ASSIGN-03", prioritas="Tinggi")
kasus("A06", "Penilai yang ditugaskan mendapat notifikasi", PENILAI_LUAR, NOTIFIKASI,
      "Penilai Luar baru ditugaskan ke #3",
      "Buka Notifikasi, lalu klik notifikasi penugasan",
      "Ada notifikasi penugasan #3\nDan mengkliknya membuka penugasan itu",
      bdd="REV-ASSIGN-09, NOT-INAPP-05", ubah="Tidak")
kasus("A06", "Penugasan muncul di Penugasan Penilaian", PENILAI_LUAR, TUGAS,
      "Anda ditugaskan ke #3",
      "Buka tab \"Perlu Tindakan\"",
      "Kartu #3 tampil dengan tombol \"Mulai Evaluasi\"",
      bdd="SM-ACCESS-05", ubah="Tidak")
kasus("A06", "Mengganti penilai", OPERATOR, SEMUA_DETAIL + " (#4)",
      "#4 punya 1 penilai (Penilai 1)",
      "Di daftar penilai #4, tekan \"Ganti\" pada Penilai 1, pilih QA{k} Penilai Luar, isi catatan \"uji ganti penilai\", simpan",
      "Penilai 1 ditandai diganti (tidak terhapus) dan Penilai Luar tercatat sebagai penilai aktif\nDan Log Audit pengajuan mencatat catatan penggantian",
      bdd="REV-REASSIGN-01, REV-REASSIGN-03, REV-REASSIGN-05")
kasus("A06", "Penyaring \"Menunggu keputusan\"", OPERATOR, SEMUA,
      "#6 di set Anda siap diputuskan",
      "Pakai penyaring \"Menunggu keputusan\" di Semua Pengajuan",
      "Hanya pengajuan yang siap diputuskan yang tampil (#6 set Anda ada di sana; #5 dan #7 tidak)",
      bdd="REV-OUTCOME-21", ubah="Tidak")

# ---------------------------------------------------------------- A07
kasus("A07", "Menyimpan draf penilaian", PENILAI2, EVAL + " (#5)",
      "#5 Sedang Dinilai; Penilai 1 sudah mengirim lembarnya, Anda belum",
      "Tekan \"Mulai Evaluasi\" pada #5, isi sebagian nilai, tekan \"Simpan Draf\", lalu muat ulang halaman",
      "Nilai yang diisi masih ada dan masih bisa diubah",
      bdd="REV-EVAL-01", prioritas="Tinggi")
kasus("A07", "Peneliti tidak melihat nilai selama dinilai", peneliti(5), DETAIL + " (#5)",
      "#5 masih Sedang Dinilai",
      "Buka detail #5",
      "Tidak ada nilai penilai yang terlihat",
      bdd="REV-VIS-04", prioritas="Tinggi", ubah="Tidak")
kasus("A07", "\"Selesai menilai\" belum bisa sebelum lembar dikirim", PENILAI2, EVAL + " (#5)",
      "Lembar Anda di #5 masih draf",
      "Cari tombol \"Selesai menilai\"",
      "Tombol belum bisa dipakai, dengan keterangan bahwa lembar harus dikirim dulu",
      bdd="REV-OUTCOME-14", ubah="Tidak")
kasus("A07", "Mengirim lembar penilaian", PENILAI2, EVAL + " (#5)",
      "Lembar Anda di #5 masih draf",
      "Lengkapi semua nilai lalu tekan \"Kirim Evaluasi\" dan konfirmasi",
      "Lembar terkirim dan tidak bisa diubah lagi",
      bdd="REV-EVAL-02, REV-EVAL-03", prioritas="Tinggi")
kasus("A07", "Membuka catatan untuk peneliti", PENILAI2, EVAL + " (#5)",
      "Lembar Anda sudah terkirim, Anda belum menekan \"Selesai menilai\"",
      "Tulis catatan baru \"Mohon lampirkan data curah hujan 5 tahun\" lalu kirim",
      "Catatan tampil sebagai catatan terbuka",
      bdd="REV-DISCUSS-01, REV-OUTCOME-13")
kasus("A07", "Menandai catatan selesai", PENILAI2, EVAL + " (#5)",
      "Catatan Anda dari kasus sebelumnya masih terbuka",
      "Tekan \"Tandai Selesai\" pada catatan itu",
      "Catatan berubah menjadi selesai\nDan status #5 tidak berubah karena tindakan ini",
      bdd="REV-DISCUSS-03, REV-OUTCOME-05")
kasus("A07", "Selesai menilai oleh penilai pertama", PENILAI2, EVAL + " (#5)",
      "Lembar terkirim dan tidak ada catatan terbuka dari Anda",
      "Tekan \"Selesai menilai\" lalu konfirmasi",
      "Penugasan pindah ke tab \"Selesai\"\nDan status #5 tetap Sedang Dinilai karena Penilai 1 belum selesai",
      bdd="REV-OUTCOME-02")
kasus("A07", "Nilai rekan masih tertutup sebelum semua selesai", PENILAI2, "Penugasan Penilaian › tab \"Selesai\" › #5 › \"Lihat\"",
      "Penilai 1 belum menekan \"Selesai menilai\" di #5",
      "Buka ringkasan penilaian #5",
      "Nilai Penilai 1 belum terlihat",
      bdd="REV-VIS-03, REV-EVAL-04", ubah="Tidak")
kasus("A07", "Setelah selesai, catatan baru tidak bisa dibuka", PENILAI2, EVAL + " (#5)",
      "Anda sudah menekan \"Selesai menilai\" di #5",
      "Buka lagi halaman evaluasi #5 dan cari tempat menulis catatan baru",
      "Tidak ada tempat menulis catatan baru (atau ditolak saat dikirim)",
      bdd="REV-OUTCOME-15", ubah="Tidak")
kasus("A07", "Penilai terakhir selesai: siap diputuskan", PENILAI1, EVAL + " (#5)",
      "Lembar Penilai 1 di #5 sudah terkirim sejak awal",
      "Tekan \"Selesai menilai\" pada #5",
      "#5 menjadi siap diputuskan (muncul di penyaring \"Menunggu keputusan\" Operator)",
      bdd="REV-OUTCOME-01", prioritas="Tinggi")
kasus("A07", "Operator diberi tahu pengajuan siap diputuskan", OPERATOR, NOTIFIKASI,
      "#5 baru saja siap diputuskan",
      "Buka Notifikasi",
      "Ada notifikasi bahwa #5 siap diputuskan",
      bdd="REV-OUTCOME-20", ubah="Tidak")
kasus("A07", "Penilai internal melihat nilai rekan setelah semua selesai", PENILAI1, "Penugasan Penilaian › tab \"Selesai\" › #5 › \"Lihat\"",
      "Kedua penilai #5 sudah selesai",
      "Buka ringkasan penilaian #5",
      "Nilai Penilai 2 terlihat",
      bdd="REV-VIS-01, REV-EVAL-05", ubah="Tidak")
kasus("A07", "Penilai eksternal tidak pernah melihat nilai rekan", PENILAI_LUAR, EVAL + " (#3)",
      "Penilai Luar ditugaskan ke #3 (alur A06 di set Anda). Bila A06 belum dikerjakan di set Anda, minta Operator set Anda menugaskan Penilai 1 dan Penilai Luar ke #3 dulu",
      "1. Penilai 1 dan Penilai Luar masing-masing mengirim lembar dan menekan \"Selesai menilai\"\n2. Masuk sebagai Penilai Luar dan buka ringkasan #3",
      "Nilai Penilai 1 tidak pernah terlihat oleh Penilai Luar",
      bdd="REV-VIS-02")
kasus("A07", "Riwayat Penilaian", PENILAI1, RIWAYAT_NILAI,
      "Anda sudah menyelesaikan beberapa penilaian",
      "Buka Riwayat Penilaian",
      "Penilaian yang sudah selesai tampil beserta hasilnya",
      prioritas="Rendah", ubah="Tidak")

# ---------------------------------------------------------------- A08
kasus("A08", "Setujui dan Tolak tidak bisa selama putaran berjalan", OPERATOR, SEMUA_DETAIL + " (#4)",
      "#4 masih menunggu penilai atau sedang dinilai",
      "Lihat tombol \"Setujui\" dan \"Tolak Manual\" di Aksi Operator",
      "Kedua tombol belum bisa dipakai, dengan keterangan alasannya",
      bdd="REV-OUTCOME-12, REV-OUTCOME-10", ubah="Tidak")
kasus("A08", "Menyetujui pengajuan yang siap diputuskan", OPERATOR, SEMUA_DETAIL + " (#6)",
      "#6 siap diputuskan (kedua penilai selesai, tidak ada catatan terbuka)",
      "Tekan \"Setujui\" lalu konfirmasi",
      "Status #6 menjadi Disetujui",
      bdd="REV-OUTCOME-11", prioritas="Tinggi")
kasus("A08", "Peneliti diberi tahu dan melihat nilai setelah disetujui", peneliti(6), DETAIL + " (#6)",
      "#6 baru disetujui",
      "Buka Notifikasi, lalu buka detail #6",
      "Ada notifikasi pengajuan disetujui\nDan nilai serta catatan penilai sekarang terlihat",
      bdd="REV-VIS-05, NOT-CHANNEL-01", prioritas="Tinggi", ubah="Tidak")
kasus("A08", "Mencetak Surat Keterangan Diterima", peneliti(6), DETAIL + " (#6)",
      "#6 Disetujui",
      "Tekan \"Cetak Surat Keterangan Diterima\" (atau tombol cetak yang tersedia)",
      "Dokumen terunduh atau terbuka dan berisi judul #6",
      bdd="RPT-PRINT-08, RPT-PRINT-04, RPT-PRINT-01")
kasus("A08", "Surat Keterangan Diterima tidak ditawarkan sebelum disetujui", peneliti(5), DETAIL + " (#5)",
      "#5 belum disetujui",
      "Cari tombol cetak surat keterangan di detail #5",
      "Tombol cetak surat keterangan tidak ada",
      bdd="RPT-PRINT-07, RPT-PRINT-03", ubah="Tidak")
kasus("A08", "Mengaktifkan siklus Monev untuk pengajuan yang baru disetujui", OPERATOR, KELOLA_MONEV_DETAIL + " (#6)",
      "#6 baru disetujui",
      "Tekan \"Aktifkan Siklus Monev 1\" (nama siklus bisa sedikit berbeda) lalu konfirmasi",
      "Siklus Monev 1 #6 aktif\nDan peneliti06 mendapat notifikasi, serta menu Daftar Monev muncul di akunnya",
      bdd="MON-CYCLE-02, MON-REVIEW-10")
kasus("A08", "Siapkan #4 sampai siap diputuskan", OPERATOR + ", lalu dua penilai aktif #4", SEMUA_DETAIL + " (#4), lalu " + EVAL,
      "#4 punya 1 dari 2 penilai",
      "1. Operator menugaskan Penilai 2 ke #4\n2. Kedua penilai aktif #4 (Penilai Luar dari A06 dan Penilai 2; bila A06 belum dijalankan: Penilai 1 dan Penilai 2) mengirim lembar dan menekan \"Selesai menilai\" tanpa catatan",
      "#4 siap diputuskan",
      bdd="REV-ASSIGN-03, REV-OUTCOME-01")
kasus("A08", "Menolak dengan alasan", OPERATOR, SEMUA_DETAIL + " (#4)",
      "#4 siap diputuskan",
      "Tekan \"Tolak Manual\", isi \"Penjelasan Detail\" dengan \"Ruang lingkup tidak sesuai skema\", lalu \"Tolak Pengajuan\"",
      "Status #4 menjadi Ditolak\nDan alasan tercatat di riwayat",
      bdd="REV-OUTCOME-07, REV-OUTCOME-08", prioritas="Tinggi")
kasus("A08", "Peneliti melihat nilai setelah ditolak", peneliti(4), DETAIL + " (#4)",
      "#4 baru ditolak",
      "Buka detail #4",
      "Status Ditolak, alasan, dan nilai penilai terlihat",
      bdd="REV-VIS-06", ubah="Tidak")
kasus("A08", "Pengajuan Ditolak terkunci", peneliti(11), DETAIL + " (#11)",
      "#11 Ditolak",
      "Cari tombol untuk mengubah isian, anggaran, anggota, atau target luaran",
      "Tidak ada yang bisa diubah",
      bdd="SM-STATE-04, BUD-LOCK-01, SM-MEMBER-08, SM-TARGET-07", ubah="Tidak")

# ---------------------------------------------------------------- A09
kasus("A09", "Peneliti melihat catatan penilai", peneliti(7), REVISI + " (#7)",
      "#7 Perlu Revisi; Penilai 1 membuka catatan tentang metode pengambilan sampel",
      "Buka detail #7 lalu bagian Catatan Penilai",
      "Catatan \"Perjelas metode pengambilan sampel ...\" terlihat",
      bdd="REV-OUTCOME-04", prioritas="Tinggi", ubah="Tidak")
kasus("A09", "Peneliti membalas catatan", peneliti(7), REVISI + " (#7)",
      "Catatan Penilai 1 masih terbuka",
      "Tulis balasan \"Metode sudah diperjelas di bab 3\" lalu \"Kirim Balasan\"",
      "Balasan muncul di bawah catatan yang sama",
      bdd="REV-DISCUSS-02")
kasus("A09", "Peneliti tidak bisa menutup catatan sendiri", peneliti(7), REVISI + " (#7)",
      "Catatan Penilai 1 masih terbuka",
      "Cari tombol \"Tandai Selesai\" pada catatan",
      "Tombol itu tidak ada untuk peneliti",
      bdd="REV-DISCUSS-04", ubah="Tidak")
kasus("A09", "Mengubah isian dan anggaran saat revisi", peneliti(7), REVISI + " (#7)",
      "#7 Perlu Revisi",
      "Tekan \"Ubah Pengajuan\", ubah Abstrak dan satu item anggaran, simpan",
      "Perubahan tersimpan\nDan Log Audit pengajuan mencatat nilai anggaran sebelum dan sesudah",
      bdd="BUD-LOCK-02, BUD-AUDIT-01")
kasus("A09", "Mengirim ulang revisi", peneliti(7), REVISI + " (#7)",
      "Perubahan revisi sudah tersimpan",
      "Tekan \"Kirim Ulang\", lalu \"Kirim Ulang Revisi\" di konfirmasi",
      "Status #7 menjadi Sedang Dinilai (putaran pemeriksaan)\nDan Penilai 1 mendapat penugasan pemeriksaan",
      bdd="SM-SUBMIT-12, SM-STATE-03, REV-OUTCOME-16", prioritas="Tinggi")
kasus("A09", "Penilai menutup catatan lalu selesai memeriksa", PENILAI1, EVAL + " (#7)",
      "#7 dalam putaran pemeriksaan",
      "Tekan \"Tandai Selesai\" pada catatan, lalu \"Selesai memeriksa\"",
      "#7 siap diputuskan Operator",
      bdd="REV-OUTCOME-05", prioritas="Tinggi")
kasus("A09", "Operator menyetujui hasil revisi", OPERATOR, SEMUA_DETAIL + " (#7)",
      "#7 siap diputuskan",
      "Tekan \"Setujui\" lalu konfirmasi",
      "Status #7 menjadi Disetujui",
      bdd="REV-OUTCOME-11")
kasus("A09", "Selesai memeriksa dengan catatan masih terbuka kembali ke revisi", PENILAI1, EVAL + " (#8)",
      "#8 dalam putaran pemeriksaan; catatan Anda masih terbuka",
      "Jangan menutup catatan; langsung tekan \"Selesai memeriksa\"",
      "Status #8 kembali menjadi Perlu Revisi",
      bdd="REV-OUTCOME-18")
kasus("A09", "Riwayat menunjukkan jejak kirim ulang", peneliti(8), DETAIL + " (#8)",
      "#8 sudah pernah dikirim ulang",
      "Buka bagian Riwayat / Timeline Status",
      "Terlihat urutan: dikirim, perlu revisi, dikirim ulang, sedang dinilai, perlu revisi lagi",
      ubah="Tidak")
kasus("A09", "Kirim ulang tanpa isian wajib ditolak", peneliti(8), REVISI + " (#8)",
      "#8 Perlu Revisi",
      "Kosongkan Abstrak (isian wajib), simpan, lalu coba \"Kirim Ulang\"",
      "Pengiriman ulang ditolak dan menyebut Abstrak wajib diisi\nSetelah itu isi lagi Abstraknya",
      bdd="SM-SUBMIT-19")

# ---------------------------------------------------------------- A10
kasus("A10", "Peneliti tidak bisa menarik pengajuannya sendiri", peneliti(3), DETAIL + " (pengajuan tahun lalu)",
      "Peneliti03 punya pengajuan tahun lalu yang Disetujui",
      "Buka detail pengajuan tahun lalu itu dan cari tombol tarik",
      "Tidak ada tombol untuk menarik pengajuan",
      bdd="SM-WITHDRAW-02", ubah="Tidak")
kasus("A10", "Tarik tidak ditawarkan untuk pengajuan yang belum disetujui", OPERATOR, SEMUA_DETAIL + " (#5)",
      "#5 belum disetujui",
      "Lihat Aksi Operator di #5",
      "Tombol \"Tarik Pengajuan\" tidak ada atau tidak bisa dipakai",
      bdd="SM-WITHDRAW-03", ubah="Tidak")
kasus("A10", "Menarik tanpa alasan tidak bisa", OPERATOR, SEMUA_DETAIL + " (pengajuan tahun lalu peneliti03)",
      "Pengajuan tahun lalu peneliti03 Disetujui",
      "Tekan \"Tarik Pengajuan\", kosongkan alasan, lalu coba konfirmasi",
      "Penarikan tidak bisa disimpan sebelum alasan diisi",
      bdd="SM-WITHDRAW-06", ubah="Tidak")
kasus("A10", "Menarik pengajuan yang sudah disetujui", OPERATOR, SEMUA_DETAIL + " (pengajuan tahun lalu peneliti03)",
      "Pengajuan tahun lalu peneliti03 Disetujui",
      "Tekan \"Tarik Pengajuan\", isi alasan \"Uji penarikan QA\", konfirmasi",
      "Status menjadi Ditarik Kembali\nDan Log Audit mencatat alasannya",
      bdd="SM-WITHDRAW-01, SM-WITHDRAW-07", prioritas="Tinggi")
kasus("A10", "Penarikan tidak bisa dibatalkan", OPERATOR, SEMUA_DETAIL + " (pengajuan yang baru ditarik)",
      "Pengajuan baru saja ditarik",
      "Cari tombol untuk membatalkan penarikan atau mengembalikan status",
      "Tidak ada tombol pembatalan",
      bdd="SM-WITHDRAW-05", ubah="Tidak")
kasus("A10", "Data pengajuan yang ditarik tetap utuh", peneliti(3), DETAIL + " (pengajuan yang ditarik)",
      "Pengajuan tahun lalu peneliti03 sudah ditarik",
      "Buka detail pengajuan itu, laporan Monev, dan luarannya",
      "Isian, nilai, laporan Monev, dan luaran lama masih bisa dibaca",
      bdd="SM-WITHDRAW-04, MON-CYCLE-11, RO-OUTPUT-10", ubah="Tidak")
kasus("A10", "Pengajuan yang ditarik: Monev dibekukan", peneliti(12), MONEV,
      "#12 Ditarik Kembali sesudah disetujui",
      "Buka Daftar Monev dan cari #12",
      "Tidak ada tombol untuk mengisi laporan Monev #12",
      bdd="MON-CYCLE-10, MON-CYCLE-12, MON-CYCLE-13", ubah="Tidak")
kasus("A10", "Alihkan kepemilikan hanya ke ketua pendamping (co-investigator)", OPERATOR, SEMUA_DETAIL + " (#9)",
      "Di #9, akun anggota adalah anggota biasa (bukan co-investigator)",
      "Tekan \"Alihkan Kepemilikan\" dan cari akun anggota sebagai pemilik baru",
      "Anggota biasa tidak bisa dipilih atau pengalihan ditolak dengan pesan yang jelas",
      bdd="SM-OWNER-02, SM-OWNER-05", ubah="Tidak")

# ---------------------------------------------------------------- A11
kasus("A11", "Daftar Monev hanya muncul untuk pengajuan yang disetujui", peneliti(9), MONEV,
      "#9 Disetujui dan laporan Monev 1 masih draf",
      "Buka Daftar Monev",
      "#9 tampil dengan tombol \"Lanjutkan Laporan Monev 1\" (nama siklus bisa sedikit berbeda)",
      bdd="MON-CYCLE-02", prioritas="Tinggi", ubah="Tidak")
kasus("A11", "Menu Monev tidak muncul tanpa pengajuan disetujui", peneliti(5), BERANDA,
      "Peneliti05 belum punya pengajuan yang disetujui",
      "Lihat menu kiri",
      "Menu \"Daftar Monev\" tidak ada",
      bdd="MON-CYCLE-01", ubah="Tidak")
kasus("A11", "Menyimpan draf laporan Monev", peneliti(9), LAPORAN_MONEV + " (#9)",
      "Laporan Monev 1 #9 masih draf",
      "Ubah isian laporan, tekan \"Simpan Draf\", muat ulang halaman",
      "Isian tetap tersimpan")
kasus("A11", "Monev 2 tertutup sebelum Monev 1 dikirim", peneliti(9), MONEV,
      "Laporan Monev 1 #9 belum dikirim",
      "Cari tombol untuk mengisi Monev 2",
      "Monev 2 belum bisa diisi",
      bdd="MON-CYCLE-03", ubah="Tidak")
kasus("A11", "Mengirim laporan Monev", peneliti(9), LAPORAN_MONEV + " (#9)",
      "Laporan Monev 1 #9 masih draf",
      "Lengkapi isian wajib lalu tekan \"Kirim Laporan\" dan konfirmasi",
      "Laporan terkirim dan tidak bisa diubah lagi",
      bdd="MON-CYCLE-04", prioritas="Tinggi")
kasus("A11", "Anggota tim hanya membaca laporan Monev", ANGGOTA, MONEV,
      "Anda anggota tim #9",
      "Buka Daftar Monev lalu laporan #9",
      "Laporan bisa dibaca\nDan tidak ada tombol isi atau kirim",
      bdd="MON-CYCLE-14, MON-CYCLE-15", ubah="Tidak")
kasus("A11", "Operator menugaskan penilai laporan Monev", OPERATOR, KELOLA_MONEV_DETAIL + " (#9)",
      "Laporan Monev 1 #9 sudah terkirim",
      "Tekan \"Tugaskan Penilai\", lihat saran penilai, pilih Penilai Luar, simpan",
      "Penilai Luar tercatat untuk siklus ini\nDan Penilai Luar mendapat notifikasi",
      bdd="MON-REVIEW-01, MON-REVIEW-03, MON-REVIEW-06, MON-REVIEW-11")
kasus("A11", "Anggota tim tidak bisa jadi penilai Monev", OPERATOR, KELOLA_MONEV_DETAIL + " (#10)",
      "Akun anggota adalah anggota tim #10",
      "Buka \"Tugaskan Penilai\" dan cari akun anggota",
      "Akun anggota tidak bisa dipilih",
      bdd="MON-REVIEW-15", ubah="Tidak")
kasus("A11", "Hasil evaluasi Monev tertutup sebelum penilai mengirim", peneliti(10), MONEV,
      "Laporan Monev 1 #10 terkirim, menunggu Penilai Luar",
      "Buka laporan #10",
      "Belum ada hasil evaluasi yang terlihat",
      bdd="MON-REVIEW-08", ubah="Tidak")
kasus("A11", "Penilai Luar menilai laporan Monev", PENILAI_LUAR, TUGAS,
      "Anda penilai laporan Monev 1 #10",
      "Buka penugasan #10, isi lembar evaluasi Monev, kirim",
      "Penilaian terkirim",
      bdd="MON-REVIEW-07", prioritas="Tinggi")
kasus("A11", "Peneliti melihat hasil evaluasi Monev", peneliti(10), MONEV,
      "Penilai Luar baru mengirim evaluasi #10",
      "Buka Notifikasi, lalu laporan #10",
      "Ada notifikasi hasil evaluasi\nDan hasilnya terlihat",
      bdd="MON-REVIEW-07, MON-REVIEW-12", ubah="Tidak")
kasus("A11", "Mencetak laporan Monev", peneliti(10), MONEV + " › laporan #10",
      "Evaluasi #10 sudah masuk",
      "Tekan tombol cetak laporan Monev",
      "Dokumen laporan terunduh atau terbuka",
      bdd="RPT-PRINT-06", prioritas="Rendah", ubah="Tidak")
kasus("A11", "Perpanjang tenggat Monev butuh alasan", OPERATOR, KELOLA_MONEV_DETAIL + " (#9)",
      "Anda di halaman kelola Monev #9",
      "1. Tekan \"Perpanjang Tenggat\", kosongkan alasan, coba simpan\n2. Isi alasan \"Uji perpanjangan\" dan tanggal minggu depan, simpan",
      "Tanpa alasan ditolak\nDan dengan alasan perpanjangan tercatat",
      bdd="FE-OVERRIDE-06, MON-CYCLE-09")

# ---------------------------------------------------------------- A12
kasus("A12", "Menu Luaran muncul untuk pengajuan yang disetujui", peneliti(9), LUARAN,
      "#9 Disetujui dan sudah punya satu luaran",
      "Buka Luaran Penelitian",
      "Luaran #9 tampil di daftar",
      bdd="RO-OUTPUT-02", prioritas="Tinggi", ubah="Tidak")
kasus("A12", "Menambah luaran Artikel Jurnal", peneliti(9), LUARAN_TAMBAH,
      "#9 Disetujui",
      "Pilih pengajuan #9, tipe Artikel Jurnal, isi isian wajib, tekan \"Simpan Luaran\"",
      "Luaran baru tampil di daftar",
      bdd="RO-OUTPUT-02, RO-OUTPUT-11", prioritas="Tinggi")
kasus("A12", "Isian wajib luaran yang kosong ditolak", peneliti(9), LUARAN_TAMBAH,
      "#9 Disetujui",
      "Pilih tipe Artikel Jurnal, kosongkan isian wajib, simpan",
      "Ditolak dan isian yang kurang ditandai",
      bdd="RO-OUTPUT-21", ubah="Tidak")
kasus("A12", "HKI wajib melampirkan berkas", peneliti(9), LUARAN_TAMBAH,
      "#9 Disetujui",
      "1. Pilih tipe HKI tanpa berkas, simpan\n2. Lampirkan satu PDF, simpan lagi",
      "Tanpa berkas ditolak\nDan dengan berkas tersimpan",
      bdd="RO-OUTPUT-06, RO-OUTPUT-07")
kasus("A12", "Prototipe wajib melampirkan berkas", peneliti(9), LUARAN_TAMBAH,
      "#9 Disetujui",
      "Pilih tipe Prototipe tanpa berkas, simpan; lalu dengan berkas",
      "Tanpa berkas ditolak\nDan dengan berkas tersimpan",
      bdd="RO-OUTPUT-17, RO-OUTPUT-18", prioritas="Rendah")
kasus("A12", "PKS hanya untuk Pengabdian", peneliti(10), LUARAN_TAMBAH,
      "#9 adalah Penelitian (milik peneliti09), #10 adalah Pengabdian (milik Anda)",
      "1. Pilih #10, lihat apakah tipe PKS (Perjanjian Kerja Sama) tersedia, simpan dengan isian wajib\n2. (Opsional) masuk sebagai peneliti09 dan cek pilihan tipe untuk #9",
      "Untuk #10 tipe PKS tersedia dan tersimpan\nDan untuk #9 tipe PKS tidak ditawarkan",
      bdd="RO-OUTPUT-03, RO-OUTPUT-04, RO-OUTPUT-05")
kasus("A12", "Mengoreksi luaran lewat Ubah", peneliti(9), LUARAN + " › \"Detail\" › \"Ubah\"",
      "#9 punya luaran",
      "Ubah judul luaran lalu simpan",
      "Judul berubah\nDan tidak ada tombol hapus luaran",
      bdd="RO-OUTPUT-24, RO-OUTPUT-23")
kasus("A12", "Anggota biasa hanya membaca luaran", ANGGOTA, LUARAN,
      "Anda anggota biasa di #9",
      "Buka luaran #9",
      "Luaran terbaca\nDan tidak ada tombol tambah atau ubah untuk #9",
      bdd="RO-OUTPUT-13", ubah="Tidak")
kasus("A12", "Luaran ditolak untuk pengajuan yang belum disetujui", peneliti(5), LUARAN_TAMBAH,
      "#5 belum disetujui",
      "Buka Tambah Luaran (atau alamat /research-outputs/create) dan cari #5",
      "#5 tidak bisa dipilih (atau menu Luaran tidak muncul sama sekali)",
      bdd="RO-OUTPUT-01", ubah="Tidak")
kasus("A12", "Berkas bukti hanya bisa diunduh yang berhak", peneliti(3), "Ketik alamat berkas luaran #9 (salin dari peneliti09)\n/research-outputs/…/files/…",
      "Salin alamat unduh berkas HKI #9 saat masuk sebagai peneliti09",
      "Masuk sebagai peneliti03 lalu buka alamat itu",
      "Akses ditolak (403)",
      bdd="RO-OUTPUT-28", ubah="Tidak")
kasus("A12", "Operator memperpanjang batas unggah luaran", OPERATOR, SEMUA_DETAIL + " (#10) › tab \"Luaran\"",
      "Anda masuk sebagai Operator",
      "Tekan \"Perpanjang Batas Unggah Luaran\" untuk #10, isi alasan dan tanggal, simpan",
      "Perpanjangan tercatat",
      bdd="RO-OUTPUT-16, FE-OVERRIDE-03", prioritas="Rendah")

# ---------------------------------------------------------------- A13
kasus("A13", "Membuat formulir baru", OPERATOR, FORMULIR,
      "Anda masuk sebagai Operator",
      "Tekan \"Buat Formulir Baru\", beri nama \"QA{k} Formulir Uji\", simpan",
      "Formulir tampil di daftar sebagai draf",
      prioritas="Tinggi")
kasus("A13", "Menambah isian dan opsi", OPERATOR, FORMULIR + " › \"QA{k} Formulir Uji\" › \"Ubah\"",
      "Formulir QA{k} Formulir Uji terbuka",
      "Tambah isian Judul, isian pilihan \"Bidang\" dengan 3 opsi (\"Tambah Opsi\"), jadikan satu isian wajib",
      "Isian tersimpan sesuai urutan\nDan \"Pratinjau\" menampilkan formulir seperti yang akan dilihat peneliti")
kasus("A13", "Dua isian judul tidak boleh", OPERATOR, FORMULIR + " › \"QA{k} Formulir Uji\"",
      "Formulir sudah punya isian Judul",
      "Tambah isian Judul kedua, simpan",
      "Ditolak dengan pesan bahwa formulir hanya boleh punya satu isian judul",
      bdd="SM-TITLE-04", ubah="Tidak")
kasus("A13", "Pilihan TKT tanpa pilihan skema ditolak", OPERATOR, FORMULIR + " › \"QA{k} Formulir Uji\"",
      "Formulir belum punya isian pilihan skema",
      "Tambah isian pilihan TKT, simpan",
      "Ditolak dengan pesan bahwa TKT butuh isian pilihan skema",
      bdd="SCH-RULE-09", ubah="Tidak")
kasus("A13", "Menerbitkan formulir", OPERATOR, FORMULIR + " › \"QA{k} Formulir Uji\"",
      "Formulir punya isian",
      "Tekan \"Terbitkan\" (atau tombol terbit) dan baca ringkasan dampaknya, lalu konfirmasi",
      "Formulir terbit\nDan ringkasan dampak menyebut isian wajib baru dan draf yang terdampak",
      bdd="FE-FORMPUB-02, FE-EVLIB-04")
kasus("A13", "Formulir yang sudah punya pengajuan tidak bisa dihapus", OPERATOR, FORMULIR,
      "Formulir Penelitian data QA sudah punya banyak pengajuan",
      "Cari tombol hapus pada formulir itu",
      "Hapus tidak tersedia atau ditolak dengan jumlah pengajuan yang menghalangi; yang ditawarkan adalah nonaktifkan\nPENTING: jangan menonaktifkan formulir bawaan",
      bdd="FE-DELETE-01, FE-DELETE-05, FE-DELETE-08", ubah="Tidak")
kasus("A13", "Formulir tanpa pengajuan bisa dihapus", OPERATOR, FORMULIR,
      "Buat formulir \"QA{k} Hapus Saya\" tanpa isian",
      "Hapus formulir itu",
      "Formulir hilang dari daftar",
      bdd="FE-DELETE-04")
kasus("A13", "Membuat Formulir Evaluasi", OPERATOR, FORMULIR_EVALUASI,
      "Anda masuk sebagai Operator",
      "\"Buat Formulir Evaluasi\" bernama \"QA{k} Lembar Uji\", tambah 2 kriteria nilai, terbitkan",
      "Formulir evaluasi terbit sebagai versi 1 (lihat \"Versi & pemakaian\")",
      bdd="FE-EVLIB-04")
kasus("A13", "Formulir Evaluasi yang belum terbit tidak bisa dipasang", OPERATOR, TAHAP + " › \"Buat Tahap Baru\"",
      "Buat Formulir Evaluasi \"QA{k} Draf\" tanpa menerbitkan",
      "Saat membuat langkah tahap, cari \"QA{k} Draf\" di pilihan formulir evaluasi",
      "Formulir yang belum terbit tidak bisa dipilih",
      bdd="FE-EVLIB-01", ubah="Tidak")
kasus("A13", "Membuat tahap baru", OPERATOR, TAHAP,
      "QA{k} Formulir Uji dan QA{k} Lembar Uji sudah terbit",
      "\"Buat Tahap Baru\" bernama \"QA{k} Tahap Uji\" dengan langkah Pengajuan (QA{k} Formulir Uji) yang dinilai dengan QA{k} Lembar Uji; terbitkan",
      "Tahap terbit")
kasus("A13", "Tahap tanpa langkah Pengajuan ditolak", OPERATOR, TAHAP,
      "Anda di layar membuat tahap",
      "Buat tahap \"QA{k} Tanpa Pengajuan\" yang hanya berisi langkah Laporan Monev, simpan",
      "Ditolak dengan pesan bahwa tahap butuh langkah Pengajuan",
      bdd="FE-PHASE-06, FE-PHASE-03", ubah="Tidak")
kasus("A13", "Membuat periode baru dan cek kesiapan", OPERATOR, PERIODE,
      "QA{k} Tahap Uji sudah terbit",
      "1. \"Buat Periode Baru\" bernama \"QA{k} Periode Uji\", tautkan QA{k} Tahap Uji, isi Batas Submit tetapi KOSONGKAN Batas Evaluasi\n2. Buka \"Kesiapan\"",
      "Kesiapan menandai Batas Evaluasi yang belum diatur\nDan tabel periode menghitung angkatan yang belum siap",
      bdd="FE-READY-01, FE-READY-06")
kasus("A13", "Periode tanpa pengajuan bisa dihapus", OPERATOR, PERIODE,
      "QA{k} Periode Uji belum punya pengajuan",
      "Hapus QA{k} Periode Uji",
      "Periode hilang dari daftar\nDan periode bawaan (yang punya pengajuan) tidak bisa dihapus",
      bdd="FE-DELETE-12, FE-DELETE-10")

# ---------------------------------------------------------------- A14
kasus("A14", "Membuat skema baru", OPERATOR, SKEMA,
      "Anda masuk sebagai Operator",
      "\"Buat Skema Baru\": nama \"QA{k} Skema Uji\", kode \"QA{k}-SU\", pagu 10000000, maks anggota 3, simpan",
      "Skema tampil di daftar",
      bdd="SCH-RULE-03", prioritas="Tinggi")
kasus("A14", "Kode skema harus unik", OPERATOR, SKEMA,
      "Kode QA-PD sudah dipakai skema Penelitian Dasar",
      "Buat skema baru dengan kode QA-PD, simpan",
      "Ditolak dengan pesan bahwa kode sudah dipakai",
      bdd="SCH-RULE-01", ubah="Tidak")
kasus("A14", "Pagu dan jumlah anggota harus lebih dari nol", OPERATOR, SKEMA,
      "Anda di layar membuat skema",
      "Isi pagu 0 dan maks anggota 0, simpan",
      "Ditolak dengan pesan untuk kedua isian",
      bdd="SCH-RULE-02", ubah="Tidak")
kasus("A14", "Skema yang dipakai tidak bisa dinonaktifkan", OPERATOR, SKEMA,
      "Skema Penelitian Dasar dipakai pengajuan aktif",
      "Coba nonaktifkan Penelitian Dasar",
      "Ditolak dengan pesan bahwa skema masih dipakai\nPENTING: bila ternyata berhasil, segera aktifkan kembali dan catat temuan",
      bdd="SCH-RULE-04, SCH-RULE-15", ubah="Tidak")
kasus("A14", "Skema tanpa pengajuan bisa dinonaktifkan", OPERATOR, SKEMA,
      "QA{k} Skema Uji belum dipakai",
      "Nonaktifkan QA{k} Skema Uji",
      "Skema berstatus nonaktif\nDan tidak muncul di pilihan skema pengajuan baru",
      bdd="SCH-RULE-05, SCH-RULE-14")
kasus("A14", "Membuat sub-unit organisasi", OPERATOR, ORGANISASI,
      "Anda masuk sebagai Operator",
      "Pilih satu jurusan, \"Tambah Sub-unit\" bernama \"QA{k} Lab Uji\", simpan",
      "Sub-unit muncul di bawah jurusan itu",
      bdd="IAM-ORG-10", prioritas="Tinggi")
kasus("A14", "Mengganti nama dan memindah unit", OPERATOR, ORGANISASI,
      "QA{k} Lab Uji sudah ada",
      "1. \"Ubah Nama / Tipe\" menjadi \"QA{k} Lab Uji 2\"\n2. Pindahkan ke jurusan lain",
      "Nama berubah dan unit pindah ke induk baru",
      bdd="IAM-ORG-11, IAM-ORG-12")
kasus("A14", "Unit tidak bisa dipindah ke bawah dirinya sendiri", OPERATOR, ORGANISASI,
      "Buat sub-unit \"QA{k} Anak\" di bawah QA{k} Lab Uji 2",
      "Coba pindahkan QA{k} Lab Uji 2 ke bawah QA{k} Anak",
      "Ditolak dengan pesan yang jelas",
      bdd="IAM-ORG-13", ubah="Tidak")
kasus("A14", "Organisasi berisi pengguna tidak bisa dihapus", OPERATOR, ORGANISASI,
      "Jurusan tempat akun QA berada berisi pengguna",
      "Coba hapus jurusan itu",
      "Ditolak dengan pesan bahwa organisasi masih berisi pengguna",
      bdd="IAM-ORG-01, IAM-ORG-02", ubah="Tidak")
kasus("A14", "Organisasi kosong bisa dihapus", OPERATOR, ORGANISASI,
      "QA{k} Anak tidak berisi pengguna",
      "Hapus QA{k} Anak, lalu QA{k} Lab Uji 2",
      "Keduanya hilang dari pohon organisasi",
      bdd="IAM-ORG-03")
kasus("A14", "Menambah tipe luaran di Konfigurasi Umum", OPERATOR, KONFIGURASI,
      "Anda masuk sebagai Operator",
      "Buka pengelolaan tipe luaran, tambah \"QA{k} Poster\", simpan",
      "Tipe baru tampil\nDan langsung bisa dipilih di Tambah Luaran",
      bdd="SC-CONFIG-06, RO-OUTPUT-09")
kasus("A14", "Tipe luaran yang dipakai tidak bisa dihapus", OPERATOR, KONFIGURASI,
      "Tipe Artikel Jurnal sudah dipakai luaran",
      "Coba hapus Artikel Jurnal",
      "Ditolak; yang ditawarkan adalah \"Nonaktifkan\"",
      bdd="SC-CONFIG-07, SC-CONFIG-01", ubah="Tidak")
kasus("A14", "Menonaktifkan tipe luaran milik sendiri", OPERATOR, KONFIGURASI,
      "QA{k} Poster sudah ada",
      "\"Nonaktifkan\" QA{k} Poster",
      "Status nonaktif\nDan tidak lagi ditawarkan di Tambah Luaran",
      bdd="SC-CONFIG-08")

# ---------------------------------------------------------------- A15
kasus("A15", "Melihat detail pengguna", ADMIN, PENGGUNA_DETAIL,
      "Anda masuk sebagai Admin",
      "Cari \"QA{k} Peneliti 09\" lalu \"Detail\"",
      "Terlihat Data Personal, Peran & Hak Akses, daftar pengajuan, dan Sesi Masuk",
      prioritas="Tinggi", ubah="Tidak")
kasus("A15", "Menonaktifkan akun", ADMIN, PENGGUNA_DETAIL + " (peneliti11)",
      "Peneliti11 hanya punya pengajuan Ditolak (#11)",
      "Tekan nonaktifkan, lalu \"Nonaktifkan\" di konfirmasi",
      "Akun berstatus nonaktif",
      bdd="IAM-DEACT-01, IAM-DEACT-08", prioritas="Tinggi")
kasus("A15", "Akun nonaktif tidak bisa masuk", peneliti(11), MASUK,
      "Akun peneliti11 baru dinonaktifkan",
      "Coba masuk sebagai peneliti11",
      "Masuk ditolak dengan pesan bahwa akun nonaktif",
      bdd="IAM-DEACT-02", prioritas="Tinggi", ubah="Tidak")
kasus("A15", "Sesi yang sedang masuk berakhir saat dinonaktifkan", peneliti(12) + " (browser 1) dan " + ADMIN + " (browser 2)",
      PENGGUNA_DETAIL + " (peneliti12)",
      "Peneliti12 sedang masuk di browser lain",
      "1. Admin menonaktifkan peneliti12\n2. Di browser peneliti12, klik menu mana saja",
      "Peneliti12 langsung keluar dan tidak bisa membuka data",
      bdd="IAM-DEACT-09")
kasus("A15", "Mengaktifkan kembali akun", ADMIN, PENGGUNA_DETAIL + " (peneliti11, lalu peneliti12)",
      "Peneliti11 dan peneliti12 nonaktif",
      "Tekan aktifkan, lalu \"Aktifkan\" di konfirmasi; ulangi untuk peneliti12",
      "Kedua akun bisa masuk lagi dan datanya utuh",
      bdd="IAM-REACT-01", prioritas="Tinggi")
kasus("A15", "Akun pemilik pengajuan aktif tidak bisa dinonaktifkan", ADMIN, PENGGUNA_DETAIL + " (peneliti10)",
      "Peneliti10 memimpin #10 yang Disetujui dan masih berjalan",
      "Coba nonaktifkan peneliti10",
      "Ditolak, dengan tombol \"Alihkan Kepemilikan\" untuk pengajuan yang menghalangi\nPENTING: bila ternyata berhasil, segera aktifkan kembali dan catat temuan",
      bdd="SM-OWNER-03, SM-OWNER-04", ubah="Tidak")
kasus("A15", "Tidak ada tombol hapus pengguna", ADMIN, PENGGUNA_DETAIL,
      "Anda di halaman detail pengguna mana saja",
      "Cari tombol hapus pengguna",
      "Tidak ada tombol hapus; yang ada hanya nonaktifkan",
      bdd="IAM-DEACT-05, IAM-DEACT-07", ubah="Tidak")
kasus("A15", "Operator tidak bisa mengaktifkan kembali akun", OPERATOR, PENGGUNA_DETAIL,
      "Anda masuk sebagai Operator (bukan Admin)",
      "Buka detail pengguna mana saja",
      "Tombol nonaktifkan/aktifkan tidak ada untuk Operator",
      bdd="IAM-REACT-04", ubah="Tidak")
kasus("A15", "Mengakhiri semua sesi", ADMIN, PENGGUNA_DETAIL + " (peneliti09)",
      "Peneliti09 sedang masuk di browser lain dan tampil \"Sedang aktif\"",
      "Tekan \"Akhiri Semua Sesi\" lalu konfirmasi",
      "Peneliti09 keluar dari browser lain\nDan akunnya tetap aktif (bisa masuk lagi)",
      bdd="IAM-SESSION-01, IAM-SESSION-02")
kasus("A15", "Mengubah peran pengguna", ADMIN, PENGGUNA_DETAIL + " (akun qa{k}-mitra dari A03, atau peneliti-baru)",
      "Anda masuk sebagai Admin",
      "\"Ubah Peran\", tambah peran lalu \"Simpan Peran\"; lalu kembalikan",
      "Menu kiri akun itu berubah sesuai peran barunya",
      bdd="IAM-PERM-03")
kasus("A15", "Menunjuk penilai baru", OPERATOR, PENILAI,
      "Anda masuk sebagai Operator",
      "\"Tambah Penilai\", pilih qa{k}-peneliti-baru, tipe internal, isi masa tugas sampai akhir tahun, simpan",
      "Akun itu tampil di daftar penilai beserta masa tugasnya\nDan ia mendapat notifikasi penunjukan",
      bdd="IAM-REVIEWER-01, IAM-REVIEWER-06, IAM-EVENT-04")
kasus("A15", "Mengubah masa tugas penilai", OPERATOR, PENILAI,
      "Penilai baru dari kasus sebelumnya ada di daftar",
      "\"Ubah Periode\" dan geser tanggal berakhir, simpan",
      "Masa tugas baru tersimpan",
      prioritas="Rendah")

# ---------------------------------------------------------------- A16
kasus("A16", "Operator membuat pengumuman", OPERATOR, KELOLA_PENGUMUMAN,
      "Anda masuk sebagai Operator",
      "\"Buat Pengumuman\" berjudul \"QA{k} Pengumuman Uji\", isi teks dengan huruf tebal dan satu gambar (\"Sisipkan\"), tekan \"Terbitkan\"",
      "Pengumuman tampil di daftar kelola",
      prioritas="Tinggi")
kasus("A16", "Peneliti melihat pengumuman baru", peneliti(1), PENGUMUMAN,
      "QA{k} Pengumuman Uji baru terbit",
      "Lihat angka di menu Pengumuman, lalu buka pengumuman itu",
      "Menu Pengumuman menunjukkan angka belum dibaca\nDan isinya tampil rapi (huruf tebal dan gambar)",
      prioritas="Tinggi", ubah="Tidak")
kasus("A16", "Menyunting dan menghapus pengumuman", OPERATOR, KELOLA_PENGUMUMAN,
      "QA{k} Pengumuman Uji ada",
      "\"Sunting\" judulnya, \"Simpan Perubahan\"; lalu \"Hapus\"",
      "Judul berubah, lalu pengumuman hilang dari daftar dan dari layar peneliti")
kasus("A16", "Notifikasi tersimpan dan membuka halaman terkait", peneliti(6), NOTIFIKASI,
      "Akun Anda punya notifikasi (mis. dari data uji)",
      "Klik \"Buka\" pada satu notifikasi",
      "Halaman yang dimaksud notifikasi terbuka\nDan notifikasi itu tertandai sudah dibaca",
      bdd="NOT-INAPP-01, NOT-INAPP-03, NOT-INAPP-05", prioritas="Tinggi")
kasus("A16", "Tandai semua dibaca", peneliti(6), NOTIFIKASI,
      "Ada beberapa notifikasi belum dibaca",
      "Tekan \"Tandai Semua Dibaca\"",
      "Semua notifikasi tertandai dibaca\nDan angka di menu Notifikasi hilang",
      bdd="NOT-INAPP-04")
kasus("A16", "Menghapus notifikasi", peneliti(6), NOTIFIKASI,
      "Ada notifikasi",
      "Tekan \"Hapus\" pada satu notifikasi",
      "Notifikasi itu hilang dan tidak kembali setelah muat ulang",
      bdd="NOT-INAPP-02")
kasus("A16", "Notifikasi datang tanpa muat ulang", peneliti(6) + " (browser 1) dan " + OPERATOR + " (browser 2)",
      NOTIFIKASI,
      "Peneliti06 membuka Beranda di browser 1; #6 siap diputuskan",
      "Di browser 2, Operator menekan \"Setujui\" pada #6; jangan muat ulang browser 1",
      "Angka di menu Notifikasi browser 1 bertambah sendiri dalam beberapa detik",
      bdd="NOT-INAPP-01", ubah="Ya")
kasus("A16", "Notifikasi tidak membuka data yang sudah tidak boleh dilihat", ANGGOTA, NOTIFIKASI,
      "Bila ada notifikasi untuk pengajuan yang Anda tidak lagi ikuti",
      "Klik notifikasi itu",
      "Data pengajuan tidak terbuka; muncul pesan yang jelas",
      bdd="NOT-INAPP-06", prioritas="Rendah", ubah="Tidak")
kasus("A16", "Beranda menampilkan aksi sesuai peran", peneliti(7), BERANDA,
      "#7 Perlu Revisi",
      "Buka Beranda",
      "Ada ringkasan pengajuan dan petunjuk tindakan berikutnya (mis. revisi #7)",
      prioritas="Rendah", ubah="Tidak")
kasus("A16", "Menyunting profil", peneliti(1), PROFIL,
      "Anda masuk sebagai Peneliti",
      "Ubah nomor telepon, ganti foto profil, tekan \"Simpan Perubahan\"",
      "Perubahan tersimpan dan foto baru tampil di menu kiri",
      ubah="Ya")
kasus("A16", "Mengubah kata sandi", peneliti(1), PROFIL,
      "Anda masuk sebagai Peneliti",
      "\"Ubah Kata Sandi\" ke sandi baru; keluar; masuk dengan sandi baru; lalu kembalikan ke sandi lama (alamat email)",
      "Sandi baru berlaku\nDan setelah dikembalikan, sandi lama berlaku lagi",
      prioritas="Rendah")

# ---------------------------------------------------------------- A17
kasus("A17", "Statistik tampil di semua tab", OPERATOR, STATISTIK,
      "Data latar dan data QA sudah ada",
      "Buka setiap tab Statistik; pakai \"Lihat tabel\" dan \"Lihat grafik\"",
      "Angka dan grafik tampil tanpa error\nDan tombol tabel/grafik bergantian dengan benar",
      prioritas="Tinggi", ubah="Tidak")
kasus("A17", "Rincian dari statistik ke daftar pengajuan", OPERATOR, STATISTIK,
      "Tab ringkasan Statistik terbuka",
      "Klik angka \"Disetujui\" atau tombol \"Drill-down: Semua Pengajuan\"",
      "Semua Pengajuan terbuka dengan penyaring yang sama (hanya yang Disetujui)",
      ubah="Tidak")
kasus("A17", "Statistik per Fakultas dan Prodi", OPERATOR, STATISTIK,
      "Tab perbandingan unit terbuka",
      "Ganti tampilan antara \"Fakultas\" dan \"Prodi\"",
      "Data per fakultas dan prodi tampil dengan nama unit yang benar",
      prioritas="Rendah", ubah="Tidak")
kasus("A17", "Mengekspor data", OPERATOR, EKSPOR,
      "Anda masuk sebagai Operator",
      "Pilih jenis ekspor, format Excel, saring tahun ini, tekan \"Ajukan Ekspor\"; tunggu lalu \"Unduh\"",
      "Berkas Excel terunduh dan isinya sesuai penyaring\nDan bila ekspor diproses di latar, Anda mendapat notifikasi saat siap",
      bdd="RPT-EXPORT-01, RPT-EXPORT-02, RPT-EXPORT-03, RPT-EXPORT-05", prioritas="Tinggi")
kasus("A17", "Ekspor ulang", OPERATOR, EKSPOR,
      "Ada ekspor lama di daftar",
      "Tekan \"Ekspor Ulang\" pada salah satu",
      "Ekspor baru terbuat dengan format yang sama",
      prioritas="Rendah")
kasus("A17", "Peneliti mengekspor pengajuannya sendiri", peneliti(9), DAFTAR + " › tombol \"Ekspor\"",
      "Anda masuk sebagai peneliti09",
      "Tekan \"Ekspor\" di Daftar Pengajuan lalu buka berkasnya",
      "Isi berkas hanya pengajuan milik Anda atau yang Anda ikuti",
      bdd="RPT-EXPORT-08, RPT-EXPORT-09, RPT-EXPORT-12")
kasus("A17", "Log Audit seluruh sistem", OPERATOR, AUDIT,
      "Sudah ada tindakan di set Anda (mis. penarikan di A10)",
      "Saring Log Audit dengan nama pengajuan atau pelaku, lalu \"Detail Aktivitas\"",
      "Entri memuat pelaku, waktu, alamat IP, serta nilai lama dan baru",
      bdd="RPT-AUDIT-01, RPT-AUDIT-04, RPT-AUDIT-06, RPT-AUDIT-08", prioritas="Tinggi", ubah="Tidak")
kasus("A17", "Log Audit tidak bisa diubah atau dihapus", OPERATOR, AUDIT,
      "Log Audit terbuka",
      "Cari tombol ubah atau hapus pada entri",
      "Tidak ada tombol ubah atau hapus",
      bdd="RPT-AUDIT-02, RPT-AUDIT-03", ubah="Tidak")
kasus("A17", "Ekspor Log Audit", OPERATOR, AUDIT,
      "Log Audit terbuka",
      "Tekan \"Ekspor Log Audit\"",
      "Berkas ekspor log audit bisa diunduh",
      prioritas="Rendah", ubah="Tidak")
kasus("A17", "Peneliti tidak bisa membuka Log Audit sistem", peneliti(9), "Ketik alamat langsung\n/reporting/audit",
      "Anda masuk sebagai Peneliti",
      "Buka alamat /reporting/audit",
      "Akses ditolak (403)",
      bdd="RPT-AUDIT-05", ubah="Tidak")

# ---------------------------------------------------------------- A18
kasus("A18", "Semua tulisan berbahasa Indonesia", "semua akun yang Anda pakai", "Semua halaman yang Anda buka",
      "Anda sudah menjalankan alur lain",
      "Perhatikan tombol, judul, pesan error, tanggal, dan email di semua halaman",
      "Tidak ada tulisan bahasa Inggris (mis. \"Submit\", \"Dashboard\", \"Required\")\nDan tanggal ditulis gaya Indonesia (mis. 14 November 2026)",
      prioritas="Tinggi", ubah="Tidak")
kasus("A18", "Tidak ada tanda pisah panjang di layar", "semua akun yang Anda pakai", "Semua halaman yang Anda buka",
      "Anda sudah menjalankan alur lain",
      "Cari tanda pisah panjang (garis datar yang lebih panjang dari tanda minus) di tulisan layar, termasuk nama tipe luaran di Tambah Luaran",
      "Tidak ada tanda pisah panjang; yang dipakai titik dua, kurung, atau tanda minus biasa",
      prioritas="Rendah", ubah="Tidak")
kasus("A18", "Tampilan di layar HP", peneliti(7), "Daftar Pengajuan, detail pengajuan, wizard, Beranda",
      "Buka staging di HP (atau mode HP di browser, lebar 375 piksel)",
      "Buka menu, Daftar Pengajuan, detail #7, dan wizard draf",
      "Menu bisa dibuka-tutup, tabel tidak terpotong keluar layar, tombol bisa ditekan",
      prioritas="Tinggi", ubah="Tidak")
kasus("A18", "Tampilan Operator di layar HP", OPERATOR, "Semua Pengajuan, Pembuat Formulir, Skema Penelitian",
      "Buka staging di HP (atau mode HP di browser)",
      "Buka Semua Pengajuan, Pembuat Formulir, dan Skema Penelitian",
      "Status dan tombol aksi tetap terlihat (tidak terdorong keluar layar)",
      ubah="Tidak")
kasus("A18", "Warna status konsisten", OPERATOR, SEMUA,
      "Semua Pengajuan menampilkan berbagai status",
      "Bandingkan warna label status (Disetujui, Ditolak, Perlu Revisi, Sedang Dinilai, Draf)",
      "Setiap status punya warna yang sama di semua halaman dan mudah dibaca",
      prioritas="Rendah", ubah="Tidak")
kasus("A18", "Keyboard saja", peneliti(1), MASUK + ", lalu Beranda",
      "Tidak memakai mouse",
      "Masuk dan buka Daftar Pengajuan hanya dengan Tab, Shift+Tab, dan Enter",
      "Fokus selalu terlihat (ada garis tepi) dan semua tombol bisa dicapai",
      prioritas="Rendah", ubah="Tidak")
kasus("A18", "Browser lain", "akun mana saja", "Halaman masuk dan Beranda",
      "Ada browser kedua (Firefox atau Safari)",
      "Masuk dan buka 3 halaman berbeda",
      "Tampilan dan fungsi sama dengan Chrome",
      prioritas="Rendah", ubah="Tidak")
