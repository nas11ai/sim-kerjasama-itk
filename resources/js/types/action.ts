import type { LucideIcon } from 'lucide-vue-next'

// Satu tombol di ActionPanel. `available` dan `hint` berasal dari objek `can` backend.
export interface ActionItem {
    key: string
    label: string
    icon?: LucideIcon
    href?: string
    onSelect?: () => void
    available: boolean
    hint: string
}
