<script setup lang="ts">
// Frame: SHARED-02-01..04
import { Link } from '@inertiajs/vue3'
import { CalendarClock } from 'lucide-vue-next'

import EmptyState from '@/Components/app/EmptyState.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import type { Deadline } from '@/types/dashboard'

defineProps<{ deadlines: Deadline[] }>()

function remaining(daysLeft: number): string {
    if (daysLeft < 0) return 'Terlambat'
    if (daysLeft === 0) return 'Hari ini'

    return `${daysLeft} hari lagi`
}
</script>

<template>
    <SectionCard title="Tenggat Mendekat">
        <EmptyState
            v-if="deadlines.length === 0"
            :icon="CalendarClock"
            title="Tidak ada tenggat mendekat"
        />
        <ul v-else class="flex flex-col gap-2">
            <li
                v-for="deadline in deadlines"
                :key="deadline.title"
                data-deadline
                :data-urgent="deadline.urgent"
                :class="[
                    'rounded-md border px-3 py-2 text-sm',
                    deadline.urgent ? 'border-destructive/30 bg-destructive/5' : 'border-border',
                ]"
            >
                <Link
                    v-if="deadline.href"
                    :href="deadline.href"
                    class="font-medium hover:underline"
                >
                    {{ deadline.title }}
                </Link>
                <p v-else class="font-medium">{{ deadline.title }}</p>
                <p
                    :class="[
                        'text-xs',
                        deadline.urgent ? 'text-destructive' : 'text-muted-foreground',
                    ]"
                >
                    {{ remaining(deadline.daysLeft) }}
                </p>
            </li>
        </ul>
    </SectionCard>
</template>
