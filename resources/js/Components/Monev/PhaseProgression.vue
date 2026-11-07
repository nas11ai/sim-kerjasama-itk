<script setup lang="ts">
import { Check, Clock, Lock } from 'lucide-vue-next'

import { cn } from '@/lib/utils'
import type { PhaseNode } from '@/types/monev'

defineProps<{ nodes: PhaseNode[] }>()
</script>

<template>
    <ol class="flex flex-wrap gap-x-6 gap-y-3" aria-label="Progres siklus monev">
        <li
            v-for="node in nodes"
            :key="node.label"
            :data-state="node.state"
            :aria-current="node.state === 'active' ? 'step' : undefined"
            class="flex min-w-36 items-start gap-2"
        >
            <span
                :class="
                    cn(
                        'mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border text-xs',
                        node.state === 'done' && 'border-primary text-primary',
                        node.state === 'active' &&
                            'border-primary bg-primary text-primary-foreground',
                        node.state !== 'done' && node.state !== 'active' && 'text-muted-foreground'
                    )
                "
            >
                <Check v-if="node.state === 'done'" class="size-3.5" />
                <Lock v-else-if="node.state === 'locked'" class="size-3.5" />
                <Clock v-else class="size-3.5" />
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-medium">{{ node.label }}</span>
                <span class="block text-xs text-muted-foreground">{{ node.meta }}</span>
            </span>
        </li>
    </ol>
</template>
