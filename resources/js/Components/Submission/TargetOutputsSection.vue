<script setup lang="ts">
import EmptyState from '@/Components/app/EmptyState.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import type { SubmissionTargetOutput } from '@/types/submission-page'

defineProps<{ targetOutputs: SubmissionTargetOutput[] }>()
</script>

<template>
    <SectionCard title="Target Luaran">
        <ul v-if="targetOutputs.length > 0" class="divide-y rounded-md border text-sm">
            <li v-for="(target, index) in targetOutputs" :key="index" class="p-3">
                <p class="font-medium">{{ target.type }}</p>
                <p v-if="target.description" class="text-muted-foreground">
                    {{ target.description }}
                </p>
            </li>
        </ul>
        <EmptyState v-else title="Belum ada target luaran" />
    </SectionCard>
</template>
