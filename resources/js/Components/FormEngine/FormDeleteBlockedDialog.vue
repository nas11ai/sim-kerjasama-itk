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
import type { FormRowData } from '@/types/form-engine'

defineProps<{ form: FormRowData | null }>()
const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ deactivate: [] }>()
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Form tidak bisa dihapus</DialogTitle>
                <!-- BR-FE-19 -->
                <DialogDescription>
                    Form ini dipakai {{ form?.submissionCount ?? 0 }} pengajuan, termasuk draft dan
                    yang sudah selesai. Form yang pernah dipakai dinonaktifkan, bukan dihapus.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="open = false">Mengerti</Button>
                <Button v-if="form?.isActive" variant="destructive" @click="emit('deactivate')"
                    >Nonaktifkan Form</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
