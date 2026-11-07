<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'

import AppBanner from '@/Components/app/AppBanner.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import StatusBadge from '@/Components/app/StatusBadge.vue'
import OutputRow from '@/Components/ResearchOutput/OutputRow.vue'
import { Button } from '@/Components/ui/button'
import type { OutputSubmissionGroup } from '@/types/research-output'

defineProps<{ group: OutputSubmissionGroup }>()
</script>

<template>
    <SectionCard :title="group.title" :description="group.scheme ?? undefined">
        <template #actions>
            <div class="flex shrink-0 items-center gap-2">
                <StatusBadge v-if="group.status.key !== 'approved'" :status="group.status" />
                <!-- W146 — kelayakan menulis dibaca dari backend, tidak dihitung di sini. -->
                <Button v-if="group.access.canWrite" size="sm" as-child>
                    <Link
                        :href="route('research-outputs.create', { submission: group.submissionId })"
                    >
                        <Plus class="size-4" /> Tambah Luaran
                    </Link>
                </Button>
            </div>
        </template>
        <AppBanner v-if="group.access.blockedReason" tone="lock">
            {{ group.access.blockedReason }}
        </AppBanner>
        <p v-if="group.outputs.length === 0" class="text-sm text-muted-foreground">
            Belum ada luaran. Tambah luaran penelitian.
        </p>
        <OutputRow v-for="output in group.outputs" :key="output.id" :output="output" />
    </SectionCard>
</template>
