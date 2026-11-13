<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Check } from 'lucide-vue-next'

import FormField from '@/Components/app/FormField.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'

// Nama pustaka Formulir Evaluasi: judul dan deskripsi tak berversi, disimpan langsung (BR-FE-31).
// `form` null = mode buat; sesudah simpan pertama layar pindah ke editor.
const props = defineProps<{
    form: { id: number; title: string; description: string | null } | null
}>()

const details = useForm({
    title: props.form?.title ?? '',
    description: props.form?.description ?? '',
})

function save() {
    const pending = details.transform((data) => ({
        title: data.title,
        description: data.description.trim() === '' ? null : data.description,
    }))
    if (props.form === null) {
        pending.post(route('admin.evaluation-forms.store'))
        return
    }
    pending.put(route('admin.evaluation-forms.update', props.form.id), { preserveScroll: true })
}
</script>

<template>
    <form @submit.prevent="save">
        <SectionCard
            title="Nama Formulir Evaluasi"
            description="Judul dan deskripsi tidak berversi; perubahannya langsung terlihat di semua tahap."
        >
            <FormField id="evaluation-title" label="Judul" required :error="details.errors.title">
                <Input id="evaluation-title" v-model="details.title" maxlength="255" />
            </FormField>
            <FormField
                id="evaluation-description"
                label="Deskripsi"
                :error="details.errors.description"
            >
                <Textarea id="evaluation-description" v-model="details.description" rows="2" />
            </FormField>
            <div class="flex justify-end">
                <Button type="submit" :disabled="details.processing"
                    ><Check class="size-4" />{{ form === null ? 'Simpan' : 'Simpan nama' }}</Button
                >
            </div>
        </SectionCard>
    </form>
</template>
