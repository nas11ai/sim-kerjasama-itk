<script setup lang="ts">
import { Button } from '@/Components/ui/button'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog'

// Penjelasan "tidak bisa dihapus, nonaktifkan saja" yang sama bentuknya untuk Form, tahap,
// dan form evaluasi; teks keputusan datang dari pemanggil lewat slot.
defineProps<{ title: string; deactivateLabel: string; canDeactivate: boolean }>()
const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ deactivate: [] }>()
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription><slot /></DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="open = false">Mengerti</Button>
                <Button v-if="canDeactivate" variant="destructive" @click="emit('deactivate')">{{
                    deactivateLabel
                }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
