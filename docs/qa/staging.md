# Staging QA di VM Oracle Always Free

Satu VM gratis menjalankan SIMPAS lengkap (web, antrean, Reverb, scheduler, PostgreSQL). Penguji cukup membuka
satu URL HTTPS; tidak ada yang perlu dipasang di laptop mereka. Rancangan:
`docs/design/2026-11-14-staging-vm-design.md`. Data uji dan akun: [`data-uji.md`](data-uji.md).

Berkas yang dipakai: `docker-compose.staging.yml`, `docker/staging/`, `.env.staging.example`,
`scripts/staging/deploy.sh`, `scripts/staging/reset-data.sh`.

## 1. Akun Oracle

1. Daftar di <https://www.oracle.com/cloud/free/>. Kartu kredit dipakai untuk verifikasi (ada tahanan kecil
   sementara), bukan untuk tagihan selama sumber daya tetap di dalam kuota Always Free.
2. **Home region tidak bisa diganti sesudah mendaftar.** Pilih yang dekat: *Singapore* (`ap-singapore-1`)
   atau *Indonesia North / Batam* (`ap-batam-1`). Pastikan shape Ampere A1 tersedia untuk Always Free di region
   itu sebelum menyelesaikan pendaftaran.
3. Kuota Always Free Ampere A1 per akun: 2 OCPU dan 12 GB memori total (dipotong dari 4/24 pada 2026). Satu VM
   yang memakai seluruhnya sudah lebih dari cukup.

## 2. Membuat VM

Menu **Compute → Instances → Create instance**:

| Isian | Nilai |
| --- | --- |
| Image | Canonical Ubuntu 24.04 (versi aarch64 terpilih otomatis untuk A1) |
| Shape | Ampere `VM.Standard.A1.Flex`, 2 OCPU, 12 GB |
| Networking | VCN baru dengan subnet publik, **Assign a public IPv4 address** dicentang |
| SSH key | unggah kunci publik Anda (`~/.ssh/id_ed25519.pub`) atau unduh pasangan yang dibuat Oracle |
| Boot volume | bawaan (sekitar 47 GB; kuota gratis 200 GB) |

Catat **Public IP address** setelah VM berstatus *Running*. Contoh di bawah memakai `152.67.10.20`.

Bila muncul **"Out of capacity"**: region sedang penuh untuk A1. Coba lagi beberapa jam kemudian, atau pilih
availability domain lain di layar yang sama.

## 3. Membuka port 80 dan 443

Dua lapis, keduanya wajib:

1. **Security List** (konsol): VCN → Subnet publik → Security List bawaan → *Add Ingress Rules*. Dua aturan:
   Source CIDR `0.0.0.0/0`, IP Protocol TCP, Destination Port `80`; lalu sama untuk `443`.
2. **Firewall di dalam VM**: image Ubuntu dari Oracle menolak semua port selain 22. Masuk lewat SSH
   (`ssh ubuntu@152.67.10.20`) lalu:

   ```bash
   sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
   sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
   sudo netfilter-persistent save
   ```

Port 80 dibutuhkan Caddy untuk mendapatkan sertifikat HTTPS dari Let's Encrypt; kalau tertutup, situs tidak
pernah bisa dibuka lewat HTTPS.

Jalankan `netfilter-persistent save` di langkah ini, **sebelum** Docker dipasang. Docker menambah aturan
iptables-nya sendiri; menjalankan `netfilter-persistent save` atau `reload` sesudahnya ikut menyimpan atau
menghapus aturan Docker, dan situs mati sampai `sudo systemctl restart docker`.

## 4. Memasang Docker

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker ubuntu
exit   # masuk lagi lewat SSH supaya grup docker berlaku
```

Periksa: `docker compose version` mencetak versi.

## 5. Mengambil kode

VM membaca repo lewat *deploy key* read-only, jadi tidak ada kredensial pribadi di server.

```bash
ssh-keygen -t ed25519 -f ~/.ssh/simpas_deploy -N ''
cat ~/.ssh/simpas_deploy.pub
```

Tempel kunci itu di GitHub: repo → *Settings → Deploy keys → Add deploy key*, **tanpa** centang *Allow write
access*. Lalu:

```bash
git clone -c core.sshCommand='ssh -i ~/.ssh/simpas_deploy -o IdentitiesOnly=yes' \
    -b feat/init-be-development git@github.com:nas11ai/sim-kerjasama-itk.git simpas
