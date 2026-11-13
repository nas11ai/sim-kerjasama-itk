<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next'

import OptionSelect from '@/Components/app/OptionSelect.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { CYCLE_NUMERALS } from '@/lib/phaseDefinition'
import type { DeadlineTypeValue } from '@/types/form-engine'
import type { FilterOption } from '@/types/submission-page'

// Satu baris = satu tenggat (SubmissionDate): jenis × siklus (BR-FE-25). `locked` = jumlah
// detail tahap yang memakainya; siklus null = pengajuan utama.
export type PeriodDateRow = {
    type: DeadlineTypeValue
    cycle: number | null
    date: string
    locked: number
}

defineProps<{ typeOptions: FilterOption[] }>()
const rows = defineModel<PeriodDateRow[]>({ required: true })

const MAIN = 'main'
const cycleOptions: FilterOption[] = [
    { value: MAIN, label: 'Pengajuan utama' },
    ...CYCLE_NUMERALS.map((numeral, i) => ({ value: String(i + 1), label: `Monev ${numeral}` })),
]

const types: readonly string[] = ['submit', 'evaluation', 'revision', 'output']
const isType = (value: string): value is DeadlineTypeValue => types.includes(value)

const setType = (row: PeriodDateRow, value: string | null) => {
    if (value === null || !isType(value)) return
    row.type = value
    // W139 — Batas Akhir tidak bersiklus.
    if (value === 'output') row.cycle = null
}
const setCycle = (row: PeriodDateRow, value: string | null) => {
    row.cycle = value === null || value === MAIN ? null : Number(value)
}

const add = () =>
    (rows.value = [...rows.value, { type: 'submit', cycle: null, date: '', locked: 0 }])
const remove = (index: number) => (rows.value = rows.value.filter((_, i) => i !== index))
</script>

<template>
    <fieldset class="flex flex-col gap-2">
        <legend class="mb-1.5 text-sm font-medium">Tenggat</legend>
        <div v-for="(row, index) in rows" :key="index" class="flex flex-col gap-1">
            <div class="flex flex-wrap items-end gap-2">
                <div class="min-w-36 flex-1 space-y-1">
                    <Label :for="`period-date-type-${index}`" class="text-xs">Jenis</Label>
                    <OptionSelect
                        :id="`period-date-type-${index}`"
                        :model-value="row.type"
                        :options="typeOptions"
                        :disabled="row.locked > 0"
                        @update:model-value="(value) => setType(row, value)"
                    />
                </div>
                <div v-if="row.type !== 'output'" class="min-w-36 flex-1 space-y-1">
                    <Label :for="`period-date-cycle-${index}`" class="text-xs">Siklus</Label>
                    <OptionSelect
                        :id="`period-date-cycle-${index}`"
                        :model-value="row.cycle === null ? MAIN : String(row.cycle)"
                        :options="cycleOptions"
                        :disabled="row.locked > 0"
                        @update:model-value="(value) => setCycle(row, value)"
                    />
                </div>
                <div class="min-w-44 flex-1 space-y-1">
                    <Label :for="`period-date-at-${index}`" class="text-xs">Batas akhir</Label>
                    <Input
                        :id="`period-date-at-${index}`"
                        v-model="row.date"
                        type="datetime-local"
                    />
                </div>
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    :aria-label="`Buang tenggat ${index + 1}`"
                    :disabled="row.locked > 0 || rows.length === 1"
                    @click="remove(index)"
                    ><Trash2 class="size-4"
                /></Button>
            </div>
            <!-- BR-FE-26 / FE-DELETE-11 — tanggal yang dibaca langkah fase tertaut tidak boleh hilang -->
            <p v-if="row.locked > 0" class="text-xs text-muted-foreground">
                Dipakai {{ row.locked }} detail tahap, tidak bisa dibuang.
            </p>
        </div>
        <Button type="button" variant="outline" size="sm" class="self-start" @click="add"
            ><Plus class="size-4" />Tambah Tenggat</Button
        >
    </fieldset>
</template>
