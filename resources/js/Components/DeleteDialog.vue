<script lang="ts" setup>
import Button from './ui/button/UiButton.vue'
import Dialog from './ui/dialog/UiDialog.vue'
import DialogContent from './ui/dialog/DialogContent.vue'
import DialogDescription from './ui/dialog/DialogDescription.vue'
import DialogFooter from './ui/dialog/DialogFooter.vue'
import DialogHeader from './ui/dialog/DialogHeader.vue'
import DialogTitle from './ui/dialog/DialogTitle.vue'

// Judul dan keterangan bisa ditimpa karena konfirmasi wajib menyebut dampak
// sebenarnya. Dialog yang selalu berbunyi "permanently delete" pada aksi yang
// justru tidak menghapus apa pun mengajari penggunanya untuk berhenti membaca.
const props = withDefaults(
    defineProps<{
        modelValue: boolean
        confirmDelete: () => void
        title?: string
        description?: string
        confirmLabel?: string
    }>(),
    {
        title: 'Are you sure?',
        description: 'This action cannot be undone. This will permanently delete the data.',
        confirmLabel: 'Yes, delete',
    }
)

const emit = defineEmits<{
    'update:modelValue': [value: boolean]
}>()
</script>

<template>
    <Dialog :open="props.modelValue" @close="emit('update:modelValue', false)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ props.title }}</DialogTitle>
                <DialogDescription>{{ props.description }}</DialogDescription>
            </DialogHeader>
            <DialogFooter class="mt-4">
                <Button
                    variant="outline"
                    class="cursor-pointer"
                    @click="emit('update:modelValue', false)"
                >
                    Cancel
                </Button>
                <Button
                    variant="destructive"
                    class="cursor-pointer bg-red-500 hover:bg-red-500/80"
                    @click="confirmDelete"
                >
                    {{ props.confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
