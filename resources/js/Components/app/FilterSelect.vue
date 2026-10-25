<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import type { FilterOption } from '@/types/submission-page'

defineProps<{ options: FilterOption[]; allLabel: string; label: string }>()

const model = defineModel<string>({ required: true })

// SelectItem tidak menerima nilai kosong, jadi "semua" memakai penanda sendiri.
const ALL = '__all'

const select = (value: unknown) => {
    model.value = typeof value === 'string' && value !== ALL ? value : ''
}
</script>

<template>
    <Select :model-value="model === '' ? ALL : model" @update:model-value="select">
        <SelectTrigger class="w-full sm:w-48" :aria-label="label">
            <SelectValue />
        </SelectTrigger>
        <SelectContent>
            <SelectItem :value="ALL">{{ allLabel }}</SelectItem>
            <SelectItem v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
