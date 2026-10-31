<script setup lang="ts">
// Frame: REVIEWER-03-01
import { Head, router } from '@inertiajs/vue3'

import EmptyState from '@/Components/app/EmptyState.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import AssignmentCard from '@/Components/Review/AssignmentCard.vue'
import { Button } from '@/Components/ui/button'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { AssignmentIndexPageProps } from '@/types/review'

defineProps<AssignmentIndexPageProps>()

const segments: { value: AssignmentIndexPageProps['filter']; label: string }[] = [
    { value: 'action', label: 'Perlu Tindakan' },
    { value: 'all', label: 'Semua' },
]

const show = (filter: AssignmentIndexPageProps['filter']) =>
    router.get(
        route('reviewer.assignments.index'),
        { filter },
        { preserveState: true, replace: true }
    )
</script>

<template>
    <Head title="Penugasan Review" />
    <AppLayout>
        <PageHeader
            title="Penugasan Review"
            subtitle="Submission yang ditugaskan kepada Anda untuk dievaluasi."
        >
            <template #actions>
                <div class="inline-flex rounded-md border p-0.5" role="group">
                    <Button
                        v-for="segment in segments"
                        :key="segment.value"
                        size="sm"
                        :data-filter="segment.value"
                        :variant="filter === segment.value ? 'secondary' : 'ghost'"
                        :aria-pressed="filter === segment.value"
                        @click="show(segment.value)"
                    >
                        {{ segment.label }}
                    </Button>
                </div>
            </template>
        </PageHeader>

        <!-- BR-SM-07, BR-REV-10 — hanya penugasan yang masih berlaku yang terdaftar -->
        <section class="flex flex-col gap-3">
            <h2 class="text-sm font-semibold text-muted-foreground">Perlu Tindakan</h2>
            <AssignmentCard v-for="card in needsAction" :key="card.submissionId" :card="card" />
            <EmptyState
                v-if="needsAction.length === 0"
                title="Tidak ada penugasan yang menunggu tindakan Anda"
            />
        </section>

        <section v-if="filter === 'all'" class="mt-8 flex flex-col gap-3">
            <h2 class="text-sm font-semibold text-muted-foreground">Selesai</h2>
            <AssignmentCard v-for="card in done" :key="card.submissionId" :card="card" />
            <EmptyState v-if="done.length === 0" title="Belum ada penugasan yang selesai" />
        </section>
    </AppLayout>
</template>
