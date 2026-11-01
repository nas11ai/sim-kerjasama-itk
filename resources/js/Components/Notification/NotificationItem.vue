<script setup lang="ts">
// Frame: SHARED-02-05
import {
    Bell,
    Calendar,
    ClipboardCheck,
    Clock,
    Download,
    FileText,
    UserCheck,
    type LucideIcon,
} from 'lucide-vue-next'
import { computed } from 'vue'

import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { formatRelative } from '@/lib/format'
import type { NotificationRow } from '@/types/notification'

const props = defineProps<{ notification: NotificationRow }>()

defineEmits<{ open: [notification: NotificationRow]; remove: [notification: NotificationRow] }>()

// Presentasi saja: kategorinya ditentukan backend (W40).
const icons: Record<string, LucideIcon> = {
    review: ClipboardCheck,
    monev: Calendar,
    submission: FileText,
    period: Clock,
    account: UserCheck,
    export: Download,
}
const icon = computed(() => icons[props.notification.category] ?? Bell)
const meta = computed(
    () => `${props.notification.categoryLabel} · ${formatRelative(props.notification.createdAt)}`
)
</script>

<template>
    <div class="flex flex-col gap-3 border-b py-3 last:border-b-0 sm:flex-row sm:items-center">
        <div class="flex min-w-0 flex-1 items-start gap-3">
            <component :is="icon" class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <div class="min-w-0">
                <p :class="['text-sm', notification.isRead ? 'font-normal' : 'font-semibold']">
                    {{ notification.title }}
                </p>
                <p class="line-clamp-2 text-sm text-muted-foreground">
                    {{ notification.message }}
                </p>
                <p class="text-xs text-muted-foreground">{{ meta }}</p>
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <Badge v-if="!notification.isRead">Belum dibaca</Badge>
            <Badge v-else variant="outline">Dibaca</Badge>
            <Button size="sm" variant="outline" @click="$emit('open', notification)">Buka</Button>
            <Button size="sm" variant="ghost" @click="$emit('remove', notification)">Hapus</Button>
        </div>
    </div>
</template>
