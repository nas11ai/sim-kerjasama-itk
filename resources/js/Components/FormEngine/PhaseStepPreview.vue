<script setup lang="ts">
import { Badge } from '@/Components/ui/badge'
import type { PreviewStep } from '@/lib/phaseDefinition'

// G3-W8 — Pratinjau tahap: alur langkah apa adanya, baca-saja — urutan, Form, nama tenggat, review,
// dan Formulir Evaluasi terpasang beserta versinya. Isinya isi editor saat ini, belum tentu tersimpan.
defineProps<{ steps: PreviewStep[] }>()
</script>

<template>
    <ol v-if="steps.length > 0" data-preview class="space-y-3">
        <li v-for="(step, index) in steps" :key="step.key" class="rounded-lg border p-4">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs text-muted-foreground">Langkah {{ index + 1 }}</span>
                <p class="text-sm font-medium">{{ step.title }}</p>
                <Badge variant="outline">{{ step.deadlineLabel }}</Badge>
                <Badge :variant="step.needsReview ? 'default' : 'outline'">{{
                    step.needsReview ? 'Perlu Review' : 'Tanpa Review'
                }}</Badge>
            </div>
            <p class="text-xs text-muted-foreground">Akses: {{ step.access }}</p>
            <ul
                v-if="step.needsReview && step.evaluationForms.length > 0"
                class="mt-2 space-y-1 text-sm"
            >
                <li
                    v-for="form in step.evaluationForms.filter((f) => f.isActive)"
                    :key="form.formId"
                >
                    {{ form.title || 'Form evaluasi sudah dihapus dari pustaka' }}
                    <span class="text-muted-foreground"
                        >· {{ form.versionLabel }} ·
                        {{ form.isRequired ? 'wajib' : 'tidak wajib' }}</span
                    >
                </li>
            </ul>
        </li>
    </ol>
    <p v-else class="text-sm text-muted-foreground">Belum ada langkah untuk dipratinjau.</p>
</template>
