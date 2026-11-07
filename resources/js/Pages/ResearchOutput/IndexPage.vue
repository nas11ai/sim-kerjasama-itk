<script setup lang="ts">
// Frame: RESEARCHER-06-01
import { Head } from '@inertiajs/vue3'
import { Folder } from 'lucide-vue-next'

import EmptyState from '@/Components/app/EmptyState.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import OutputGroupCard from '@/Components/ResearchOutput/OutputGroupCard.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { ResearchOutputIndexPageProps } from '@/types/research-output'

defineProps<ResearchOutputIndexPageProps>()
</script>

<template>
    <Head title="Luaran Penelitian" />
    <AppLayout>
        <PageHeader
            title="Luaran Penelitian"
            subtitle="Luaran dikelompokkan per pengajuan yang telah disetujui."
        />
        <EmptyState
            v-if="groups.length === 0"
            :icon="Folder"
            title="Belum ada pengajuan yang disetujui."
        />
        <div v-else class="flex flex-col gap-4">
            <OutputGroupCard v-for="group in groups" :key="group.submissionId" :group="group" />
        </div>
    </AppLayout>
</template>
