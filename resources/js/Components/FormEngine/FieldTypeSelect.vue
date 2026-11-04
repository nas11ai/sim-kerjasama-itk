<script setup lang="ts">
import { computed } from 'vue'

import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import type { FieldTypeOption } from '@/types/form-engine'

// Isian Tipe dialog field (form maupun form evaluasi); `locked` dari jawaban yang ada (BR-FE-23).
defineProps<{ id: string; options: FieldTypeOption[]; locked: boolean; hint?: string }>()
const fieldTypeId = defineModel<number>({ required: true })

const value = computed({
    get: () => String(fieldTypeId.value),
    set: (next: string | null) => {
        if (next !== null) fieldTypeId.value = Number(next)
    },
})
</script>

<template>
    <FormField :id="id" label="Tipe" :hint="hint">
        <template #default="{ describedBy }">
            <OptionSelect
                :id="id"
                v-model="value"
                :options="options"
                :disabled="locked"
                :described-by="describedBy"
            />
        </template>
    </FormField>
</template>
