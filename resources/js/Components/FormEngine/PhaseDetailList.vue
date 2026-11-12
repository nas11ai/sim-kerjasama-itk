<script setup lang="ts">
import { Plus } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import RowErrors from '@/Components/app/RowErrors.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import EvaluationSection from '@/Components/FormEngine/EvaluationSection.vue'
import FieldRowActions from '@/Components/FormEngine/FieldRowActions.vue'
import PhaseDetailDialog from '@/Components/FormEngine/PhaseDetailDialog.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { errorsUnder } from '@/lib/formErrors'
import { blankDetail, stepHeading, type EditableDetail } from '@/lib/phaseDefinition'
import type { EvaluationOption, PhaseAccessOption, PhaseDetailEditData } from '@/types/form-engine'
import type { FilterOption } from '@/types/submission-page'

// BR-FE-33 — langkah draf. Versi terbit beku (BR-FE-32), jadi tidak ada kunci pakai: menghapus atau
// mengubah langkah hanya berlaku sejak versi berikutnya.
const props = defineProps<{
    saved: PhaseDetailEditData[]
    accessOptions: PhaseAccessOption[]
    phaseTypeOptions: FilterOption[]
    evaluationOptions: EvaluationOption[]
    errors: Record<string, string>
}>()
const details = defineModel<EditableDetail[]>({ required: true })

const editing = ref<EditableDetail | null>(null)
const dialogOpen = ref(false)
const adding = computed(() => !details.value.some((d) => d.key === editing.value?.key))

const heading = (detail: EditableDetail) =>
    stepHeading(detail, props.saved, props.accessOptions, props.phaseTypeOptions)
const rowErrors = (index: number) => errorsUnder(props.errors, `phase_details.${index}.`)

const open = (detail: EditableDetail | null) => {
    const first = props.accessOptions[0]
    editing.value =
        detail ??
        blankDetail(Number(first?.value ?? 0), Number(props.phaseTypeOptions[0]?.value ?? 0))
    dialogOpen.value = true
}
const store = (saved: EditableDetail) => {
    const index = details.value.findIndex((detail) => detail.key === saved.key)
    details.value =
        index === -1
            ? [...details.value, saved]
            : details.value.map((detail, i) => (i === index ? saved : detail))
}
const remove = (key: string) => (details.value = details.value.filter((d) => d.key !== key))
</script>

<template>
    <SectionCard title="Langkah">
        <template #actions>
            <Button type="button" size="sm" @click="open(null)"
                ><Plus class="size-4" />Tambah Langkah</Button
            >
        </template>
        <ul class="divide-y">
            <li v-for="(detail, index) in details" :key="detail.key" class="space-y-3 py-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-2">
                        <!-- W96 — nomor urut diturunkan backend dari Form; langkah baru belum punya -->
                        <span class="rounded border px-1.5 text-xs text-muted-foreground">{{
                            detail.order ?? 'Baru'
                        }}</span>
                        <div class="min-w-0 space-y-0.5">
                            <p class="text-sm font-medium">{{ heading(detail).title }}</p>
                            <p class="text-xs text-muted-foreground">
                                Akses: {{ heading(detail).access }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Jenis: {{ detail.deadlineLabel }}
                            </p>
                            <RowErrors :messages="rowErrors(index)" />
                        </div>
                    </div>
                    <FieldRowActions
                        :label="heading(detail).title"
                        :remove-disabled="false"
                        @edit="open(detail)"
                        @remove="remove(detail.key)"
                    >
                        <Badge :variant="detail.needsReview ? 'default' : 'outline'">{{
                            detail.needsReview ? 'Perlu Review' : 'Tanpa Review'
                        }}</Badge>
                    </FieldRowActions>
                </div>
                <!-- BR-FE-30 — Form Evaluasi hanya di langkah yang direview -->
                <EvaluationSection
                    v-if="detail.needsReview"
                    v-model="detail.evaluationForms"
                    class="ml-7"
                    :step-key="detail.key"
                    :label="heading(detail).title"
                    :evaluation-options="evaluationOptions"
                />
            </li>
        </ul>
        <p v-if="details.length === 0" class="text-sm text-muted-foreground">Belum ada langkah.</p>
        <PhaseDetailDialog
            v-model:open="dialogOpen"
            :detail="editing"
            :adding="adding"
            :access-options="accessOptions"
            :phase-type-options="phaseTypeOptions"
            @save="store"
        />
    </SectionCard>
</template>
