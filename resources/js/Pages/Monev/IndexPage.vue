<script setup lang="ts">
// Frame: RESEARCHER-05-01
import { Head, usePage } from '@inertiajs/vue3'
import { Flag } from 'lucide-vue-next'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import MonevSubmissionCard from '@/Components/Monev/MonevSubmissionCard.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { MonevIndexPageProps } from '@/types/monev'

defineProps<MonevIndexPageProps>()

// BR-MON-01/02/08 — penolakan gerbang saat memulai laporan kembali sebagai error Inertia.
const page = usePage()
const gateError = computed(
    () => page.props.errors.form_phase_detail_id ?? page.props.errors.parent_submission_id
)
</script>

<template>
    <Head title="Daftar Monev" />
    <AppLayout>
        <PageHeader
            title="Daftar Monev"
            subtitle="Progres monitoring & evaluasi untuk penelitian Anda yang telah disetujui."
        />
        <AppBanner v-if="gateError" tone="denial" class="mb-4">{{ gateError }}</AppBanner>
        <EmptyState
            v-if="submissions.length === 0"
            :icon="Flag"
            title="Belum ada pengajuan yang disetujui"
        />
        <div v-else class="flex flex-col gap-4">
            <MonevSubmissionCard
                v-for="submission in submissions"
                :key="submission.id"
                :submission="submission"
            />
        </div>
    </AppLayout>
</template>
