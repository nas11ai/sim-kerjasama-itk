<script setup lang="ts">
import AppBanner from '@/Components/app/AppBanner.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import WizardFieldList from '@/Components/Submission/Wizard/WizardFieldList.vue'
import type { WizardField, WizardFieldValue } from '@/types/submission-wizard'

// Langkah 2 (Informasi Dasar) dan 6 (Upload Berkas): field Form Engine apa adanya.
defineProps<{
    step: number
    label: string
    fields: WizardField[]
    values: Record<number, WizardFieldValue>
    errors: Record<string, string>
}>()

const emit = defineEmits<{ update: [fieldId: number, value: WizardFieldValue] }>()
</script>

<template>
    <SectionCard :title="`Step ${step} — ${label}`">
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
        <AppBanner v-if="step === 6" tone="info">
            Proposal PDF wajib ada sebelum submit. Berkas lain bersifat opsional kecuali diminta
            skema.
        </AppBanner>
    </SectionCard>
</template>
