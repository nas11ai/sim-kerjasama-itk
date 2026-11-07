import type { FilterOption } from '@/types/submission-page'

// Sumber: App\Support\Config\ReferenceEntity::$group (W112).
export type ReferenceGroup = 'output' | 'scheme' | 'other'

// Sumber: App\Data\View\SystemConfig\ReferenceItemData.
export interface ReferenceItemData {
    id: number
    name: string
    isActive: boolean | null
    usageCount: number
}

// Sumber: App\Data\View\SystemConfig\ReferenceCategoryData.
export interface ReferenceCategoryData {
    key: string
    label: string
    group: ReferenceGroup
    usedBy: string
    editable: boolean
    deactivatable: boolean
    items: ReferenceItemData[]
}

// Sumber: App\Data\View\SystemConfig\NotificationChannelData (W113).
export interface NotificationChannelData {
    eventType: string
    channels: string[]
    declared: boolean
}

// Sumber: Config\ReferenceEntityController::index.
export interface GeneralPageProps {
    categories: ReferenceCategoryData[]
    notificationChannels: NotificationChannelData[]
    channelOptions: FilterOption[]
}
