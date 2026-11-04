<script setup lang="ts">
import { computed } from 'vue'

import ActiveStatusSelect from '@/Components/app/ActiveStatusSelect.vue'
import FormField from '@/Components/app/FormField.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import type { PhaseGeneral } from '@/lib/phaseDefinition'

// W94 — frame 08-09 tidak punya isian judul, padahal `title` wajib; kartu ini menambahkannya.
defineProps<{ errors: Record<string, string> }>()
const general = defineModel<PhaseGeneral>({ required: true })

const active = computed({
    get: () => general.value.isActive,
    set: (isActive: boolean) => (general.value = { ...general.value, isActive }),
})
const update = (key: 'title' | 'description', value: string | number) =>
    (general.value = { ...general.value, [key]: String(value) })
</script>

<template>
    <SectionCard title="Tahap">
        <FormField id="phase-title" label="Judul Tahap" required :error="errors.title">
            <Input
                id="phase-title"
                :model-value="general.title"
                maxlength="255"
                @update:model-value="(value) => update('title', value)"
            />
        </FormField>
        <FormField id="phase-description" label="Deskripsi" :error="errors.description">
            <Textarea
                id="phase-description"
                :model-value="general.description"
                rows="2"
                @update:model-value="(value) => update('description', value)"
            />
        </FormField>
        <FormField id="phase-status" label="Status" :error="errors.is_active">
            <ActiveStatusSelect id="phase-status" v-model="active" />
        </FormField>
    </SectionCard>
</template>
