<script setup lang="ts">
// Frame: OPERATOR-09-01
import { Head, usePage } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import FilterToolbar from '@/Components/app/FilterToolbar.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PaginationBar from '@/Components/app/PaginationBar.vue'
import PeriodDeleteBlockedDialog from '@/Components/FormEngine/PeriodDeleteBlockedDialog.vue'
import PeriodFormDialog from '@/Components/FormEngine/PeriodFormDialog.vue'
import PeriodTable from '@/Components/FormEngine/PeriodTable.vue'
import { Button } from '@/Components/ui/button'
import { useFilters } from '@/composables/useFilters'
import { usePeriodActions } from '@/composables/usePeriodActions'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { PeriodIndexFilters, PeriodIndexPageProps, PeriodRowData } from '@/types/form-engine'
import type { FilterSelectSpec } from '@/types/submission-page'

const props = defineProps<PeriodIndexPageProps>()

const { filters } = useFilters('admin.submission-periods.index', props.filters)

const filtered = computed(() => Object.values(props.filters).some((value) => value !== ''))

const selects = computed((): FilterSelectSpec<Exclude<keyof PeriodIndexFilters, 'search'>>[] => [
    { key: 'status', label: 'Status', allLabel: 'Semua status', options: props.statusOptions },
    { key: 'year', label: 'Tahun', allLabel: 'Semua tahun', options: props.yearOptions },
])

// BR-SCH-08 / SCH-RULE-13 — flash native hanya ada bila sebuah tenggat mundur (W73).
const page = usePage()
const extended = computed(() => page.flash.deadlineExtended)

const actions = usePeriodActions()
const { target, blockedOpen, deleteOpen, closeOpen, processing } = actions

const formOpen = ref(false)
const editing = ref<PeriodRowData | null>(null)
const openForm = (row: PeriodRowData | null) => {
    editing.value = row
    formOpen.value = true
}
</script>

<template>
    <Head title="Periode Pengajuan" />
    <AppLayout>
        <PageHeader
            title="Periode Pengajuan"
            subtitle="Kelola jendela waktu penerimaan pengajuan proposal."
        >
            <template #actions>
                <Button @click="openForm(null)"><Plus class="size-4" /> Buat Periode Baru</Button>
            </template>
        </PageHeader>
        <AppBanner v-if="extended !== undefined" tone="success" class="mb-4">
            <template v-if="extended > 0">
                Tenggat diperbarui. {{ extended }} peneliti yang masih punya draf diberi tahu.
            </template>
            <template v-else
                >Tenggat diperbarui. Tidak ada peneliti yang perlu diberi tahu.</template
            >
        </AppBanner>
        <FilterToolbar
            v-model:search="filters.search"
            :selects="selects"
            :values="filters"
            search-placeholder="Cari nama periode..."
            search-label="Cari nama periode"
            @select="(key, value) => (filters[key] = value)"
        />
        <PeriodTable
            :rows="periods.data"
            :filtered="filtered"
            :can-view-submissions="can.viewSubmissions"
            @edit="openForm"
            @close="actions.askClose"
            @delete="actions.askDelete"
        />
        <PaginationBar :page="periods" />
        <div class="mt-4 flex flex-col gap-3">
            <!-- K8d + BR-SM-10/13 — "Dibuka" berakhir di Batas Submit; arsip menunggu seluruh tenggat -->
            <AppBanner tone="info">
                Periode berhenti menerima pengajuan baru saat Batas Pengiriman terlewat. Draf yang
                belum dikirim diarsipkan setelah seluruh tenggat periode terlewat atau periode
                ditutup, dan tidak dapat diubah lagi. Menutup periode secara manual memerlukan
                konfirmasi.
            </AppBanner>
            <!-- BR-SCH-08 — hanya perpanjangan tenggat peneliti yang memberi tahu pemilik draft -->
            <AppBanner tone="info">
                Memundurkan Batas Pengiriman atau Batas Revisi akan otomatis memberi tahu peneliti
                yang masih punya draf di sana. Memajukannya, atau menggeser Batas Evaluasi, tidak.
            </AppBanner>
        </div>
        <PeriodDeleteBlockedDialog
            v-model:open="blockedOpen"
            :period="target"
            @close="target && actions.askClose(target)"
        />
        <ConfirmDialog
            v-model:open="deleteOpen"
            title="Hapus periode?"
            :description="`Periode ${target?.name ?? ''} beserta tenggatnya dihapus permanen.`"
            confirm-label="Hapus Periode"
            destructive
            :processing="processing"
            @confirm="actions.confirmDelete"
        />
        <ConfirmDialog
            v-model:open="closeOpen"
            title="Tutup periode?"
            :description="actions.closeImpact.value"
            confirm-label="Tutup Periode"
            destructive
            :processing="processing"
            @confirm="actions.confirmClose"
        />
        <PeriodFormDialog
            v-model:open="formOpen"
            :period="editing"
            :type-options="typeOptions"
            :phase-options="phaseOptions"
        />
    </AppLayout>
</template>
