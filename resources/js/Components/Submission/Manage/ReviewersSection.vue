<script setup lang="ts">
import EmptyState from '@/Components/app/EmptyState.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import type { ManagedReviewer } from '@/types/submission-manage'

defineProps<{
    reviewers: ManagedReviewer[]
    activeCount: number
    minimum: number
    canReplace: boolean
}>()

const emit = defineEmits<{ replace: [number] }>()

const typeLabel = (type: string) => (type === 'external' ? 'Eksternal' : 'Internal')
</script>

<template>
    <SectionCard title="Reviewer Ditugaskan">
        <!-- BR-REV-01 — penugasan bertahap; minimum milik skema -->
        <p class="text-sm font-medium">{{ activeCount }} dari minimal {{ minimum }} reviewer</p>
        <ul v-if="reviewers.length > 0" class="flex flex-col divide-y">
            <li
                v-for="reviewer in reviewers"
                :key="reviewer.reviewerId"
                class="flex items-start justify-between gap-3 py-2 first:pt-0 last:pb-0"
            >
                <div class="min-w-0 text-sm">
                    <p class="font-medium" :class="{ 'text-muted-foreground': reviewer.replaced }">
                        {{ reviewer.name }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        NIDN {{ reviewer.nidn ?? '—' }} · {{ typeLabel(reviewer.type) }} ·
                        {{ reviewer.evaluationLabel }}
                    </p>
                    <!-- BR-IAM-05, IAM-DEACT-11 — penugasan tetap berjalan sampai Operator menggantinya -->
                    <Badge
                        v-if="!reviewer.replaced && !reviewer.accountActive"
                        variant="destructive"
                        class="mt-1"
                        >Akun nonaktif — perlu diganti</Badge
                    >
                </div>
                <!-- BR-REV-10 — yang digantikan tinggal sejarah, tidak diganti lagi -->
                <Badge v-if="reviewer.replaced" variant="outline">Digantikan</Badge>
                <Button
                    v-else-if="canReplace"
                    variant="outline"
                    size="sm"
                    @click="emit('replace', reviewer.reviewerId)"
                >
                    Ganti
                </Button>
            </li>
        </ul>
        <EmptyState v-else title="Belum ada reviewer ditugaskan." />
    </SectionCard>
</template>
