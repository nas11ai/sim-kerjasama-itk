<script setup lang="ts">
// Frame: RESEARCHER-02-01
import { Head, Link } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { computed } from 'vue'

import FilterToolbar from '@/Components/app/FilterToolbar.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PaginationBar from '@/Components/app/PaginationBar.vue'
import SubmissionTable from '@/Components/Submission/SubmissionTable.vue'
import { Button } from '@/Components/ui/button'
import { useFilters } from '@/composables/useFilters'
import AppLayout from '@/Layouts/AppLayout.vue'
import type {
    FilterSelectSpec,
    SubmissionIndexFilters,
    SubmissionIndexPageProps,
} from '@/types/submission-page'

const props = defineProps<SubmissionIndexPageProps>()

const { filters } = useFilters('user.submissions.index', props.filters)

const filtered = computed(() => Object.values(props.filters).some((value) => value !== ''))

const selects = computed(
    (): FilterSelectSpec<Exclude<keyof SubmissionIndexFilters, 'search'>>[] => [
        { key: 'status', label: 'Status', allLabel: 'Semua status', options: props.statusOptions },
        {
            key: 'period',
            label: 'Periode',
            allLabel: 'Semua periode',
            options: props.periodOptions,
        },
    ]
)
</script>

<template>
    <Head title="Daftar Pengajuan" />
    <AppLayout>
        <PageHeader
            title="Daftar Pengajuan"
            subtitle="Semua pengajuan di mana Anda menjadi ketua atau anggota tim."
        >
            <template v-if="canCreate" #actions>
                <Button as-child>
                    <Link :href="route('user.submissions.create')">
                        <Plus class="size-4" /> Buat Pengajuan Baru
                    </Link>
                </Button>
            </template>
        </PageHeader>
        <FilterToolbar
            v-model:search="filters.search"
            :selects="selects"
            :values="filters"
            search-placeholder="Cari judul pengajuan..."
            search-label="Cari judul pengajuan"
            @select="(key, value) => (filters[key] = value)"
        />
        <SubmissionTable :rows="submissions.data" :filtered="filtered" :can-create="canCreate" />
        <PaginationBar :page="submissions" />
    </AppLayout>
</template>
