<script setup lang="ts">
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import WizardFieldList from '@/Components/Submission/Wizard/WizardFieldList.vue'
import type { WizardField, WizardFieldValue } from '@/types/submission-wizard'

// Langkah 2 (Informasi Dasar) dan 6 (Upload Berkas): field Form Engine apa adanya.
const props = defineProps<{
    step: number
    label: string
    fields: WizardField[]
    values: Record<number, WizardFieldValue>
    errors: Record<string, string>
}>()

const emit = defineEmits<{ update: [fieldId: number, value: WizardFieldValue] }>()

// BR-SM-02 — yang wajib adalah field berkas yang diwajibkan Form (RequiredFieldGuard); E2E-12:
// copy tetap tanpa field itu menyatakan aturan yang tidak ditegakkan.
const requiredFiles = computed(() =>
    props.fields
        .filter((field) => field.type === 'file' && field.required)
        .map((field) => field.label)
)
</script>

<template>
    <SectionCard :title="`Step ${step} · ${label}`">
        <WizardFieldList
            :fields="fields"
            :values="values"
            :errors="errors"
            @update="(id, value) => emit('update', id, value)"
        />
        <!-- BR-FE-11 -->
        <p v-if="step === 2" class="text-xs text-muted-foreground">
            Field bertanda wajib mengikuti konfigurasi Form saat pengajuan ini dibuat. Field yang
            diwajibkan setelah tanggal tersebut tidak menghalangi submit.
        </p>
        <!-- BR-SM-02 -->
        <AppBanner v-if="step === 6 && requiredFiles.length > 0" tone="info">
            Wajib diunggah sebelum submit: {{ requiredFiles.join(', ') }}. Berkas lain bersifat
            opsional.
        </AppBanner>
    </SectionCard>
</template>
