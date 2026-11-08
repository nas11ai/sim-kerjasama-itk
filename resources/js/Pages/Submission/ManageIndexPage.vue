<script setup lang="ts">
// Frame: OPERATOR-02-01
import { Head, Link } from '@inertiajs/vue3'
import { Download } from 'lucide-vue-next'
import { computed } from 'vue'

import FilterToolbar from '@/Components/app/FilterToolbar.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PaginationBar from '@/Components/app/PaginationBar.vue'
import OperatorSubmissionTable from '@/Components/Submission/Manage/OperatorSubmissionTable.vue'
import { Button } from '@/Components/ui/button'
import { useFilters } from '@/composables/useFilters'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { ManageIndexFilters, ManageIndexPageProps } from '@/types/submission-manage'
import type { FilterSelectSpec } from '@/types/submission-page'

const props = defineProps<ManageIndexPageProps>()

const { filters } = useFilters('admin.submissions.index', props.filters)

const filtered = computed(() => Object.values(props.filters).some((value) => value !== ''))

const selects = computed((): FilterSelectSpec<Exclude<keyof ManageIndexFilters, 'search'>>[] => [
    { key: 'status', label: 'Status', allLabel: 'Semua status', options: props.statusOptions },
    { key: 'period', label: 'Periode', allLabel: 'Semua periode', options: props.periodOptions },
    { key: 'scheme', label: 'Skema', allLabel: 'Semua skema', options: props.schemeOptions },
    { key: 'year', label: 'Tahun', allLabel: 'Semua tahun', options: props.yearOptions },
])
</script>

<template>
    <Head title="Semua Pengajuan" />
    <AppLayout>
        <PageHeader
            title="Semua Pengajuan"
            subtitle="Seluruh submission di sistem — filter lanjutan tersedia."
        >
            <template #actions>
                <Button as-child variant="outline">
                    <Link :href="route('reporting.exports.index')"
                        ><Download class="size-4" /> Export</Link
                    >
                </Button>
            </template>
        </PageHeader>
        <FilterToolbar
            v-model:search="filters.search"
            :selects="selects"
            :values="filters"
            search-placeholder="Cari judul / lead researcher..."
            search-label="Cari judul atau lead researcher"
            @select="(key, value) => (filters[key] = value)"
        />
        <OperatorSubmissionTable :rows="submissions.data" :filtered="filtered" />
        <PaginationBar :page="submissions" />
    </AppLayout>
</template>
