<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import { CircleAlert, MailPlus } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import ActionDialog from '@/Components/app/ActionDialog.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import FormField from '@/Components/app/FormField.vue'
import TenureFields from '@/Components/Identity/TenureFields.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { usePartialSearch } from '@/composables/usePartialSearch'
import type { AppointmentCandidateData, Option } from '@/types/identity'

// BR-IAM-11 — penunjukan memilih user yang sudah ada; `lockedUser` dipakai detail user.
const props = defineProps<{
    typeOptions: Option[]
    candidates?: AppointmentCandidateData[]
    lockedUser?: { userId: number; name: string }
    canManageInvitations?: boolean
}>()
const open = defineModel<boolean>('open', { required: true })

const { search } = usePartialSearch('appointmentCandidates', 'candidate_search')
const picked = ref<AppointmentCandidateData | null>(null)

type AppointForm = {
    user_id: number | null
    reviewer_type: string | null
    start_date: string
    end_date: string
}

const form = useForm<AppointForm>({
    user_id: props.lockedUser?.userId ?? null,
    reviewer_type: props.typeOptions[0]?.value ?? null,
    start_date: '',
    end_date: '',
})

const chosenName = computed(() => props.lockedUser?.name ?? picked.value?.name ?? null)
const noMatch = computed(() => search.value.trim().length >= 2 && props.candidates?.length === 0)

// IAM-REVIEWER-04 — orang yang sudah reviewer diperbarui, bukan diduplikasi.
const pick = (candidate: AppointmentCandidateData) => {
    picked.value = candidate
    form.user_id = candidate.userId
    const current = candidate.currentReviewer
    if (!current) return
    form.reviewer_type = current.type.value
    form.start_date = current.tenureStart
    form.end_date = current.tenureEnd ?? ''
}

const send = () =>
    form.post(route('admin.reviewers.store'), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    })
</script>

<template>
    <ActionDialog
        v-model:open="open"
        :title="lockedUser ? 'Tunjuk sebagai Penilai' : 'Tambah Penilai'"
        :description="chosenName ?? undefined"
        submit-label="Simpan"
        :processing="form.processing"
        :submit-disabled="form.user_id === null"
        @submit="send"
    >
        <template v-if="!lockedUser">
            <FormField id="appoint-search" label="Cari pengguna" :error="form.errors.user_id">
                <Input
                    id="appoint-search"
                    v-model="search"
                    type="search"
                    placeholder="Nama, email, atau NIDN (min. 2 huruf)"
                />
            </FormField>
            <ul
                v-if="candidates && candidates.length > 0"
                class="flex max-h-48 flex-col gap-1 overflow-y-auto"
            >
                <li v-for="candidate in candidates" :key="candidate.userId">
                    <button
                        type="button"
                        class="w-full rounded-md border px-3 py-2 text-left text-sm hover:bg-muted"
                        :class="{ 'border-primary': picked?.userId === candidate.userId }"
                        @click="pick(candidate)"
                    >
                        <span class="font-medium">{{ candidate.name }}</span>
                        <span class="block text-xs text-muted-foreground"
                            >{{ candidate.email }} · {{ candidate.organization ?? '-' }}</span
                        >
                    </button>
                </li>
            </ul>
            <!-- IAM-REVIEWER-03 — penunjukan bukan pembuatan akun -->
            <template v-if="noMatch">
                <EmptyState
                    :icon="CircleAlert"
                    title="Tidak ada pengguna dengan nama itu"
                    description="Penilai dipilih dari pengguna yang sudah terdaftar. Untuk pakar dari luar ITK, terbitkan undangan registrasi lebih dulu."
                />
                <Button v-if="canManageInvitations" as-child variant="outline">
                    <Link :href="route('admin.invitations.index')"
                        ><MailPlus class="size-4" />Buat Undangan Registrasi</Link
                    >
                </Button>
            </template>
            <AppBanner v-if="picked?.currentReviewer" tone="info">
                {{ picked.name }} sudah menjadi penilai. Menyimpan akan memperbarui tipe dan masa
                tugasnya, bukan menambah entri baru.
            </AppBanner>
        </template>
        <TenureFields
            v-model:type="form.reviewer_type"
            v-model:start="form.start_date"
            v-model:end="form.end_date"
            :type-options="typeOptions"
            :previous-type="picked?.currentReviewer?.type.value ?? null"
            :errors="form.errors"
        />
    </ActionDialog>
</template>
