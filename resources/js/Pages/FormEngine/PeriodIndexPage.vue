<script setup lang="ts">
// Frame: OPERATOR-09-01
import { Head } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import FilterToolbar from '@/Components/app/FilterToolbar.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PaginationBar from '@/Components/app/PaginationBar.vue'
import PeriodTable from '@/Components/FormEngine/PeriodTable.vue'
import { Button } from '@/Components/ui/button'
import { useFilters } from '@/composables/useFilters'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { PeriodIndexFilters, PeriodIndexPageProps } from '@/types/form-engine'
import type { FilterSelectSpec } from '@/types/submission-page'

const props = defineProps<PeriodIndexPageProps>()

const { filters } = useFilters('admin.submission-periods.index', props.filters)

const filtered = computed(() => Object.values(props.filters).some((value) => value !== ''))

const selects = computed((): FilterSelectSpec<Exclude<keyof PeriodIndexFilters, 'search'>>[] => [
    { key: 'status', label: 'Status', allLabel: 'Semua status', options: props.statusOptions },
    { key: 'year', label: 'Tahun', allLabel: 'Semua tahun', options: props.yearOptions },
])
</script>

<template>
    <Head title="Periode Pengajuan" />
    <AppLayout>
        <PageHeader
            title="Periode Pengajuan"
            subtitle="Kelola jendela waktu penerimaan pengajuan proposal."
        >
            <template #actions>
                <Button><Plus class="size-4" /> Buat Periode Baru</Button>
            </template>
        </PageHeader>
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
        />
        <PaginationBar :page="periods" />
        <div class="mt-4 flex flex-col gap-3">
            <!-- BR-SM-10/13 — draft terarsip saat seluruh tenggat lewat atau periode ditutup (W67) -->
            <AppBanner tone="info">
                Periode ditutup otomatis saat seluruh tenggatnya terlewat. Draft yang belum disubmit
                ketika periode ditutup akan diarsipkan dan tidak dapat diedit lagi. Menutup periode
                secara manual memerlukan konfirmasi.
            </AppBanner>
            <!-- BR-SCH-08 — hanya perpanjangan yang memberi tahu pemilik draft -->
            <AppBanner tone="info">
                Memundurkan salah satu tanggal periode akan otomatis memberi tahu peneliti yang
                masih punya draft di sana. Memajukannya tidak — tidak ada kesempatan baru yang
                diberikan.
            </AppBanner>
        </div>
    </AppLayout>
</template>
