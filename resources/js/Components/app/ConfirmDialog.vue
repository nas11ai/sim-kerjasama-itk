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
import { useLockedOpen } from '@/composables/useLockedOpen'

// Pengganti confirm() native; pemanggil yang menutup dialog setelah aksi selesai.
const props = withDefaults(
    defineProps<{
        title: string
        description: string
        confirmLabel: string
        destructive?: boolean
        processing?: boolean
    }>(),
    { destructive: false, processing: false }
)

const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ confirm: [] }>()
const changeOpen = useLockedOpen(open, () => props.processing)
</script>

<template>
    <Dialog :open="open" @update:open="changeOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <slot />
            <DialogFooter>
                <Button
                    variant="outline"
                    type="button"
                    :disabled="processing"
                    @click="changeOpen(false)"
                >
                    Batal
                </Button>
                <Button
                    type="button"
                    :variant="destructive ? 'destructive' : 'default'"
                    :disabled="processing"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
