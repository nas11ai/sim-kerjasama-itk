<script setup lang="ts">
import Button from './ui/button/UiButton.vue'
import Dialog from './ui/dialog/UiDialog.vue'
import DialogContent from './ui/dialog/DialogContent.vue'
import DialogFooter from './ui/dialog/DialogFooter.vue'
import DialogHeader from './ui/dialog/DialogHeader.vue'
import DialogTitle from './ui/dialog/DialogTitle.vue'

const props = defineProps<{
    modelValue: boolean
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void
    (e: 'confirm'): void
}>()

function close() {
    emit('update:modelValue', false)
}

function confirm() {
    emit('confirm')
    close()
}
</script>

<template>
    <Dialog :open="props.modelValue" @update:open="(v) => emit('update:modelValue', v)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete Confirmation</DialogTitle>
            </DialogHeader>

            <slot />

            <DialogFooter>
                <Button variant="outline" class="cursor-pointer" @click="close"> Cancel </Button>
                <Button
                    variant="destructive"
                    class="cursor-pointer text-white bg-red-500 hover:bg-red-500/80"
                    @click="confirm"
                >
                    Yes, delete
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
