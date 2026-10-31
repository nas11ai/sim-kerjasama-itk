<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ChevronRight } from 'lucide-vue-next'

import AppBanner from '@/Components/app/AppBanner.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import type { WizardMissingField } from '@/types/submission-wizard'

// Revisi disunting lewat wizard (W18): panel ini hanya menaut langkahnya.
const props = defineProps<{
    submissionId: number
    missingFields: WizardMissingField[]
    canEdit: boolean
}>()

const sections = [
    { step: 2, label: 'Isian pengajuan' },
    { step: 4, label: 'Anggota' },
    { step: 5, label: 'Anggaran' },
    { step: 6, label: 'Berkas' },
]

const wizardUrl = (step: number) =>
    route('user.submissions.wizard', [String(props.submissionId), String(step)])
</script>

<template>
    <SectionCard title="Edit Pengajuan">
        <!-- BR-SM-02 — field wajib diperiksa ulang saat kirim ulang -->
        <AppBanner v-if="missingFields.length > 0" tone="warning">
            {{ missingFields.length }} field wajib masih kosong. Lengkapi sebelum mengirim ulang.
            <ul class="mt-2 list-disc pl-4">
                <li v-for="field in missingFields" :key="field.fieldId">
                    <Link v-if="canEdit" :href="wizardUrl(field.step)" class="underline">{{
                        field.label
                    }}</Link>
                    <span v-else>{{ field.label }}</span>
                </li>
            </ul>
        </AppBanner>

        <nav v-if="canEdit" aria-label="Langkah wizard" class="divide-y rounded-md border">
            <Link
                v-for="section in sections"
                :key="section.step"
                :href="wizardUrl(section.step)"
                class="flex items-center justify-between p-3 text-sm hover:bg-muted"
            >
                {{ section.label }}
                <ChevronRight class="size-4 text-muted-foreground" />
            </Link>
        </nav>
        <p class="text-xs text-muted-foreground">
            Rencana anggaran masih dapat diubah selama revisi.
        </p>
    </SectionCard>
</template>
