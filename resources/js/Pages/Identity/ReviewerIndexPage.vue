<script setup lang="ts">
// Frame: OPERATOR-08-04
import { Head } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import FilterToolbar from '@/Components/app/FilterToolbar.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PaginationBar from '@/Components/app/PaginationBar.vue'
import AppointReviewerDialog from '@/Components/Identity/AppointReviewerDialog.vue'
import ReviewerTable from '@/Components/Identity/ReviewerTable.vue'
import ReviseAppointmentDialog from '@/Components/Identity/ReviseAppointmentDialog.vue'
import { Button } from '@/Components/ui/button'
import { useFilters } from '@/composables/useFilters'
import AppLayout from '@/Layouts/AppLayout.vue'
import type {
    ReviewerIndexFilters,
    ReviewerIndexPageProps,
    ReviewerRowData,
} from '@/types/identity'
import type { FilterSelectSpec } from '@/types/submission-page'

const props = defineProps<ReviewerIndexPageProps>()

const { filters } = useFilters('admin.reviewers.index', props.filters)
const filtered = computed(() => Object.values(props.filters).some((value) => value !== ''))

const selects = computed((): FilterSelectSpec<Exclude<keyof ReviewerIndexFilters, 'search'>>[] => [
    { key: 'type', label: 'Tipe', allLabel: 'Semua tipe', options: props.typeOptions },
    { key: 'standing', label: 'Status', allLabel: 'Semua status', options: props.standingOptions },
])

const appointing = ref(false)
const revising = ref<ReviewerRowData | null>(null)
const reviseOpen = ref(false)

const revise = (row: ReviewerRowData) => {
    revising.value = row
    reviseOpen.value = true
}
</script>

<template>
    <Head title="Manajemen Penilai" />
    <AppLayout>
        <PageHeader
            title="Manajemen Penilai"
            subtitle="Semua penilai beserta periode aktif dan beban tugasnya."
        >
            <template #actions>
                <Button @click="appointing = true"><Plus class="size-4" />Tambah Penilai</Button>
            </template>
        </PageHeader>
        <FilterToolbar
            v-model:search="filters.search"
            :selects="selects"
            :values="filters"
            search-placeholder="Cari nama penilai..."
            search-label="Cari nama penilai"
            @select="(key, value) => (filters[key] = value)"
        />
        <ReviewerTable :rows="reviewers.data" :filtered="filtered" @revise="revise" />
        <PaginationBar :page="reviewers" />
        <div class="mt-4">
            <!-- BR-IAM-11 — tidak ada registrasi mandiri reviewer -->
            <AppBanner tone="warning">
                Menambah penilai memilih pengguna yang sudah terdaftar, tidak ada jalur registrasi
                mandiri untuk penilai. Penilai eksternal harus diundang lebih dulu lewat Undangan
                Registrasi.
            </AppBanner>
        </div>
        <AppointReviewerDialog
            v-model:open="appointing"
            :type-options="typeOptions"
            :candidates="appointmentCandidates"
            :can-manage-invitations="can.manageInvitations"
        />
        <ReviseAppointmentDialog
            v-if="revising"
            :key="revising.id"
            v-model:open="reviseOpen"
            :reviewer="revising"
            :type-options="typeOptions"
        />
    </AppLayout>
</template>
