import type { ExportJobRow } from '@/types/reporting'

// Baris riwayat ekspor yang selesai 23 jam sebelum `2026-07-03 09:00 WITA`, masih bisa diunduh.
export const exportJob = (overrides: Partial<ExportJobRow> = {}): ExportJobRow => ({
    id: 1,
    kind: 'export',
    type: 'Rekap Submission',
    type_value: 'submissions',
    format: 'xlsx',
    filters: { year_from: 2026 },
    parameters: 'Sejak 2026 · Semua skema',
    status: 'completed',
    row_count: 12,
    attempts: 1,
    failure_reason: null,
    requested_at: '2026-07-02T10:00:00+08:00',
    completed_at: '2026-07-02T10:00:00+08:00',
    file_is_gone: false,
    file_expires_at: '2026-07-03T10:00:00+08:00',
    download_url: 'https://simpas.test/reporting/export/1/download?signature=x',
    link_expires_at: '2026-07-03T12:00:00+08:00',
    submission_href: null,
    ...overrides,
})
