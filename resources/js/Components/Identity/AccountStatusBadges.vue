<script setup lang="ts">
import { Badge } from '@/Components/ui/badge'
import { useLiveAccountStatus } from '@/composables/useSessionActivity'
import type { AccountStatus } from '@/types/identity'

// W49 — status akun dan status verifikasi adalah dua sumbu DDD, jadi dua badge.
const props = defineProps<{ userId: number; status: AccountStatus }>()

// RT1 — "Sedang aktif" ikut dorongan session.active tanpa reload.
const live = useLiveAccountStatus(
    () => props.userId,
    () => props.status
)
</script>

<template>
    <span class="inline-flex flex-wrap items-center gap-1">
        <Badge :variant="live.active ? 'success' : 'outline'">{{
            live.active ? 'Aktif' : 'Nonaktif'
        }}</Badge>
        <Badge v-if="live.verification" variant="secondary">{{ live.verification.label }}</Badge>
        <!-- BR-IAM-21 — aktivitas sesi ≤ 5 menit -->
        <span
            v-if="live.online"
            class="inline-flex items-center gap-1 text-xs text-muted-foreground"
        >
            <span class="size-2 rounded-full bg-green-500" aria-hidden="true" />Sedang aktif
        </span>
    </span>
</template>
