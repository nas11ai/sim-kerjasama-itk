<script setup lang="ts">
import { CheckCircle, Clock, LogOut, UserCog, Users, XCircle } from 'lucide-vue-next'
import { computed } from 'vue'

import ActionPanel from '@/Components/app/ActionPanel.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import type { ActionItem } from '@/types/action'
import type {
    DecisionReadiness,
    OperatorAbilities,
    OperatorActionKey,
} from '@/types/submission-manage'

// Ketersediaan dari Policy (`operatorCan`, ADR 0015); hint menjelaskan kapan aksi terbuka.
const props = defineProps<{
    can: OperatorAbilities
    readiness: DecisionReadiness
    hasOwnershipCandidates: boolean
}>()

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
        label: 'Tugaskan Penilai',
        icon: Users,
        hint: 'Tersedia saat status Menunggu Penilaian.',
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
    // BR-REV-05 — Setujui hanya bila siap diputuskan; catatan terbuka menonaktifkannya.
    ...(props.can.approve
        ? [
              item('approve', props.readiness.approvable, {
                  label: 'Setujui',
                  icon: CheckCircle,
                  hint:
                      props.readiness.blockers[0] ?? 'Semua penilai selesai tanpa catatan terbuka.',
              }),
          ]
        : []),
    item('reject', props.can.reject, {
        label: 'Tolak Manual',
        icon: XCircle,
        hint: 'Tersedia saat pengajuan siap diputuskan.',
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
