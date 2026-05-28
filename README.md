# SIMPAS v2 (Sistem Informasi Manajemen Penelitian dan Pengabdian Masyarakat)

Sistem Informasi Manajemen Penelitian dan Pengabdian Masyarakat untuk ITK (Institut Teknologi Kalimantan).

## Development Setup

Proyek ini menggunakan Docker Compose untuk menyederhanakan proses setup di lingkungan development. Stack ini mencakup:
- **PHP App** (Laravel 13, Vue 3, Vite, PHP 8.3)
- **PostgreSQL 18** (Primary Database)
- **Dragonfly** (Redis-compatible Cache & Queue)
- **MinIO** (S3-compatible Local File Storage)

### Prasyarat
- Docker & Docker Compose
- Git

### Langkah-langkah Setup

1. **Clone Repository**
   ```bash
   git clone https://github.com/nas11ai/sim-kerjasama-itk.git
   cd sim-kerjasama-itk
   ```

2. **Jalankan Docker Compose**
   Cukup dengan satu perintah ini, seluruh stack akan berjalan, termasuk instalasi dependency (Composer & NPM) secara otomatis:
   ```bash
   docker compose up -d
   ```
   *Catatan: Saat pertama kali dijalankan, proses akan memakan waktu karena mengunduh image dan menginstall dependencies.*

3. **Akses Aplikasi**
   - Laravel App: http://localhost:8000
   - Vite (Hot Module Replacement): http://localhost:5173
   - MinIO Console: http://localhost:9001 (User/Pass default: `minioadmin` / `minioadmin`)

### Konfigurasi Lanjutan
File `.env` akan dibuat secara otomatis dari `.env.example` saat container `app` pertama kali dijalankan. Jika ingin menggunakan database lokal yang berbeda atau setup tambahan, Anda dapat mengubah isi `.env` sesuai kebutuhan.

## Kontribusi

Baca [CONTRIBUTING.md](CONTRIBUTING.md) untuk panduan lengkap mengenai alur kerja, konvensi branch, commit, kode, testing, dan cara submit Pull Request.
