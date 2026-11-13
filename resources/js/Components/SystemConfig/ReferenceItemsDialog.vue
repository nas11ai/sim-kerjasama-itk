<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import ReferenceItemRow from '@/Components/SystemConfig/ReferenceItemRow.vue'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog'
import type { ReferenceCategoryData, ReferenceItemData } from '@/types/system-config'

const props = defineProps<{ category: ReferenceCategoryData | null }>()
const open = defineModel<boolean>('open', { required: true })

const target = ref<ReferenceItemData | null>(null)
const confirming = ref(false)
const processing = ref(false)

const deleteCopy = computed(
    () => `${target.value?.name ?? ''} dihapus permanen dari ${props.category?.label ?? ''}.`
)

function requestDelete(item: ReferenceItemData) {
    target.value = item
    confirming.value = true
}

function confirmDelete() {
    if (props.category === null || target.value === null) return
    processing.value = true
    router.delete(
        route('admin.reference.destroy', {
            entity: props.category.key,
            id: target.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => (confirming.value = false),
            onFinish: () => (processing.value = false),
        }
    )
}
</script>

<template>
    <Dialog v-if="category" :open="open" @update:open="(value) => (open = value)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Kelola {{ category.label }}</DialogTitle>
                <DialogDescription>Dipakai oleh {{ category.usedBy }}.</DialogDescription>
            </DialogHeader>
            <!-- BR-SC-02 — mengikat empat context, jadi tidak punya aksi di layar -->
            <AppBanner v-if="!category.editable" tone="info">
                {{ category.label }} hanya dapat diubah lewat seeder. Bukan kekurangan antarmuka,
                nilainya mengikat empat context sekaligus.
            </AppBanner>
            <ul class="divide-y">
                <ReferenceItemRow
                    v-for="item in category.items"
                    :key="item.id"
                    :category="category"
                    :item="item"
                    @delete="requestDelete"
                />
            </ul>
            <p v-if="category.items.length === 0" class="text-sm text-muted-foreground">
                Belum ada item.
            </p>
        </DialogContent>
    </Dialog>
    <ConfirmDialog
        v-model:open="confirming"
        title="Hapus item?"
        :description="deleteCopy"
        confirm-label="Hapus Item"
        destructive
        :processing="processing"
        @confirm="confirmDelete"
    />
</template>
