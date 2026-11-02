<script setup lang="ts">
import { Badge } from '@/Components/ui/badge'
import type { AccountStatus } from '@/types/identity'

// W49 — status akun dan status verifikasi adalah dua sumbu DDD, jadi dua badge.
defineProps<{ status: AccountStatus }>()
</script>

<template>
    <span class="inline-flex flex-wrap items-center gap-1">
        <Badge :variant="status.active ? 'success' : 'outline'">{{
            status.active ? 'Aktif' : 'Nonaktif'
        }}</Badge>
        <Badge v-if="status.verification" variant="secondary">{{
            status.verification.label
        }}</Badge>
        <!-- BR-IAM-21 — aktivitas sesi ≤ 5 menit -->
        <span
            v-if="status.online"
            class="inline-flex items-center gap-1 text-xs text-muted-foreground"
        >
            <span class="size-2 rounded-full bg-green-500" aria-hidden="true" />Sedang aktif
        </span>
    </span>
</template>
