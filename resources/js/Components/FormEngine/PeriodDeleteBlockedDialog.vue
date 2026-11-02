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
import type { PeriodRowData } from '@/types/form-engine'

// BR-FE-21 / FE-DELETE-10, 11 — penghalang dari SubmissionPeriodReferences, definisi yang
// sama dengan penegaknya; jalan keluarnya menutup periode, bukan menghapusnya.
defineProps<{ period: PeriodRowData | null }>()
const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ close: [] }>()
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Periode tidak bisa dihapus</DialogTitle>
                <DialogDescription>
                    Periode yang sudah dipakai dinonaktifkan, bukan dihapus. Tutup periode ini agar
                    tidak menerima pengajuan baru.
                </DialogDescription>
            </DialogHeader>
            <ul v-if="period" class="list-disc pl-5 text-sm">
                <li v-for="blocker in period.deleteBlockers" :key="blocker.label">
                    Masih dirujuk {{ blocker.count }} {{ blocker.label }}
                </li>
            </ul>
            <DialogFooter>
                <Button variant="outline" @click="open = false">Mengerti</Button>
                <Button v-if="period?.closable" variant="destructive" @click="emit('close')"
                    >Tutup Periode</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
