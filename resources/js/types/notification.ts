// Props layar notifikasi (SHARED-02-05), cermin Data PHP di
// app/Data/View/Notification.
import type { Paginated } from '@/types/pagination'

// W40 — chip filter wireframe; filter lain di URL dibaca server sebagai `all`.
export type NotificationFilter = 'all' | 'unread' | 'review' | 'monev'

// Sumber: NotificationRowData. `category` = NotificationCategory, `createdAt` ISO 8601.
export interface NotificationRow {
    id: string
    title: string
    message: string
    category: string
    categoryLabel: string
    createdAt: string
    isRead: boolean
}

export interface NotificationIndexPageProps {
    notifications: Paginated<NotificationRow>
    filter: NotificationFilter
    unreadCount: number
}
