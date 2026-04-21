# BC: File Management

**Klasifikasi:** 🟢 Generic Domain  
**Versi:** 2.0  
**Status:** Draft

---

## Responsibility

Layanan upload, storage, dan retrieval file. Consumer tidak perlu tahu storage backend yang dipakai.

| Environment | Provider                       |
| ----------- | ------------------------------ |
| Development | MinIO (Docker, S3-compatible)  |
| Staging     | MinIO (VPS)                    |
| Production  | Cloudflare R2 (no egress cost) |

---

## Activity Diagram

```mermaid
flowchart TD
    START([Consumer request upload]) --> A[Consumer validasi<br/>type & ukuran file]
    A --> B{Valid?}
    B -->|Tidak| ERR[Return error ke consumer]
    B -->|Ya| C[Generate UUID filename]
    C --> D[Upload ke storage backend]
    D --> E{Berhasil?}
    E -->|Tidak| ERR2[Return error ke consumer]
    E -->|Ya| F[Return FileResult<br/>path + signed URL]
    F --> G[Consumer simpan<br/>path & URL ke tabel-nya]

    subgraph DELETE["Soft Delete Flow"]
        H([Consumer request delete]) --> I[Set deleted_at di DB]
        I --> J[Scheduled job<br/>physical delete dari storage]
    end
```

---

## Interface

```php
interface StorageService {
    public function upload(UploadedFile $file, string $path): FileResult;
    public function delete(string $path): void;
    public function getUrl(string $path, ?int $expiresInMinutes = null): string;
}
```

File type validation adalah tanggung jawab consumer context, bukan BC ini.
