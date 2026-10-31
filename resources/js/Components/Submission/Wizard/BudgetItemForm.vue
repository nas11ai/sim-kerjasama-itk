<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { reactive } from 'vue'

import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import type { BudgetItem, FilterOption } from '@/types/submission-page'

// Tambah item baru, atau ubah `item` bila diberikan (BR-BUD-10). Pemanggil memberinya `key`
// per item supaya isian di-seed ulang.
const props = defineProps<{
    submissionId: number
    components: FilterOption[]
    item: BudgetItem | null
}>()

const emit = defineEmits<{ saved: []; cancel: [] }>()

const page = usePage()
const form = reactive({
    budget_component_id: props.item ? String(props.item.componentId) : null,
    item_name: props.item?.item ?? '',
    volume: props.item?.volume ?? '',
    unit: props.item?.unit ?? '',
    unit_price: props.item?.unitPrice ?? '',
})
const error = (key: string) => page.props.errors?.[key]

const save = () => {
    const data = { ...form, volume: Number(form.volume), unit_price: Number(form.unit_price) }
    const options = { preserveScroll: true, onSuccess: () => emit('saved') }
    if (props.item === null) {
        router.post(
            route('user.submissions.budget-items.store', String(props.submissionId)),
            data,
            options
        )
        return
    }
    router.put(
        route('user.submissions.budget-items.update', [
            String(props.submissionId),
            String(props.item.id),
        ]),
        data,
        options
    )
}
</script>

<template>
    <form class="grid gap-3 rounded-md border p-4 sm:grid-cols-2" @submit.prevent="save">
        <FormField
            id="budget-component"
            label="Komponen"
            required
            :error="error('budget_component_id')"
        >
            <OptionSelect
                id="budget-component"
                v-model="form.budget_component_id"
                :options="components"
                placeholder="Pilih komponen"
            />
        </FormField>
        <FormField id="budget-item" label="Item" required :error="error('item_name')">
            <Input id="budget-item" v-model="form.item_name" />
        </FormField>
        <!-- BR-BUD-04 -->
        <FormField id="budget-volume" label="Volume" required :error="error('volume')">
            <Input id="budget-volume" v-model="form.volume" type="number" min="1" />
        </FormField>
        <FormField id="budget-unit" label="Satuan" required :error="error('unit')">
            <Input id="budget-unit" v-model="form.unit" />
        </FormField>
        <FormField
            id="budget-price"
            label="Harga Satuan (Rp)"
            required
            :error="error('unit_price')"
        >
            <Input id="budget-price" v-model="form.unit_price" type="number" min="1" />
        </FormField>
        <div class="flex items-end gap-2">
            <Button type="submit">{{ item ? 'Simpan Perubahan' : 'Tambah Item' }}</Button>
            <Button v-if="item" type="button" variant="ghost" @click="emit('cancel')">Batal</Button>
        </div>
    </form>
</template>
