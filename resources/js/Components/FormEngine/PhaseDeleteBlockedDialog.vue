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
import type { PhaseCardData } from '@/types/form-engine'

defineProps<{ phase: PhaseCardData | null }>()
const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ deactivate: [] }>()
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Tahap tidak bisa dihapus</DialogTitle>
                <!-- BR-FE-20 -->
                <DialogDescription>
                    Tahap ini dirujuk {{ phase?.submissionCount ?? 0 }} pengajuan, termasuk draft
                    dan yang sudah selesai. Tahap yang pernah dipakai dinonaktifkan, bukan dihapus.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="open = false">Mengerti</Button>
                <Button v-if="phase?.isActive" variant="destructive" @click="emit('deactivate')"
                    >Nonaktifkan Tahap</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
