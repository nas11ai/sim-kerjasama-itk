<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import ActionDialog from '@/Components/app/ActionDialog.vue'
import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import {
    applyStepKind,
    blankDetail,
    stepKindKey,
    stepKindOptions,
    type EditableDetail,
} from '@/lib/phaseDefinition'
import type { PhaseAccessOption } from '@/types/form-engine'
import type { FilterOption } from '@/types/submission-page'

const props = defineProps<{
    detail: EditableDetail | null
    evaluationFormCount: number
    accessOptions: PhaseAccessOption[]
    phaseTypeOptions: FilterOption[]
}>()
const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ save: [EditableDetail] }>()

const draft = ref<EditableDetail>(blankDetail(0, 0))
const fill = (isOpen: boolean) => {
    if (isOpen && props.detail) draft.value = { ...props.detail }
}
watch(open, fill, { immediate: true })

// BR-FE-20 + K-1 — detail berpengajuan terkunci form, tipe tahap, dan jenis langkahnya;
// W97 — review terkunci form evaluasi.
const used = computed(() => draft.value.submissionCount > 0)
const usedHint = computed(() =>
    used.value
        ? 'Detail ini sudah dipakai pengajuan; form, tipe tahap, dan jenis langkahnya tidak dapat diubah.'
        : undefined
)
// W97 — hitungan hidup dari props server: form evaluasi bisa dibuat di layar ini tanpa remount.
const evaluated = computed(() => props.evaluationFormCount)

const idField = (key: 'formAccessControlId' | 'phaseTypeId') =>
    computed({
        get: () => String(draft.value[key]),
        set: (value: string | null) => {
            if (value !== null) draft.value[key] = Number(value)
        },
    })
const access = idField('formAccessControlId')
const phaseType = idField('phaseTypeId')

// ADR 0019 — langkah memilih jenis + siklus; tanggalnya dibaca dari kalender periode pengajuan.
const kindOptions = stepKindOptions.map(({ value, label }) => ({ value, label }))
const stepKind = computed({
    get: () => stepKindKey(draft.value),
    set: (value: string | null) => {
        if (value !== null) draft.value = applyStepKind(draft.value, value)
    },
})
// BR-FE-26 (W139) — langkah Upload Luaran tidak direview.
const isOutput = computed(() => draft.value.deadlineType === 'output')
const reviewLocked = computed(() => evaluated.value > 0 || isOutput.value)
const reviewHint = computed(() => {
    if (evaluated.value > 0) return `Detail ini punya ${evaluated.value} form evaluasi aktif.`
    return isOutput.value ? 'Detail Upload Luaran tidak direview.' : undefined
})

const save = () => {
    emit('save', { ...draft.value })
    open.value = false
}
</script>

<template>
    <ActionDialog
        v-model:open="open"
        :title="draft.id === null ? 'Tambah Detail' : 'Edit Detail'"
        submit-label="Simpan Detail"
        @submit="save"
    >
        <FormField id="detail-access" label="Akses" required :hint="usedHint">
            <template #default="{ describedBy }">
                <OptionSelect
                    id="detail-access"
                    v-model="access"
                    :options="accessOptions"
                    :disabled="used"
                    :described-by="describedBy"
                />
            </template>
        </FormField>
        <FormField id="detail-type" label="Tipe Tahap" required :hint="usedHint">
            <template #default="{ describedBy }">
                <OptionSelect
                    id="detail-type"
                    v-model="phaseType"
                    :options="phaseTypeOptions"
                    :disabled="used"
                    :described-by="describedBy"
                />
            </template>
        </FormField>
        <FormField
            id="detail-kind"
            label="Jenis langkah"
            required
            :hint="usedHint ?? 'Tanggalnya dibaca dari kalender periode pengajuan.'"
        >
            <template #default="{ describedBy }">
                <OptionSelect
                    id="detail-kind"
                    v-model="stepKind"
                    :options="kindOptions"
                    :disabled="used"
                    :described-by="describedBy"
                />
            </template>
        </FormField>
        <div class="space-y-1.5">
            <div class="flex items-center gap-2">
                <Switch
                    id="detail-review"
                    v-model="draft.needsReview"
                    :disabled="reviewLocked"
                    :aria-describedby="reviewHint ? 'detail-review-hint' : undefined"
                />
                <Label for="detail-review" class="font-normal">Perlu Review</Label>
            </div>
            <!-- W97 -->
            <p v-if="reviewHint" id="detail-review-hint" class="text-xs text-muted-foreground">
                {{ reviewHint }}
            </p>
        </div>
    </ActionDialog>
</template>
