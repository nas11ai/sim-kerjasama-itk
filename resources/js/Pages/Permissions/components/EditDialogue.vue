<!-- components/EditDialog.vue -->
<script setup lang="ts">
import { route } from 'ziggy-js'
import InputError from '@/Components/InputError.vue'
import { Button } from '@/Components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import type { Permission } from '@/types'
import { useForm } from '@inertiajs/vue3'
import { watch } from 'vue'

const props = defineProps<{
    open: boolean
    permission: Permission | null
}>()

const emit = defineEmits<{
    (e: 'close'): void
}>()

const form = useForm({
    name: '',
})

// Trigger pertama kali ketika permission berubah
watch(
    () => props.permission,
    (permission) => {
        if (props.open && permission) {
            form.reset()
            form.clearErrors()
            form.name = permission.name
        }
    },
    { immediate: true }
)

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.permission) {
            form.reset()
            form.clearErrors()
            form.name = props.permission.name
        }
    }
)

function submit() {
    if (!props.permission) return

    form.put(route('admin.permissions.update', props.permission.id), {
        onSuccess: () => {
            emit('close')
        },
    })
}
</script>

<template>
    <Dialog :open="props.open" @update:open="(v) => !v && emit('close')">
        <DialogContent class="transition-all duration-300 sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Edit Permission</DialogTitle>
            </DialogHeader>

            <p class="text-muted-foreground text-sm">
                Isi form di bawah untuk memberikan nama baru pada hak akses.
            </p>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Nama Hak Akses</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        placeholder="Masukkan Nama Hak Akses"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        class="cursor-pointer"
                        variant="ghost"
                        @click="emit('close')"
                    >
                        Batal
                    </Button>
                    <Button type="submit" class="cursor-pointer"> Simpan </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
