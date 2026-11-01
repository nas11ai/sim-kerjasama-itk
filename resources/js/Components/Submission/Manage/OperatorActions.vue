<script setup lang="ts">
import { Clock, LogOut, UserCog, Users, XCircle } from 'lucide-vue-next'
import { computed } from 'vue'

import ActionPanel from '@/Components/app/ActionPanel.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import type { ActionItem } from '@/types/action'
import type { OperatorAbilities, OperatorActionKey } from '@/types/submission-manage'

// Ketersediaan dari Policy (`operatorCan`, ADR 0015); hint menjelaskan kapan aksi terbuka.
const props = defineProps<{ can: OperatorAbilities; hasOwnershipCandidates: boolean }>()

const emit = defineEmits<{ select: [OperatorActionKey] }>()

const item = (
    key: OperatorActionKey,
    available: boolean,
    rest: Omit<ActionItem, 'key' | 'available' | 'onSelect'>
): ActionItem => ({
    key,
    available,
    onSelect: () => emit('select', key),
    ...rest,
})

const actions = computed<ActionItem[]>(() => [
    item('assign', props.can.assignReviewers, {
        label: 'Tugaskan Reviewer',
        icon: Users,
        hint: 'Tersedia saat status Disubmit.',
    }),
    item('extend', props.can.grantOverride, {
        label: 'Perpanjang Tenggat',
        icon: Clock,
        hint: 'Jika ada tenggat yang terlewat.',
    }),
    // BR-SM-09 — penerimanya hanya co-investigator aktif.
    item('transfer', props.can.transferOwnership && props.hasOwnershipCandidates, {
        label: 'Alihkan Kepemilikan',
        icon: UserCog,
        hint: 'Perlu anggota tim inti yang aktif.',
    }),
    item('reject', props.can.reject, {
        label: 'Tolak Manual',
        icon: XCircle,
        hint: 'Tersedia saat status Direview.',
    }),
    item('withdraw', props.can.withdraw, {
        label: 'Tarik Pengajuan',
        icon: LogOut,
        hint: 'Tersedia saat status Disetujui.',
    }),
])
</script>

<template>
    <div class="flex flex-col gap-4">
        <ActionPanel :actions="actions" />
        <!-- BR-SM-05, BR-SM-11 -->
        <AppBanner tone="lock">
            Pengajuan yang sudah Disetujui, Ditolak, atau Ditarik tidak dapat diubah lagi.
        </AppBanner>
    </div>
</template>
