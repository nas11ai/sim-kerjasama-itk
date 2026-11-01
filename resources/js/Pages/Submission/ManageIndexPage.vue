<script setup lang="ts">
// Frame: OPERATOR-02-01
import { Head, Link } from '@inertiajs/vue3'
import { Download } from 'lucide-vue-next'
import { computed } from 'vue'

import FilterSelect from '@/Components/app/FilterSelect.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PaginationBar from '@/Components/app/PaginationBar.vue'
import OperatorSubmissionTable from '@/Components/Submission/Manage/OperatorSubmissionTable.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { useFilters } from '@/composables/useFilters'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { ManageIndexFilters, ManageIndexPageProps } from '@/types/submission-manage'
import type { FilterOption } from '@/types/submission-page'

const props = defineProps<ManageIndexPageProps>()

const { filters } = useFilters('admin.submissions.index', props.filters)

const filtered = computed(() => Object.values(props.filters).some((value) => value !== ''))

interface FilterSelectSpec {
    key: keyof ManageIndexFilters
    label: string
    allLabel: string
    options: FilterOption[]
}

const selects = computed((): FilterSelectSpec[] => [
    { key: 'status', label: 'Status', allLabel: 'Semua status', options: props.statusOptions },
    { key: 'period', label: 'Periode', allLabel: 'Semua periode', options: props.periodOptions },
    { key: 'scheme', label: 'Skema', allLabel: 'Semua skema', options: props.schemeOptions },
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
                    <Link :href="route('exports.index')"><Download class="size-4" /> Export</Link>
                </Button>
            </template>
        </PageHeader>
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
            <Input
                v-model="filters.search"
                type="search"
                placeholder="Cari judul / lead researcher..."
                aria-label="Cari judul atau lead researcher"
                class="sm:max-w-xs"
            />
            <FilterSelect
                v-for="select in selects"
                :key="select.key"
                v-model="filters[select.key]"
                :options="select.options"
                :all-label="select.allLabel"
                :label="select.label"
            />
        </div>
        <OperatorSubmissionTable :rows="submissions.data" :filtered="filtered" />
        <PaginationBar :page="submissions" />
    </AppLayout>
</template>
