<script setup lang="ts">
// Frame: OPERATOR-05-01
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

import FilterToolbar from '@/Components/app/FilterToolbar.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PaginationBar from '@/Components/app/PaginationBar.vue'
import MonevBoardTable from '@/Components/Monev/MonevBoardTable.vue'
import { useFilters } from '@/composables/useFilters'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { MonevBoardFilters, MonevManageIndexPageProps } from '@/types/monev'
import type { FilterSelectSpec } from '@/types/submission-page'

const props = defineProps<MonevManageIndexPageProps>()

const { filters } = useFilters('monev.manage.index', props.filters)

const filtered = computed(() => Object.values(props.filters).some((value) => value !== ''))

// W131 — tanpa filter "Status Siklus": keadaan turunan, tidak bisa dipaginasi di server.
const selects = computed((): FilterSelectSpec<Exclude<keyof MonevBoardFilters, 'search'>>[] => [
    { key: 'period', label: 'Periode', allLabel: 'Semua periode', options: props.periodOptions },
    { key: 'scheme', label: 'Skema', allLabel: 'Semua skema', options: props.schemeOptions },
])
</script>

<template>
    <Head title="Manajemen Siklus Monev" />
    <AppLayout>
        <PageHeader
            title="Manajemen Siklus Monev"
            subtitle="Semua pengajuan berstatus Disetujui beserta progres siklus monev-nya."
        />
        <FilterToolbar
            v-model:search="filters.search"
            :selects="selects"
            :values="filters"
            search-placeholder="Cari judul / ketua..."
            search-label="Cari judul atau ketua"
            @select="(key, value) => (filters[key] = value)"
        />
        <MonevBoardTable :rows="rows.data" :filtered="filtered" />
        <PaginationBar :page="rows" />
    </AppLayout>
</template>