cd simpas
```

Ketik `yes` saat SSH menanyakan sidik jari github.com. Kunci deploy tersimpan di `.git/config` checkout ini,
jadi `git pull` di `deploy.sh` memakainya tanpa pengaturan lain.

Cabang yang di-clone harus sudah di-push ke GitHub. Ganti `feat/init-be-development` bila staging melacak cabang
lain.

## 6. Mengisi `.env`

```bash
cp .env.staging.example .env
```

Isi yang bertanda **WAJIB** di berkas itu:

| Variabel | Isi |
| --- | --- |
| `SITE_HOST` | `152.67.10.20.sslip.io` (IP publik VM + `.sslip.io`; layanan DNS gratis, tanpa mendaftar) |
| `BASIC_AUTH_HASH` | keluaran perintah di bawah, **di antara petik tunggal** |
| `DB_PASSWORD` | `openssl rand -hex 16` |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | masing-masing `openssl rand -hex 16` |

```bash
docker run --rm -it caddy:2 caddy hash-password
```

Perintah ini meminta kata sandi gerbang dua kali (tidak tersimpan di riwayat shell) lalu mencetak hash.

`BASIC_AUTH_USER` (bawaan `qa`) dan kata sandi gerbang itulah yang dibagikan ke penguji. Gerbang ini wajib:
kata sandi akun uji sama dengan alamat emailnya dan tertulis di `data-uji.md`.

Kalau punya domain sendiri, isi `SITE_HOST` dengan domain itu dan arahkan record A-nya ke IP VM.

## 7. Menjalankan

```bash
scripts/staging/deploy.sh       # membangun image, memasang dependency, build aset, migrate
scripts/staging/reset-data.sh   # mengisi data uji QA; meminta Anda mengetik ulang SITE_HOST
```

Deploy pertama memakan waktu 10–20 menit (mengunduh image, Composer, npm, build aset); deploy berikutnya
beberapa menit karena image sudah tersimpan. `reset-data.sh` sekitar 5–10 menit. Sesudah selesai, buka
`https://152.67.10.20.sslip.io`, masukkan Basic Auth, lalu masuk dengan salah satu akun di `data-uji.md`.

## 8. Membagikan ke penguji

Kirim tiga hal: URL, username dan kata sandi Basic Auth, dan `data-uji.md` (set mana yang dipakai siapa).
Browser meminta Basic Auth satu kali per sesi; sesudah itu halaman masuk SIMPAS tampil seperti biasa.

## Pekerjaan rutin

| Kebutuhan | Perintah (di `~/simpas`) |
| --- | --- |
| Memperbarui ke commit terbaru yang sudah di-push | `scripts/staging/deploy.sh` |
| Mengembalikan data uji ke awal | `scripts/staging/reset-data.sh` |
| Melihat log aplikasi (termasuk isi email: tautan verifikasi dan reset kata sandi) | `tail -f storage/logs/laravel-$(date +%F).log` |
| Melihat log proses | `docker compose logs -f app queue reverb caddy` |
| Melihat status service | `docker compose ps` |
| Mematikan sementara | `docker compose stop` |

`.env` mengatur `COMPOSE_FILE`, jadi `docker compose` di folder ini selalu memakai berkas staging. Jangan
menghapus baris itu: tanpa dia, `docker compose up` memakai `docker-compose.yml` lokal dan merusak staging.

`reset-data.sh` memasang mode pemeliharaan selama berjalan; penguji yang sedang membuka situs melihat halaman
pemeliharaan sampai selesai.

Tidak ada email sungguhan dan tidak ada cadangan data: data uji selalu bisa dibuat ulang dengan `reset-data.sh`.

## Masalah umum

- **VM hilang atau berhenti sendiri.** Oracle mereklamasi VM Always Free yang menganggur selama 7 hari (CPU,
  jaringan, dan memori di bawah 20%). Tumpukan ini memakai sekitar 1–2 GB dari 12 GB, jadi **minggu tanpa
  pengujian hampir pasti berujung VM direklamasi**. Membuat ulang = langkah 2 sampai 7 (sekitar satu jam),
  data uji ikut dibuat ulang. Akun yang di-upgrade ke *Pay As You Go* tidak terkena aturan ini (periksa halaman
  *Always Free Resources* Oracle), dengan risiko tertagih bila melewati kuota.
- **Browser menolak sertifikat / situs tidak terbuka.** Port 80 atau 443 masih tertutup di salah satu lapis
  langkah 3. Lihat `docker compose -f docker-compose.staging.yml logs caddy`: galat ACME menyebut penyebabnya.
- **Notifikasi langsung atau "Akhiri Semua Sesi" tidak terasa seketika.** Pastikan service `reverb` dan `queue`
  hidup (`docker compose -f docker-compose.staging.yml ps`).
- **Perubahan kode belum terlihat sesudah push.** `deploy.sh` belum dijalankan ulang di VM; aset dan cache
  konfigurasi dibangun saat deploy.
- **`git pull` di `deploy.sh` gagal "Permission denied" di `storage/` atau `bootstrap/cache/`.** Folder itu
  milik pengguna container (`www-data`). Jalankan `sudo chown -R ubuntu storage bootstrap/cache`, lalu
  `deploy.sh` lagi (deploy mengembalikan kepemilikannya).
- **`deploy.sh` berhenti dengan "Isi dulu di .env: ...".** Kunci yang disebut masih kosong; isi lalu ulangi.
- **`deploy.sh` berhenti dengan "Service tidak berjalan: ...".** Lihat log service yang disebut
  (`docker compose logs <nama>`); untuk `caddy` biasanya hash Basic Auth tidak di antara petik tunggal.
