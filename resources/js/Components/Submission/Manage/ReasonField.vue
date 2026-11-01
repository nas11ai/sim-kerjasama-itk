<script setup lang="ts">
import FormField from '@/Components/app/FormField.vue'
import { Textarea } from '@/Components/ui/textarea'

// Alasan/catatan aksi Operator; tersimpan di entri audit Action-nya (BR-RPT-09, W26).
defineProps<{
    id: string
    label: string
    placeholder: string
    error?: string
    required?: boolean
}>()

const model = defineModel<string>({ required: true })
</script>

<template>
    <FormField :id="id" :label="label" :required="required" :error="error">
        <template #default="{ describedBy }">
            <Textarea
                :id="id"
                v-model="model"
                rows="4"
                :placeholder="placeholder"
                :aria-describedby="describedBy"
                :aria-invalid="error ? true : undefined"
            />
        </template>
    </FormField>
</template>
