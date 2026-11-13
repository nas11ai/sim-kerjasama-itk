<script setup lang="ts">
// Frame: OPERATOR-08-02
import { Head, router } from '@inertiajs/vue3'
import { Check, PencilLine, UserCheck, X } from 'lucide-vue-next'
import { ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import CorrectNidnDialog from '@/Components/Identity/CorrectNidnDialog.vue'
import RejectVerificationDialog from '@/Components/Identity/RejectVerificationDialog.vue'
import VerificationCard from '@/Components/Identity/VerificationCard.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { VerificationCardData, VerificationIndexPageProps } from '@/types/identity'

defineProps<VerificationIndexPageProps>()

const rejecting = ref<VerificationCardData | null>(null)
const rejectOpen = ref(false)
const correcting = ref<VerificationCardData | null>(null)
const correctOpen = ref(false)
const verifying = ref<number | null>(null)

const openReject = (card: VerificationCardData) => {
    rejecting.value = card
    rejectOpen.value = true
}

const openCorrect = (card: VerificationCardData) => {
    correcting.value = card
    correctOpen.value = true
}

// BR-IAM-13 — verifikasi berlaku juga untuk akun yang pernah ditolak (IAM-VERIFY-10).
const verify = (card: VerificationCardData) => {
    verifying.value = card.profileId
    router.post(
        route('admin.verifications.verify', card.profileId),
        {},
        { preserveScroll: true, onFinish: () => (verifying.value = null) }
    )
}
</script>

<template>
    <Head title="Antrian Verifikasi NIDN" />
    <AppLayout>
        <PageHeader
            title="Antrian Verifikasi NIDN"
            subtitle="Pendaftar baru menunggu verifikasi, periksa kecocokan NIDN sebelum menyetujui."
            :back-href="route('admin.users.index')"
            back-label="Daftar User"
        >
            <template #badge>
                <Badge variant="secondary">{{ pending.length }} menunggu</Badge>
            </template>
        </PageHeader>
        <div class="simpas-form-narrow flex flex-col gap-4">
            <VerificationCard v-for="card in pending" :key="card.profileId" :card="card">
                <template #actions>
                    <Button size="sm" :disabled="verifying === card.profileId" @click="verify(card)"
                        ><Check class="size-4" />Verifikasi</Button
                    >
                    <Button size="sm" variant="outline" @click="openReject(card)"
                        ><X class="size-4" />Tolak</Button
                    >
                </template>
            </VerificationCard>
            <EmptyState
                v-if="pending.length === 0"
                :icon="UserCheck"
                title="Tidak ada pendaftar yang menunggu verifikasi"
            />
            <AppBanner tone="info">
                Menolak pendaftaran wajib menyertakan alasan, alasan dikirimkan ke pendaftar.
                Setelah diverifikasi, pendaftar dapat mulai membuat pengajuan.
            </AppBanner>
            <!-- BR-IAM-12 — akun undangan tidak pernah berstatus pending (IAM-REG-14) -->
            <AppBanner tone="warning">
                Antrian ini hanya memuat pendaftar jalur mandiri. Peneliti yang masuk lewat undangan
                tidak punya NIDN untuk diperiksa dan akunnya sudah aktif sejak mendaftar.
            </AppBanner>
            <section v-if="rejected.length > 0" class="flex flex-col gap-4">
                <h2 class="text-lg font-semibold">Ditolak</h2>
                <VerificationCard v-for="card in rejected" :key="card.profileId" :card="card">
                    <template #actions>
                        <Button size="sm" variant="outline" @click="openCorrect(card)"
                            ><PencilLine class="size-4" />Koreksi NIDN</Button
                        >
                        <Button
                            size="sm"
                            :disabled="verifying === card.profileId"
                            @click="verify(card)"
                            ><Check class="size-4" />Verifikasi</Button
                        >
                    </template>
                </VerificationCard>
            </section>
        </div>
        <RejectVerificationDialog
            v-if="rejecting"
            :key="rejecting.profileId"
            v-model:open="rejectOpen"
            :card="rejecting"
        />
        <CorrectNidnDialog
            v-if="correcting"
            :key="`${correcting.profileId}-${correcting.nidn}`"
            v-model:open="correctOpen"
            :profile-id="correcting.profileId"
            :current-nidn="correcting.nidn"
        />
    </AppLayout>
</template>
