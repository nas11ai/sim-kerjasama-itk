<script lang="ts" setup>
import Button from "./ui/button/Button.vue";
import Dialog from "./ui/dialog/Dialog.vue";
import DialogContent from "./ui/dialog/DialogContent.vue";
import DialogDescription from "./ui/dialog/DialogDescription.vue";
import DialogFooter from "./ui/dialog/DialogFooter.vue";
import DialogHeader from "./ui/dialog/DialogHeader.vue";
import DialogTitle from "./ui/dialog/DialogTitle.vue";

const props = defineProps<{
    modelValue: boolean;
    confirmDelete: () => void;
}>();

const emit = defineEmits<{
    (e: "update:modelValue", value: boolean): void;
}>();
</script>

<template>
    <Dialog :open="props.modelValue" @close="emit('update:modelValue', false)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Are you sure?</DialogTitle>
                <DialogDescription>
                    This action cannot be undone. This will permanently delete
                    the data.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="mt-4">
                <Button
                    variant="outline"
                    @click="emit('update:modelValue', false)"
                    class="cursor-pointer"
                    >Cancel</Button
                >
                <Button
                    variant="destructive"
                    @click="confirmDelete"
                    class="cursor-pointer bg-red-500 hover:bg-red-500/80"
                    >Yes, delete</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
