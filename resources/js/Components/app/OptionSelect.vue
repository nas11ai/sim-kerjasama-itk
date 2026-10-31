<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import type { FilterOption } from '@/types/submission-page'

// Select dari daftar opsi berbentuk {value, label}; nilai kosong dibaca sebagai belum dipilih.
defineProps<{
    options: FilterOption[]
    id?: string
    placeholder?: string
    disabled?: boolean
    describedBy?: string
}>()

const model = defineModel<string | null>({ required: true })

const select = (value: unknown) => {
    model.value = typeof value === 'string' && value !== '' ? value : null
}
</script>

<template>
    <Select :model-value="model ?? undefined" :disabled="disabled" @update:model-value="select">
        <SelectTrigger :id="id" class="w-full" :aria-describedby="describedBy">
            <SelectValue :placeholder="placeholder ?? 'Pilih…'" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
