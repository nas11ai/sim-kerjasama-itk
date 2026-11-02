// Props `Dashboard/IndexPage` (SHARED-02-01..04), cermin Data PHP di
// app/Data/View/Dashboard. Tanggal ISO 8601.
import type { AnnouncementSummary } from '@/types/announcement'

// Sumber: KpiCardData. `value` sudah teks tampilan ("3", "2 hari", "—").
export interface KpiCard {
    key: string
    label: string
    value: string
    meta: string | null
    href: string | null
}

// Sumber: DashboardSectionData. Section dikirim backend menurut permission (§4.4 K4).
export interface DashboardSection {
    key: 'researcher' | 'reviewer' | 'management'
    title: string
    cards: KpiCard[]
}

// Sumber: PendingWorkData — banner antrian Operator.
export interface PendingWork {
    awaitingReviewer: number
    awaitingVerification: number
}

// Sumber: DeadlineData. `urgent` = daysLeft <= URGENT_DAYS (W43).
export interface Deadline {
    title: string
    closesAt: string
    daysLeft: number
    urgent: boolean
    href: string | null
}

// Sumber: ActivityData — entri jejak audit dalam cakupan pembaca (BR-RPT-05).
export interface Activity {
    description: string
    actorName: string | null
    at: string
}

export interface DashboardIndexPageProps {
    subtitle: string | null
    sections: DashboardSection[]
    pending: PendingWork | null
    activities: Activity[]
    deadlines: Deadline[]
    announcements: AnnouncementSummary[]
    profileCompletion: ProfileCompletion | null
}

// Sumber: ProfileCompletionData — BR-IAM-03; null bila bukan pembuat pengajuan atau sudah lengkap.
export interface ProfileCompletion {
    missing: string[]
}
