<script setup lang="ts">
import { Check, Lock } from 'lucide-vue-next'

import { cn } from '@/lib/utils'
import type { WizardStep } from '@/types/submission-wizard'

const props = withDefaults(
    defineProps<{ steps: WizardStep[]; active: number; disabled?: boolean }>(),
    { disabled: false }
)

const emit = defineEmits<{ select: [number] }>()

type Status = 'active' | 'completed' | 'blocked' | 'idle'

const status = (step: WizardStep): Status => {
    if (step.number === props.active) return 'active'
    if (step.blockedReason !== null) return 'blocked'
    return step.completed ? 'completed' : 'idle'
}

const select = (step: WizardStep) => {
    if (step.number !== props.active) emit('select', step.number)
}
</script>

<template>
    <!-- BR-SM-17 — navigasi bebas; langkah terkunci tetap dibuka untuk membaca alasannya -->
    <nav aria-label="Langkah pengajuan">
        <ol class="flex flex-col gap-1">
            <li v-for="step in steps" :key="step.number">
                <button
                    type="button"
                    :data-status="status(step)"
                    :aria-current="step.number === active ? 'step' : undefined"
                    :title="step.blockedReason ?? undefined"
                    :disabled="disabled && step.number !== active"
                    :class="
                        cn(
                            'flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-50',
                            step.number === active && 'bg-muted font-medium',
                            status(step) === 'blocked' && 'text-muted-foreground'
                        )
                    "
                    @click="select(step)"
                >
                    <span
                        :class="
                            cn(
                                'flex size-6 shrink-0 items-center justify-center rounded-full border text-xs',
                                step.number === active &&
                                    'border-primary bg-primary text-primary-foreground',
                                status(step) === 'completed' && 'border-primary text-primary'
                            )
                        "
                    >
                        <Check v-if="status(step) === 'completed'" class="size-3.5" />
                        <Lock v-else-if="status(step) === 'blocked'" class="size-3.5" />
                        <template v-else>{{ step.number }}</template>
                    </span>
                    <span class="min-w-0">{{ step.label }}</span>
                </button>
            </li>
        </ol>
    </nav>
</template>
