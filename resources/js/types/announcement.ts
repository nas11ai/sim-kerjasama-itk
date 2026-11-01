// Props layar pengumuman (fe-v2-design §4.7), cermin Data PHP di
// app/Data/View/Announcement.
import type { Paginated } from '@/types/pagination'
import type { FilterOption } from '@/types/submission-page'

// Sumber: AnnouncementFileData. `url` publik (disk `public`, W15).
export interface AnnouncementFile {
    id: number
    name: string
    url: string
    size: number
}

// Sumber: ManagedAnnouncementRowData. Tanggal ISO 8601.
export interface ManagedAnnouncementRow {
    id: number
    title: string
    audienceLabel: string
    expiresAt: string | null
    expired: boolean
    createdAt: string
    author: string | null
}

// Sumber: AnnouncementFormData. `content` = HTML tersaring server (K6); `expiredOn` = Y-m-d.
export interface AnnouncementForm {
    id: number
    title: string
    content: string
    type: string
    expiredOn: string | null
    targetRoles: string[]
    files: AnnouncementFile[]
}

export type AnnouncementManageFilters = { search: string }

export interface AnnouncementManageIndexPageProps {
    announcements: Paginated<ManagedAnnouncementRow>
    filters: AnnouncementManageFilters
}

export interface AnnouncementFormPageProps {
    announcement: AnnouncementForm | null
    roleOptions: FilterOption[]
}
