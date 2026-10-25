<script setup lang="ts">
// Frame: RESEARCHER-02-01
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

import FilterSelect from '@/Components/app/FilterSelect.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PaginationBar from '@/Components/app/PaginationBar.vue'
import SubmissionTable from '@/Components/Submission/SubmissionTable.vue'
import { Input } from '@/Components/ui/input'
import { useFilters } from '@/composables/useFilters'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { SubmissionIndexPageProps } from '@/types/submission-page'

const props = defineProps<SubmissionIndexPageProps>()

const { filters } = useFilters('user.submissions.index', props.filters)

const filtered = computed(() => Object.values(props.filters).some((value) => value !== ''))
</script>

<template>
    <Head title="Daftar Pengajuan" />
    <AppLayout>
        <PageHeader
            title="Daftar Pengajuan"
            subtitle="Semua pengajuan di mana Anda menjadi ketua atau anggota tim."
        />
        <div class="mb-4 flex flex-col gap-2 sm:flex-row">
            <Input
                v-model="filters.search"
                type="search"
                placeholder="Cari judul pengajuan..."
                aria-label="Cari judul pengajuan"
                class="sm:max-w-xs"
            />
            <FilterSelect
                v-model="filters.status"
                :options="statusOptions"
                all-label="Semua status"
                label="Status"
            />
            <FilterSelect
                v-model="filters.period"
                :options="periodOptions"
                all-label="Semua periode"
                label="Periode"
            />
        </div>
        <SubmissionTable :rows="submissions.data" :filtered="filtered" />
        <PaginationBar :page="submissions" />
    </AppLayout>
</template>
