import { formatDate } from '@/lib/format'
import type {
    MonevBoardStatus,
    MonevCycle,
    MonevCycleState,
    PhaseNode,
    PhaseNodeState,
} from '@/types/monev'

const NODE_STATE: Record<MonevCycleState, PhaseNodeState> = {
    done: 'done',
    submitted: 'done',
    open: 'active',
    closed: 'waiting',
    locked: 'locked',
    frozen: 'locked',
}

function metaOf(cycle: MonevCycle, previous: string): string {
    switch (cycle.state) {
        case 'open':
            return `Tenggat ${formatDate(cycle.deadline)}`
        case 'submitted':
        case 'done':
            return `Dikirim ${formatDate(cycle.submittedAt)}`
        case 'closed':
            return 'Ditutup, tenggat lewat'
        case 'locked':
            return `Terbuka setelah ${previous} selesai`
        case 'frozen':
            return 'Dibekukan'
    }
}

/**
 * Node progres RESEARCHER-05-01. Hanya pemetaan tampilan: keadaannya dihitung backend.
 * W131 — node Pengajuan tanpa tanggal persetujuan (tidak ada satu sumbernya).
 */
export function phaseNodes(cycles: MonevCycle[]): PhaseNode[] {
    return [
        { label: 'Pengajuan', meta: 'Disetujui', state: 'done' },
        ...cycles.map((cycle, i) => ({
            label: cycle.name,
            meta: metaOf(cycle, cycles[i - 1]?.name ?? 'Pengajuan'),
            state: NODE_STATE[cycle.state],
        })),
    ]
}

type BadgeVariant = 'default' | 'secondary' | 'outline'

// Cermin label App\Enums\MonevBoardStatus; titik menandai status yang menunggu tindakan.
export const BOARD_STATUS: Record<
    MonevBoardStatus,
    { label: string; variant: BadgeVariant; dot: string | null }
> = {
    report_in: { label: 'Laporan Masuk', variant: 'default', dot: 'bg-primary-foreground' },
    awaiting_report: { label: 'Menunggu Laporan', variant: 'outline', dot: 'bg-amber-500' },
    not_opened: { label: 'Belum Aktif', variant: 'outline', dot: null },
    completed: { label: 'Selesai', variant: 'secondary', dot: null },
}
