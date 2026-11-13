import type { AuditEntryRow } from '@/types/reporting'

// Baris contoh frame OPERATOR-07-03 (W155).
export const auditEntry = (overrides: Partial<AuditEntryRow> = {}): AuditEntryRow => ({
    id: 1,
    createdAt: '2026-07-03T09:41:00+08:00',
    subject: 'Submission: Biochar Kaltim',
    action: 'Status berubah: Direview',
    actor: 'Siti Aminah (Operator)',
    ipAddress: '10.10.2.14',
    old: null,
    new: null,
    ...overrides,
})
