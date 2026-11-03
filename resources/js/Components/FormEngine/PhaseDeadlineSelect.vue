<script setup lang="ts">
import { computed } from 'vue'

import { Label } from '@/Components/ui/label'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import type { DeadlineTypeValue, SubmissionDateOption } from '@/types/form-engine'

// BR-FE-26 — satu kolom tenggat detail tahap; hanya tenggat berjenis kolom itu yang ditawarkan.
const props = defineProps<{
    options: SubmissionDateOption[]
    type: DeadlineTypeValue
    label: string
    error?: string
}>()
const model = defineModel<number | null>({ required: true })

const choices = computed(() => props.options.filter((option) => option.type === props.type))
</script>

<template>
    <div class="space-y-2">
        <Label>{{ label }}</Label>
        <Select v-model="model">
            <SelectTrigger>
                <SelectValue :placeholder="`Pilih ${label.replace(' *', '')}`" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-for="date in choices" :key="date.id" :value="date.id">
                    {{ date.label }}
                </SelectItem>
            </SelectContent>
        </Select>
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    </div>
</template>
