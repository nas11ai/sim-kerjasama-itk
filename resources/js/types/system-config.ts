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

// Sumber: App\Data\View\SystemConfig\OrganizationNodeData (urut depth-first).
export interface OrganizationNodeData {
    id: number
    name: string
    typeLabel: string
    isActive: boolean
    parentId: number | null
    depth: number
    hasChildren: boolean
}

// Sumber: App\Data\Result\OrganizationCensusData.
export interface OrganizationCensusData {
    directProfiles: number
    directActiveUsers: number
    subtreeProfiles: number
    subtreeActiveUsers: number
    unitsWithActiveUsers: number
    pendingInvitations: number
}

// Sumber: App\Data\View\SystemConfig\OrganizationUnitData.
export interface OrganizationUnitData {
    id: number
    name: string
    type: string
    typeLabel: string
    isActive: boolean
    parentId: number | null
    census: OrganizationCensusData
    canDeactivate: boolean
    canDelete: boolean
    // BR-IAM-22 — nonaktif dan induknya aktif (atau tanpa induk).
    canReactivate: boolean
    revivableInvitations: number
}

// Sumber: App\Queries\Identity\OrganizationMembers (W120).
export interface MemberCandidate {
    value: string
    label: string
    hint: string
    organizationId: number
}

// Sumber: Identity\OrganizationController::index.
export interface OrganizationPageProps {
    tree: OrganizationNodeData[]
    selected: OrganizationUnitData | null
    typeOptions: FilterOption[]
    parentOptions: FilterOption[]
    // Prop opsional: hanya ada setelah partial reload pencarian anggota.
    memberCandidates?: MemberCandidate[]
}

// Sumber: Config\ReferenceEntityController::index.
export interface GeneralPageProps {
    categories: ReferenceCategoryData[]
    notificationChannels: NotificationChannelData[]
    channelOptions: FilterOption[]
}
