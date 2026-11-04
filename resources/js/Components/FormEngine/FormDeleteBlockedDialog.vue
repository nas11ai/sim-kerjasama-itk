<script setup lang="ts">
import DeleteBlockedDialog from '@/Components/FormEngine/DeleteBlockedDialog.vue'
import type { FormRowData } from '@/types/form-engine'

defineProps<{ form: FormRowData | null }>()
const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ deactivate: [] }>()
</script>

<template>
    <DeleteBlockedDialog
        v-model:open="open"
        title="Form tidak bisa dihapus"
        deactivate-label="Nonaktifkan Form"
        :can-deactivate="form?.isActive ?? false"
        @deactivate="emit('deactivate')"
    >
        <!-- BR-FE-19 -->
        Form ini dipakai {{ form?.submissionCount ?? 0 }} pengajuan, termasuk draft dan yang sudah
        selesai. Form yang pernah dipakai dinonaktifkan, bukan dihapus.
    </DeleteBlockedDialog>
</template>
