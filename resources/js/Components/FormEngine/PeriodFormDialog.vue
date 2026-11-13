<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

import ActionDialog from '@/Components/app/ActionDialog.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import PeriodDateRows, { type PeriodDateRow } from '@/Components/FormEngine/PeriodDateRows.vue'
import { Checkbox } from '@/Components/ui/checkbox'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import type { DeadlineTypeValue, PeriodRowData } from '@/types/form-engine'
import type { FilterOption } from '@/types/submission-page'

// W71 — buat & sunting periode dalam satu dialog; tanpa frame tersendiri.
const props = defineProps<{
    period: PeriodRowData | null
    typeOptions: FilterOption[]
    phaseOptions: FilterOption[]
}>()
const open = defineModel<boolean>('open', { required: true })

const form = useForm<{
    name: string
    submission_dates: { type: DeadlineTypeValue; cycle: number | null; date: string }[]
    form_phase_ids: number[]
}>({ name: '', submission_dates: [], form_phase_ids: [] })
const rows = ref<PeriodDateRow[]>([])

const fill = () => {
    const period = props.period
    form.name = period?.name ?? ''
    form.form_phase_ids = period ? [...period.formPhaseIds] : []
    rows.value = period
        ? period.dates.map((d) => ({
              type: d.type,
              cycle: d.cycle,
              date: d.inputValue,
              locked: d.phaseDetailCount,
          }))
        : [{ type: 'submit', cycle: null, date: '', locked: 0 }]
    form.clearErrors()
}
watch(open, (isOpen) => isOpen && fill(), { immediate: true })

const forceClosed = computed(() => props.period?.status.value === 'force_closed')
const dateErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => key.startsWith('submission_dates'))
        .map(([, message]) => message)
)

const toggle = (id: number, checked: boolean | 'indeterminate') => {
    form.form_phase_ids =
        checked === true
            ? [...form.form_phase_ids, id]
            : form.form_phase_ids.filter((p) => p !== id)
}

const send = () => {
    form.submission_dates = rows.value.map(({ type, cycle, date }) => ({ type, cycle, date }))
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) }
    if (props.period) {
        form.put(route('admin.submission-periods.update', props.period.id), options)
    } else {
        form.post(route('admin.submission-periods.store'), options)
    }
}
</script>

<template>
    <ActionDialog
        v-model:open="open"
        :title="period ? 'Sunting Periode' : 'Buat Periode Baru'"
        description="Setiap tanggal adalah batas akhir; periode menerima pengajuan baru sampai Batas Pengiriman."
        submit-label="Simpan Periode"
        :processing="form.processing"
        @submit="send"
    >
        <!-- BR-SCH-07 / W73 — periode yang ditutup Operator tetap tertutup -->
        <AppBanner v-if="forceClosed" tone="lock">
            Periode ini ditutup Operator. Memundurkan tenggat tidak membukanya kembali dan tidak
            mengirim pemberitahuan.
        </AppBanner>
        <!-- BR-SCH-08 — jumlah yang akan diberi tahu terlihat sebelum menggeser tanggal -->
        <AppBanner v-else-if="period && period.draftOwnerCount > 0" tone="info">
            Memundurkan Batas Pengiriman atau Batas Revisi akan memberi tahu
            {{ period.draftOwnerCount }} peneliti yang masih punya draf di periode ini.
        </AppBanner>
        <FormField id="period-name" label="Nama Periode" required :error="form.errors.name">
            <Input id="period-name" v-model="form.name" placeholder="mis. Penelitian DIPA 2027" />
        </FormField>
        <PeriodDateRows v-model="rows" :type-options="typeOptions" />
        <p v-for="message in dateErrors" :key="message" class="text-xs text-destructive">
            {{ message }}
        </p>
        <fieldset class="flex flex-col gap-2">
            <legend class="mb-1.5 text-sm font-medium">Tahap yang dibuka</legend>
            <div v-for="phase in phaseOptions" :key="phase.value" class="flex items-center gap-2">
                <Checkbox
                    :id="`period-phase-${phase.value}`"
                    :model-value="form.form_phase_ids.includes(Number(phase.value))"
                    @update:model-value="(checked) => toggle(Number(phase.value), checked)"
                />
                <Label :for="`period-phase-${phase.value}`" class="font-normal">{{
                    phase.label
                }}</Label>
            </div>
            <p v-if="form.errors.form_phase_ids" class="text-xs text-destructive">
                {{ form.errors.form_phase_ids }}
            </p>
        </fieldset>
    </ActionDialog>
</template>
