<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { UserX, Users } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import { Button } from '@/Components/ui/button'
import type { DeactivationBlockerData, UserDetailData } from '@/types/identity'

// BR-IAM-10, SM-OWNER-03/04 — ketua pengajuan aktif tak bisa dinonaktifkan; penghalangnya
// dihitung server (DeactivationBlockers), layar hanya menyebutnya.
const props = defineProps<{
    user: UserDetailData
    blockers: DeactivationBlockerData[]
    canDeactivate: boolean
    runningReviews: number
}>()

// W59 — dampak deaktivasi disebut sebelum dikonfirmasi (BR-IAM-05, BR-REV-10).
const impact = computed(() =>
    [
        `${props.user.name} tidak akan bisa masuk dan tidak menerima penugasan baru.`,
        props.runningReviews > 0
            ? `${props.runningReviews} penugasan review berjalan akan tetap ada dan perlu diganti Operator.`
            : null,
        'Sesi yang sedang login akan diakhiri.',
        'Pengajuan, evaluasi, dan riwayatnya tetap tersimpan.',
    ]
        .filter((sentence) => sentence !== null)
        .join(' ')
)

const confirming = ref(false)
const processing = ref(false)

const deactivate = () => {
    processing.value = true
    router.post(
        route('admin.users.deactivate', props.user.id),
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
    <!-- Akun nonaktif: banner IAM-DEACT-03 tinggal di halaman, panel ini tak punya isi. -->
    <div v-if="user.status.active" class="flex flex-col gap-3">
        <template v-if="blockers.length > 0">
            <AppBanner tone="denial">
                {{ user.name }} masih menjadi ketua {{ blockers.length }} pengajuan aktif. Alihkan
                kepemilikannya lebih dulu.
            </AppBanner>
            <template v-for="blocker in blockers" :key="blocker.submissionId">
                <Button
                    v-if="blocker.hasActiveCoInvestigator && blocker.manageUrl"
                    as-child
                    variant="outline"
                >
                    <Link :href="blocker.manageUrl">
                        <Users class="size-4" /> Alihkan Kepemilikan: {{ blocker.title }}
                    </Link>
                </Button>
                <AppBanner v-else-if="!blocker.hasActiveCoInvestigator" tone="warning">
                    Pengajuan "{{ blocker.title }}" belum punya co-investigator aktif, jadi belum
                    ada calon penerima kepemilikan. Tambahkan co-investigator lebih dulu.
                </AppBanner>
            </template>
        </template>
        <AppBanner v-else tone="info">
            User yang masih menjadi ketua pengajuan aktif tidak dapat dinonaktifkan sebelum
            kepemilikannya dialihkan.
        </AppBanner>
        <Button
            v-if="canDeactivate"
            variant="outline"
            :disabled="blockers.length > 0"
            @click="confirming = true"
            ><UserX class="size-4" /> Nonaktifkan</Button
        >
        <ConfirmDialog
            v-model:open="confirming"
            title="Nonaktifkan akun?"
            :description="impact"
            confirm-label="Nonaktifkan"
            destructive
            :processing="processing"
            @confirm="deactivate"
        />
    </div>
</template>
