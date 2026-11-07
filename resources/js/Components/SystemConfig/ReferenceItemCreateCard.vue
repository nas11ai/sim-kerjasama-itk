<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import type { ReferenceCategoryData } from '@/types/system-config'

const props = defineProps<{ categories: ReferenceCategoryData[] }>()

// BR-SC-02 — kategori yang hanya boleh diubah lewat seeder tidak ditawarkan.
const options = computed(() =>
    props.categories
        .filter((category) => category.editable)
        .map((category) => ({ value: category.key, label: category.label }))
)

const categoryKey = ref<string | null>(null)
const name = ref('')
const error = ref<string | undefined>(undefined)
const processing = ref(false)

function add() {
    if (categoryKey.value === null) return
    processing.value = true
    router.post(
        route('admin.reference.store', categoryKey.value),
        { name: name.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                name.value = ''
                error.value = undefined
            },
            onError: (errors) => (error.value = errors.name),
            onFinish: () => (processing.value = false),
        }
    )
}
</script>

<template>
    <SectionCard title="Tambah Item Master Data">
        <form class="flex flex-col gap-4" @submit.prevent="add">
            <FormField id="reference-new-category" label="Kategori" required>
                <OptionSelect
                    id="reference-new-category"
                    v-model="categoryKey"
                    :options="options"
                    placeholder="Pilih kategori"
                />
            </FormField>
            <FormField id="reference-new-name" label="Nama Item" required :error="error">
                <Input
                    id="reference-new-name"
                    v-model="name"
                    maxlength="191"
                    placeholder="Misal: Sewa Peralatan"
                />
            </FormField>
            <div>
                <Button
                    type="submit"
                    :disabled="processing || categoryKey === null || name.trim() === ''"
                    ><Plus class="size-4" />Tambah Item</Button
                >
            </div>
        </form>
        <p class="text-xs text-muted-foreground">
            Nonaktifkan item hanya jika tidak sedang dipakai data aktif.
        </p>
    </SectionCard>
</template>
