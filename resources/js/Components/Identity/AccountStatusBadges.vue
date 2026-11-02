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
    </span>
</template>
