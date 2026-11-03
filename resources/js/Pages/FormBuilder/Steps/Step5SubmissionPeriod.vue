<script setup lang="ts">
import { computed } from 'vue'
import { Label } from '@/Components/ui/label'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import { Calendar } from 'lucide-vue-next'

import type { SubmissionPeriod } from '@/types/form-builder'
import { formatDateTime } from '@/lib/format'

// K8g — wizard hanya memilih periode yang sudah ada (dibuat di layar Periode Pengajuan).
// Detail barunya mendapat Batas Submit, dan Batas Evaluasi bila direview, siklus utama
// periode itu (BR-FE-26).
export interface SubmissionPeriodChoice {
    existing_period_id: number | null
}

const props = defineProps<{
    modelValue: SubmissionPeriodChoice
    submissionPeriods: SubmissionPeriod[]
    errors: Record<string, string>
}>()
const emit = defineEmits<{ 'update:modelValue': [value: SubmissionPeriodChoice] }>()

const periodId = computed({
    get: () => props.modelValue.existing_period_id,
    set: (value: number | null) => emit('update:modelValue', { existing_period_id: value }),
})

const selected = computed(() => props.submissionPeriods.find((p) => p.id === periodId.value))
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Periode Pengajuan</CardTitle>
            <CardDescription>
                Pilih periode yang sudah dibuat di layar Periode Pengajuan. Detail tahap memakai
                Batas Submit periode itu, dan Batas Evaluasi bila tahapnya direview.
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="space-y-2">
                <Label for="builder-period">Periode *</Label>
                <Select v-model="periodId">
                    <SelectTrigger id="builder-period">
                        <SelectValue placeholder="Pilih periode pengajuan" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="period in submissionPeriods"
                            :key="period.id"
                            :value="period.id"
                        >
                            {{ period.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p
                    v-if="errors['submission_period.existing_period_id']"
                    class="text-sm text-destructive"
                >
                    {{ errors['submission_period.existing_period_id'] }}
                </p>
            </div>

            <div v-if="selected" class="space-y-1 rounded-lg border p-3">
                <p class="flex items-center gap-2 text-sm font-medium">
                    <Calendar class="h-4 w-4" />
                    {{ selected.name }}
                </p>
                <p
                    v-for="date in selected.submission_dates"
                    :key="date.id"
                    class="text-sm text-muted-foreground"
                >
                    {{ date.label }}: {{ formatDateTime(date.datetime) }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>
