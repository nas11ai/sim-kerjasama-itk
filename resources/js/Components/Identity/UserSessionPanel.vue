<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { LogOut } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import { useLiveAccountStatus } from '@/composables/useSessionActivity'
import { formatRelative } from '@/lib/format'
import type { UserDetailData } from '@/types/identity'

// BR-IAM-21 — kehadiran dari sesi; mengakhiri sesi tidak menonaktifkan akun.
const props = defineProps<{ user: UserDetailData; canEnd: boolean }>()

const confirming = ref(false)
const processing = ref(false)

// RT1 — kehadiran ikut dorongan session.active tanpa reload.
const live = useLiveAccountStatus(
    () => props.user.id,
    () => props.user.status
)

const presence = computed(() => {
    if (live.value.online) return 'Sedang aktif'
    const lastActiveAt = live.value.lastActiveAt
    return lastActiveAt
        ? `Terakhir aktif ${formatRelative(lastActiveAt)}`
        : 'Belum ada sesi tercatat'
})

const endSessions = () => {
    processing.value = true
    router.post(
        route('admin.users.sessions.end', props.user.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => (confirming.value = false),
            onFinish: () => (processing.value = false),
        }
    )
}
</script>

<template>
    <SectionCard title="Sesi Login">
        <p class="text-sm">{{ presence }}</p>
        <Button v-if="canEnd && user.status.active" variant="outline" @click="confirming = true"
            ><LogOut class="size-4" /> Akhiri Semua Sesi</Button
        >
        <ConfirmDialog
            v-model:open="confirming"
            title="Akhiri semua sesi?"
            :description="`${user.name} keluar dari semua perangkat dan perlu login lagi. Akunnya tetap aktif.`"
            confirm-label="Akhiri Semua Sesi"
            :processing="processing"
            @confirm="endSessions"
        />
    </SectionCard>
</template>
