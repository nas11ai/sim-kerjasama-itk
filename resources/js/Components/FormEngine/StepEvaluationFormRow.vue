<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Pencil, Unlink } from 'lucide-vue-next'

import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import type { StepEvaluationFormData } from '@/types/form-engine'

// BR-FE-30 — satu pemasangan Form Evaluasi di langkah draf: wajib/aktif milik pemasangan, isinya
// disunting di pustaka. Lepas hanya berlaku sejak versi berikutnya; penugasan lama tetap di versinya.
const props = defineProps<{ step: StepEvaluationFormData }>()
const emit = defineEmits<{ update: [step: StepEvaluationFormData]; remove: [] }>()

const toggle = (key: 'isRequired' | 'isActive') =>
    emit('update', { ...props.step, [key]: !props.step[key] })
</script>

<template>
    <li class="flex flex-wrap items-start justify-between gap-3 rounded-lg border p-3">
        <div class="min-w-0 space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-sm font-medium">
                    {{ step.title || 'Form evaluasi sudah dihapus dari pustaka' }}
                </p>
                <Badge v-if="step.versionLabel" variant="outline">{{ step.versionLabel }}</Badge>
                <Badge v-if="!step.isActive" variant="outline">Nonaktif</Badge>
            </div>
            <p class="text-xs text-muted-foreground">
                {{ step.isRequired ? 'Wajib diisi reviewer' : 'Tidak wajib' }}
            </p>
        </div>
        <div class="flex flex-wrap justify-end gap-2">
            <Button v-if="step.title" as-child size="sm" variant="ghost">
                <Link :href="route('admin.evaluation-forms.edit', step.formId)"
                    ><Pencil class="size-4" />Sunting isi</Link
                >
            </Button>
            <Button type="button" size="sm" variant="outline" @click="toggle('isRequired')">{{
                step.isRequired ? 'Jadikan tidak wajib' : 'Jadikan wajib'
            }}</Button>
            <Button type="button" size="sm" variant="outline" @click="toggle('isActive')">{{
                step.isActive ? 'Nonaktifkan' : 'Aktifkan'
            }}</Button>
            <Button type="button" size="sm" variant="ghost" @click="emit('remove')"
                ><Unlink class="size-4" />Lepas</Button
            >
        </div>
    </li>
</template>
