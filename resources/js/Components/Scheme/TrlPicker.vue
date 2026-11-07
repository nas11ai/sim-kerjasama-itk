<script setup lang="ts">
import type { FilterOption } from '@/types/submission-page'

// BR-SCH-05 — TRL yang boleh dipilih pengajuan berskema ini; chip penuh = terpilih.
defineProps<{ options: FilterOption[] }>()
const selected = defineModel<number[]>({ required: true })

function toggle(value: string) {
    const id = Number(value)
    selected.value = selected.value.includes(id)
        ? selected.value.filter((current) => current !== id)
        : [...selected.value, id]
}
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :aria-pressed="selected.includes(Number(option.value))"
            class="rounded-full border px-3 py-1 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            :class="
                selected.includes(Number(option.value))
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'border-border text-muted-foreground hover:bg-muted'
            "
            @click="toggle(option.value)"
        >
            {{ option.label }}
        </button>
    </div>
    <p class="text-xs text-muted-foreground">Klik untuk memilih — tanda penuh berarti terpilih.</p>
</template>
