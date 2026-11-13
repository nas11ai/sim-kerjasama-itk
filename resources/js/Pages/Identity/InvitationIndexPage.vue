<script setup lang="ts">
// Frame: ADMIN-08-01
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import InvitationForm from '@/Components/Identity/InvitationForm.vue'
import InvitationTable from '@/Components/Identity/InvitationTable.vue'
import IssuedInvitationLink from '@/Components/Identity/IssuedInvitationLink.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { InvitationIndexPageProps, InvitationRowData } from '@/types/identity'

defineProps<InvitationIndexPageProps>()

// W51 — flash native: tidak tersimpan di riwayat, jadi Back tidak menampilkan tautannya lagi.
const page = usePage()
const issuedUrl = computed(() => page.flash.issuedInvitationUrl ?? null)

const formRef = ref<InstanceType<typeof InvitationForm> | null>(null)

const revoking = ref<InvitationRowData | null>(null)
const confirmOpen = ref(false)
const processing = ref(false)

// IAM-REG-16 — konfirmasi menyebut berapa pemakaian yang ikut terhenti.
const revokeImpact = computed(() => {
    const remaining = revoking.value?.remainingUses
    const uses = remaining === null || remaining === undefined ? 'semua' : String(remaining)

    return `Mencabut undangan ini akan menghentikan ${uses} pemakaian yang tersisa. Akun yang sudah terlanjur dibuat tidak terpengaruh.`
})

const askRevoke = (row: InvitationRowData) => {
    revoking.value = row
    confirmOpen.value = true
}

const revoke = () => {
    if (!revoking.value) return
    processing.value = true
    router.delete(route('admin.invitations.revoke', revoking.value.id), {
        preserveScroll: true,
        onSuccess: () => (confirmOpen.value = false),
        onFinish: () => (processing.value = false),
    })
}
</script>

<template>
    <Head title="Undangan Registrasi" />
    <AppLayout>
        <PageHeader
            title="Undangan Registrasi"
            subtitle="Undang peneliti eksternal lewat tautan registrasi, akun langsung aktif tanpa verifikasi NIDN."
            :back-href="route('admin.users.index')"
            back-label="Daftar Pengguna"
        />
        <div class="flex flex-col gap-4">
            <IssuedInvitationLink v-if="issuedUrl" :url="issuedUrl" />
            <InvitationForm
                ref="formRef"
                :organization-options="organizationOptions"
                :preset-options="presetOptions"
            />
            <InvitationTable
                :rows="invitations"
                @revoke="askRevoke"
                @refill="(row) => formRef?.refill(row)"
            />
            <p class="text-xs text-muted-foreground">
                Undangan kedaluwarsa atau kuotanya habis otomatis tidak berlaku lagi dan tidak bisa
                dipakai mendaftar. Mencabut undangan memerlukan konfirmasi.
            </p>
        </div>
        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Cabut undangan?"
            :description="revokeImpact"
            confirm-label="Cabut Undangan"
            destructive
            :processing="processing"
            @confirm="revoke"
        />
    </AppLayout>
</template>
