<script setup lang="ts">
import InputError from '@/Components/InputError.vue'
import { Button } from '@/Components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { useForm } from '@inertiajs/vue3'

const props = defineProps<{
    open: boolean
}>()

const emit = defineEmits<{
    (e: 'close'): void
}>()

const form = useForm({
    name: '',
})

function submit() {
    form.post(route('admin.permissions.store'), {
        onSuccess: () => {
            form.reset()
            emit('close')
        },
    })
}
</script>

<template>
    <Dialog :open="props.open" @update:open="(v) => !v && emit('close')">
        <DialogContent class="transition-all duration-250 sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Tambah Hak Akses Baru</DialogTitle>
            </DialogHeader>

            <p class="text-muted-foreground text-sm">
                Buat hak akses baru untuk mengontrol akses pengguna dalam sistem.
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
                        placeholder="e.g., View Data"
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
                    <Button type="submit" class="cursor-pointer"> Buat </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
