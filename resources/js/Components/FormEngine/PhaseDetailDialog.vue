<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import ActionDialog from '@/Components/app/ActionDialog.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import PhaseDeadlineSelect from '@/Components/FormEngine/PhaseDeadlineSelect.vue'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { blankDetail, deadlineChoices, type EditableDetail } from '@/lib/phaseDefinition'
import type {
    DeadlineTypeValue,
    PhaseAccessOption,
    SubmissionDateOption,
} from '@/types/form-engine'
import type { FilterOption } from '@/types/submission-page'

const props = defineProps<{
    detail: EditableDetail | null
    evaluationFormCount: number
    accessOptions: PhaseAccessOption[]
    phaseTypeOptions: FilterOption[]
    deadlineOptions: SubmissionDateOption[]
}>()
const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ save: [EditableDetail] }>()

const draft = ref<EditableDetail>(blankDetail(0, 0))
const fill = (isOpen: boolean) => {
    if (isOpen && props.detail) draft.value = { ...props.detail }
}
watch(open, fill, { immediate: true })

// BR-FE-20 — detail berpengajuan terkunci form dan tipe tahapnya; W97 — review terkunci form evaluasi.
const used = computed(() => draft.value.submissionCount > 0)
const usedHint = computed(() =>
    used.value
        ? 'Detail ini sudah dipakai pengajuan; form dan tipe tahapnya tidak dapat diubah.'
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

const choices = (type: DeadlineTypeValue) =>
    deadlineChoices(props.deadlineOptions, type, draft.value)
// BR-FE-26 — Batas Submit pindah periode/siklus: Batas Evaluasi/Revisi yang tak lagi cocok dikosongkan.
const submitDate = computed({
    get: () => draft.value.submissionDateId,
    set: (id: number | null) => {
        draft.value.submissionDateId = id
        const keep = (type: DeadlineTypeValue, current: number | null) =>
            choices(type).some((option) => option.id === current) ? current : null
        draft.value.evaluationDateId = keep('evaluation', draft.value.evaluationDateId)
        draft.value.revisionDateId = keep('revision', draft.value.revisionDateId)
    },
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
        <div class="space-y-1.5">
            <div class="flex items-center gap-2">
                <Switch
                    id="detail-review"
                    v-model="draft.needsReview"
                    :disabled="evaluated > 0"
                    :aria-describedby="evaluated > 0 ? 'detail-review-hint' : undefined"
                />
                <Label for="detail-review" class="font-normal">Perlu Review</Label>
            </div>
            <!-- W97 -->
            <p v-if="evaluated > 0" id="detail-review-hint" class="text-xs text-muted-foreground">
                Detail ini punya {{ evaluated }} form evaluasi aktif.
            </p>
        </div>
        <PhaseDeadlineSelect
            v-model="submitDate"
            :options="choices('submit')"
            type="submit"
            label="Batas Submit *"
        />
        <template v-if="draft.needsReview">
            <PhaseDeadlineSelect
                v-model="draft.evaluationDateId"
                :options="choices('evaluation')"
                type="evaluation"
                label="Batas Evaluasi *"
            />
            <!-- FE-WINDOW-04 — yang dilarang membuat jendela kerja seseorang nol, bukan berbagi tanggal -->
            <AppBanner tone="info">
                Pengajuan dan evaluasinya wajib memakai tanggal berbeda: aktornya berbeda dan
                tahapannya berurutan.
            </AppBanner>
        </template>
        <PhaseDeadlineSelect
            v-model="draft.revisionDateId"
            :options="choices('revision')"
            type="revision"
            label="Batas Revisi"
        />
    </ActionDialog>
</template>
