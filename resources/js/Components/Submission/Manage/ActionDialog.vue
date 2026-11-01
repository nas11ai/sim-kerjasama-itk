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

// Kerangka keenam dialog aksi Operator (W30: frame berupa halaman, dibangun sebagai dialog).
const props = withDefaults(
    defineProps<{
        title: string
        description?: string
        submitLabel: string
        destructive?: boolean
        processing?: boolean
        submitDisabled?: boolean
    }>(),
    { description: undefined, destructive: false, processing: false, submitDisabled: false }
)

const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ submit: [] }>()
const changeOpen = useLockedOpen(open, () => props.processing)
</script>

<template>
    <Dialog :open="open" @update:open="changeOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">{{ description }}</DialogDescription>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="emit('submit')">
                <slot />
                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        :disabled="processing"
                        @click="changeOpen(false)"
                        >Batal</Button
                    >
                    <Button
                        type="submit"
                        :variant="destructive ? 'destructive' : 'default'"
                        :disabled="processing || submitDisabled"
                        >{{ submitLabel }}</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
