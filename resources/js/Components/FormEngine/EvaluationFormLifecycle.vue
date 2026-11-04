<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import DeleteBlockedDialog from '@/Components/FormEngine/DeleteBlockedDialog.vue'
import { Button } from '@/Components/ui/button'
import type { EvaluationFormEditData } from '@/types/form-engine'

// Nonaktifkan/aktifkan dan hapus satu form evaluasi tersimpan. Kelayakan hapus dari backend
// (`assignmentCount`); toggle = PUT tanpa kunci `fields`, jadi field tidak tersentuh.
const props = defineProps<{ form: EvaluationFormEditData }>()

const toggling = ref(false)
const deleting = ref(false)
const blocked = ref(false)
const processing = ref(false)

// Payload dibangun saat kirim dari props terkini: kartu tidak dipasang ulang setelah simpan/toggle.
const toggle = useForm({}).transform(() => ({
    title: props.form.title,
    description: props.form.description,
    is_required: props.form.isRequired,
    is_active: !props.form.isActive,
}))

const requestDelete = () => {
    if (props.form.assignmentCount > 0) blocked.value = true
    else deleting.value = true
}

const confirmToggle = () =>
    toggle.put(route('admin.evaluation-forms.update', props.form.id), {
        preserveScroll: true,
        onSuccess: () => (toggling.value = false),
    })

const confirmDelete = () =>
    router.delete(route('admin.evaluation-forms.destroy', props.form.id), {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onSuccess: () => (deleting.value = false),
        onFinish: () => (processing.value = false),
    })

// Dari dialog penjelasan: tutup, lalu minta konfirmasi nonaktifkan.
const deactivateInstead = () => {
    blocked.value = false
    toggling.value = true
}
</script>

<template>
    <Button type="button" variant="outline" size="sm" @click="toggling = true">{{
        form.isActive ? 'Nonaktifkan' : 'Aktifkan'
    }}</Button>
    <Button type="button" variant="outline" size="sm" @click="requestDelete">Hapus</Button>
    <ConfirmDialog
        v-model:open="toggling"
        :title="form.isActive ? 'Nonaktifkan form evaluasi?' : 'Aktifkan form evaluasi?'"
        :description="
            form.isActive
                ? `${form.title} berhenti dipakai untuk penugasan baru. Penilaian yang sudah ada tetap terbaca.`
                : `${form.title} kembali dipakai untuk penugasan baru.`
        "
        :confirm-label="form.isActive ? 'Nonaktifkan' : 'Aktifkan'"
        :destructive="form.isActive"
        :processing="toggle.processing"
        @confirm="confirmToggle"
    />
    <ConfirmDialog
        v-model:open="deleting"
        title="Hapus form evaluasi?"
        :description="`${form.title} beserta fieldnya dihapus permanen.`"
        confirm-label="Hapus"
        destructive
        :processing="processing"
        @confirm="confirmDelete"
    />
    <DeleteBlockedDialog
        v-model:open="blocked"
        title="Form evaluasi tidak bisa dihapus"
        deactivate-label="Nonaktifkan"
        :can-deactivate="form.isActive"
        @deactivate="deactivateInstead"
    >
        <!-- BR-FE-22 -->
        Form ini pernah ditugaskan {{ form.assignmentCount }} kali. Form evaluasi adalah dasar
        keputusan review, jadi yang pernah dipakai dinonaktifkan, bukan dihapus.
    </DeleteBlockedDialog>
</template>
