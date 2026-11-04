<script setup lang="ts">
import DeleteBlockedDialog from '@/Components/FormEngine/DeleteBlockedDialog.vue'
import type { PhaseCardData } from '@/types/form-engine'

defineProps<{ phase: PhaseCardData | null }>()
const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ deactivate: [] }>()
</script>

<template>
    <DeleteBlockedDialog
        v-model:open="open"
        title="Tahap tidak bisa dihapus"
        deactivate-label="Nonaktifkan Tahap"
        :can-deactivate="phase?.isActive ?? false"
        @deactivate="emit('deactivate')"
    >
        <!-- BR-FE-20 -->
        Tahap ini dirujuk {{ phase?.submissionCount ?? 0 }} pengajuan, termasuk draft dan yang sudah
        selesai. Tahap yang pernah dipakai dinonaktifkan, bukan dihapus.
    </DeleteBlockedDialog>
</template>
