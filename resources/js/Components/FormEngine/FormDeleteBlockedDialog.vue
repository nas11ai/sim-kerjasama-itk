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
        title="Formulir tidak bisa dihapus"
        deactivate-label="Nonaktifkan Formulir"
        :can-deactivate="form?.isActive ?? false"
        @deactivate="emit('deactivate')"
    >
        <!-- BR-FE-19; BR-FE-32 — langkah versi tahap terbit juga merujuknya -->
        <template v-if="(form?.submissionCount ?? 0) > 0"
            >Formulir ini dipakai {{ form?.submissionCount }} pengajuan, termasuk draf dan yang
            sudah selesai.</template
        >
        <template v-else
            >Formulir ini dipakai {{ form?.stepCount ?? 0 }} langkah tahap yang sudah terbit, dan
            versi tahap terbit tidak diubah.</template
        >
        Formulir yang pernah dipakai dinonaktifkan, bukan dihapus.
    </DeleteBlockedDialog>
</template>
