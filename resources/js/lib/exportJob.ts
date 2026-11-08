import type { ExportJobRow } from '@/types/reporting'

export type ExportJobState = 'queued' | 'processing' | 'ready' | 'linkExpired' | 'gone' | 'failed'

const passed = (iso: string | null, now: Date): boolean =>
    iso !== null && now.getTime() >= new Date(iso).getTime()

// Satu-satunya logika waktu ekspor di FE: tautan dan berkas bisa basi saat layar dibiarkan
// terbuka. Berkas terhapus (BR-RPT-12) menang atas tautan basi (BR-RPT-03).
export function jobState(job: ExportJobRow, now: Date): ExportJobState {
    if (job.status === 'failed') return 'failed'
    if (job.status === 'queued' || job.status === 'processing') return job.status
    if (job.file_is_gone || passed(job.file_expires_at, now)) return 'gone'
    if (job.download_url === null || passed(job.link_expires_at, now)) return 'linkExpired'

    return 'ready'
}

// BR-RPT-12 UX — sisa jam penuh hingga berkas dihapus.
export function hoursLeft(job: ExportJobRow, now: Date): number {
    if (job.file_expires_at === null) return 0

    return Math.max(
        0,
        Math.floor((new Date(job.file_expires_at).getTime() - now.getTime()) / 3_600_000)
    )
}
