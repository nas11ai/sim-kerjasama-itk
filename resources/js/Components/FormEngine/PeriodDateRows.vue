<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next'

import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'

// Satu baris = satu tenggat (SubmissionDate). `locked` = jumlah detail tahap yang memakainya.
export type PeriodDateRow = { label: string; date: string; locked: number }

defineProps<{ labelOptions: string[] }>()
const rows = defineModel<PeriodDateRow[]>({ required: true })

const add = () => (rows.value = [...rows.value, { label: '', date: '', locked: 0 }])
const remove = (index: number) => (rows.value = rows.value.filter((_, i) => i !== index))
</script>

<template>
    <fieldset class="flex flex-col gap-2">
        <legend class="mb-1.5 text-sm font-medium">Tenggat</legend>
        <!-- W72 — label teks bebas dengan saran; label baru dibuat saat disimpan (BR-FE-17) -->
        <datalist id="period-date-labels">
            <option v-for="name in labelOptions" :key="name" :value="name" />
        </datalist>
        <div v-for="(row, index) in rows" :key="index" class="flex flex-col gap-1">
            <div class="flex items-end gap-2">
                <div class="flex-1 space-y-1">
                    <Label :for="`period-date-label-${index}`" class="text-xs">Label</Label>
                    <Input
                        :id="`period-date-label-${index}`"
                        v-model="row.label"
                        list="period-date-labels"
                        placeholder="mis. Batas Submit"
                        :readonly="row.locked > 0"
                    />
                </div>
                <div class="flex-1 space-y-1">
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
            <!-- BR-FE-07 / FE-DELETE-11 — tenggat yang menggerbangi detail tahap tidak boleh hilang -->
            <p v-if="row.locked > 0" class="text-xs text-muted-foreground">
                Dipakai {{ row.locked }} detail tahap — tidak bisa dibuang.
            </p>
        </div>
        <Button type="button" variant="outline" size="sm" class="self-start" @click="add"
            ><Plus class="size-4" />Tambah Tenggat</Button
        >
    </fieldset>
</template>
