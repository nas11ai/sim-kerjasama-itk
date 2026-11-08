<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next'
import { computed } from 'vue'

import { Badge, type BadgeVariants } from '@/Components/ui/badge'
import type { ExportJobState } from '@/lib/exportJob'

// UX pattern 07 — badge selalu berlabel teks; warna hanya penguat.
const props = defineProps<{ state: ExportJobState }>()

const BADGES: Record<
    ExportJobState,
    { label: string; variant: BadgeVariants['variant']; tone?: string }
> = {
    queued: { label: 'Antrian', variant: 'secondary' },
    processing: {
        label: 'Memproses',
        variant: 'outline',
        tone: 'border-amber-300 bg-amber-50 text-amber-800',
    },
    ready: { label: 'Selesai', variant: 'success' },
    failed: { label: 'Gagal', variant: 'destructive' },
    linkExpired: { label: 'Kadaluarsa', variant: 'secondary' },
    gone: { label: 'Kadaluarsa', variant: 'secondary' },
}

const badge = computed(() => BADGES[props.state])
const spinning = computed(() => props.state === 'queued' || props.state === 'processing')
</script>

<template>
    <Badge :variant="badge.variant" :class="['gap-1', badge.tone]">
        <LoaderCircle v-if="spinning" class="size-3 animate-spin" aria-hidden="true" />
        {{ badge.label }}
    </Badge>
</template>
