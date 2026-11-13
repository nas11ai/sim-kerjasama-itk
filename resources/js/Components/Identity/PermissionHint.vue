<script setup lang="ts">
import { Info } from 'lucide-vue-next'

import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/Components/ui/tooltip'

// K5 (2026-11-13) — label pendek tetap di layar; penjelasan dan kode mesinnya di tooltip.
defineProps<{ label: string; description: string; code: string }>()
</script>

<template>
    <span class="inline-flex items-center gap-1">
        <span class="font-medium">{{ label }}</span>
        <TooltipProvider :delay-duration="150">
            <Tooltip>
                <TooltipTrigger
                    type="button"
                    class="rounded-sm text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :aria-label="`Penjelasan: ${label}`"
                >
                    <Info class="size-3.5" aria-hidden="true" />
                </TooltipTrigger>
                <TooltipContent class="max-w-xs">
                    <p>{{ description }}</p>
                    <code class="mt-1 block text-[11px] opacity-80">{{ code }}</code>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </span>
</template>
