<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { Pencil, Unlink } from 'lucide-vue-next'
import { ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import DeleteBlockedDialog from '@/Components/FormEngine/DeleteBlockedDialog.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import type { StepEvaluationFormData } from '@/types/form-engine'

// BR-FE-30 — satu pemasangan Form Evaluasi pustaka di langkah ini: wajib/aktif milik pemasangan,
// isinya disunting di pustaka. Kelayakan lepas dari backend (`assignmentCount`, BR-FE-22).
const props = defineProps<{ step: StepEvaluationFormData }>()

const detaching = ref(false)
const blocked = ref(false)
const processing = ref(false)

const update = (changes: { is_required?: boolean; is_active?: boolean }) =>
    router.put(
        route('admin.step-evaluation-forms.update', props.step.id),
        { is_required: props.step.isRequired, is_active: props.step.isActive, ...changes },
        { preserveScroll: true, onSuccess: () => (blocked.value = false) }
    )

const requestDetach = () => {
    if (props.step.assignmentCount > 0) blocked.value = true
    else detaching.value = true
}

// Data basi (reviewer ditugaskan sesudah layar dibuka): penolakan server tampil di baris ini.
const refusal = ref<string | null>(null)
const confirmDetach = () =>
    router.delete(route('admin.step-evaluation-forms.destroy', props.step.id), {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onSuccess: () => (detaching.value = false),
        onError: (errors) => {
            refusal.value = errors.step_evaluation_form ?? null
            detaching.value = false
        },
        onFinish: () => (processing.value = false),
    })
</script>

<template>
    <li class="flex flex-wrap items-start justify-between gap-3 rounded-lg border p-3">
        <div class="min-w-0 space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-sm font-medium">{{ step.title }}</p>
                <Badge variant="outline">{{ step.versionLabel }}</Badge>
                <Badge v-if="!step.isActive" variant="outline">Nonaktif</Badge>
            </div>
            <p class="text-xs text-muted-foreground">
                {{ step.isRequired ? 'Wajib diisi reviewer' : 'Tidak wajib' }}
                <template v-if="step.assignmentCount > 0">
                    · ditugaskan ke {{ step.assignmentCount }} reviewer
                </template>
            </p>
            <AppBanner v-if="refusal" tone="denial">{{ refusal }}</AppBanner>
        </div>
        <div class="flex flex-wrap justify-end gap-2">
            <Button as-child size="sm" variant="ghost">
                <Link :href="route('admin.evaluation-forms.edit', step.formId)"
                    ><Pencil class="size-4" />Sunting isi</Link
                >
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                @click="update({ is_required: !step.isRequired })"
                >{{ step.isRequired ? 'Jadikan tidak wajib' : 'Jadikan wajib' }}</Button
            >
            <Button
                type="button"
                size="sm"
                variant="outline"
                @click="update({ is_active: !step.isActive })"
                >{{ step.isActive ? 'Nonaktifkan' : 'Aktifkan' }}</Button
            >
            <Button type="button" size="sm" variant="ghost" @click="requestDetach"
                ><Unlink class="size-4" />Lepas</Button
            >
        </div>
        <ConfirmDialog
            v-model:open="detaching"
            title="Lepas form evaluasi?"
            :description="`${step.title} tidak lagi dipakai langkah ini. Isinya tetap ada di pustaka Form Evaluasi.`"
            confirm-label="Lepas Form"
            destructive
            :processing="processing"
            @confirm="confirmDetach"
        />
        <DeleteBlockedDialog
            v-model:open="blocked"
            title="Form evaluasi tidak bisa dilepas"
            deactivate-label="Nonaktifkan"
            :can-deactivate="step.isActive"
            @deactivate="update({ is_active: false })"
        >
            <!-- BR-FE-22 — teks sama dengan penolakan DetachEvaluationForm -->
            Form evaluasi ini sudah ditugaskan ke {{ step.assignmentCount }} reviewer di langkah
            ini, jadi tidak bisa dilepas. Nonaktifkan saja.
        </DeleteBlockedDialog>
    </li>
</template>
