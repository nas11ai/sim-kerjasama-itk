<script setup lang="ts">
// Frame: SHARED-02-01..04
import { History } from 'lucide-vue-next'

import EmptyState from '@/Components/app/EmptyState.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { formatRelative } from '@/lib/format'
import type { Activity } from '@/types/dashboard'

defineProps<{ activities: Activity[] }>()
</script>

<template>
    <SectionCard title="Aktivitas Terbaru">
        <EmptyState v-if="activities.length === 0" :icon="History" title="Belum ada aktivitas" />
        <ol v-else class="flex flex-col gap-3">
            <li v-for="(activity, index) in activities" :key="index" class="text-sm">
                <p>{{ activity.description }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ activity.actorName ?? 'Sistem' }} · {{ formatRelative(activity.at) }}
                </p>
            </li>
        </ol>
    </SectionCard>
</template>
