<script setup lang="ts">
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import { Input } from '@/Components/ui/input'
import type { Option } from '@/types/identity'

// BR-IAM-11, BR-REV-09 — tipe dan masa tugas; dipakai penunjukan dan "Edit Periode".
const props = defineProps<{
    typeOptions: Option[]
    previousType: string | null
    errors: Partial<Record<'reviewer_type' | 'start_date' | 'end_date', string>>
}>()

const type = defineModel<string | null>('type', { required: true })
const start = defineModel<string>('start', { required: true })
const end = defineModel<string>('end', { required: true })

// IAM-REVIEWER-05 — role tipe lama dicabut, termasuk reviewers.view-scores-others.
const losesScores = computed(() => props.previousType === 'internal' && type.value === 'external')
</script>

<template>
    <FormField id="tenure-type" label="Tipe" required :error="errors.reviewer_type">
        <template #default="{ describedBy }">
            <OptionSelect
                id="tenure-type"
                v-model="type"
                :options="typeOptions"
                :described-by="describedBy"
            />
        </template>
    </FormField>
    <AppBanner v-if="losesScores" tone="warning">
        Mengubah tipe menjadi Eksternal akan mencabut kemampuan melihat skor penilai lain. Evaluasi
        yang sudah ditulis tidak berubah.
    </AppBanner>
    <div class="grid gap-4 sm:grid-cols-2">
        <FormField id="tenure-start" label="Mulai" required :error="errors.start_date">
            <template #default="{ describedBy }">
                <Input
                    id="tenure-start"
                    v-model="start"
                    type="date"
                    :aria-describedby="describedBy"
                />
            </template>
        </FormField>
        <FormField id="tenure-end" label="Berakhir" required :error="errors.end_date">
            <template #default="{ describedBy }">
                <Input id="tenure-end" v-model="end" type="date" :aria-describedby="describedBy" />
            </template>
        </FormField>
    </div>
</template>
