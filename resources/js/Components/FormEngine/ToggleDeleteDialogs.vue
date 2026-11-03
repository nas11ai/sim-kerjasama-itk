<script setup lang="ts">
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'

// Dua konfirmasi yang sama bentuknya untuk Form dan tahap: aktifkan/nonaktifkan, lalu hapus.
// Teksnya datang dari pemanggil; pemanggil yang menutup dialog setelah aksi selesai.
defineProps<{
    toggleCopy: { title: string; description: string; confirmLabel: string }
    destructiveToggle: boolean
    deleteTitle: string
    deleteDescription: string
    deleteConfirmLabel: string
    processing: boolean
}>()

const toggling = defineModel<boolean>('toggling', { required: true })
const deleting = defineModel<boolean>('deleting', { required: true })
const emit = defineEmits<{ confirmToggle: []; confirmDelete: [] }>()
</script>

<template>
    <ConfirmDialog
        v-model:open="toggling"
        :title="toggleCopy.title"
        :description="toggleCopy.description"
        :confirm-label="toggleCopy.confirmLabel"
        :destructive="destructiveToggle"
        :processing="processing"
        @confirm="emit('confirmToggle')"
    />
    <ConfirmDialog
        v-model:open="deleting"
        :title="deleteTitle"
        :description="deleteDescription"
        :confirm-label="deleteConfirmLabel"
        destructive
        :processing="processing"
        @confirm="emit('confirmDelete')"
    />
</template>
