<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Check, Lock, Pencil, Trash2, X } from 'lucide-vue-next'
import { ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import type { ReferenceCategoryData, ReferenceItemData } from '@/types/system-config'

const props = defineProps<{ category: ReferenceCategoryData; item: ReferenceItemData }>()
const emit = defineEmits<{ delete: [item: ReferenceItemData] }>()

const renaming = ref(false)
const name = ref(props.item.name)
const error = ref<string | undefined>(undefined)
const lockId = `reference-lock-${props.item.id}`

function startRename() {
    name.value = props.item.name
    error.value = undefined
    renaming.value = true
}

function saveName() {
    router.put(
        route('admin.reference.update', { entity: props.category.key, id: props.item.id }),
        { name: name.value },
        {
            preserveScroll: true,
            onSuccess: () => (renaming.value = false),
            onError: (errors) => (error.value = errors.name),
        }
    )
}

function toggle() {
    router.post(
        route('admin.reference.toggle', { entity: props.category.key, id: props.item.id }),
        { is_active: props.item.isActive !== true },
        { preserveScroll: true }
    )
}
</script>

<template>
    <li class="space-y-2 py-3 first:pt-0 last:pb-0">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex min-w-0 items-center gap-2">
                <span class="text-sm font-medium">{{ item.name }}</span>
                <Badge v-if="item.isActive === false" variant="outline">Nonaktif</Badge>
                <span class="text-xs text-muted-foreground">dipakai {{ item.usageCount }}</span>
            </div>
            <div v-if="category.editable && !renaming" class="flex gap-2">
                <Button size="sm" variant="ghost" @click="startRename"
                    ><Pencil class="size-4" />Ganti Nama</Button
                >
                <Button v-if="category.deactivatable" size="sm" variant="outline" @click="toggle">{{
                    item.isActive ? 'Nonaktifkan' : 'Aktifkan'
                }}</Button>
                <Button
                    size="sm"
                    variant="ghost"
                    :disabled="item.usageCount > 0"
                    :aria-describedby="item.usageCount > 0 ? lockId : undefined"
                    @click="emit('delete', item)"
                    ><Trash2 class="size-4" />Hapus</Button
                >
            </div>
        </div>
        <!-- BR-SC-01 — angka pemakai tampil sebelum Hapus ditekan -->
        <p
            v-if="category.editable && item.usageCount > 0"
            :id="lockId"
            class="flex items-start gap-1.5 text-xs text-muted-foreground"
        >
            <Lock class="mt-0.5 size-3.5 shrink-0" />
            Tidak dapat dihapus: masih dipakai {{ item.usageCount }} {{ category.usedBy }}.
            Nonaktifkan saja agar tidak lagi ditawarkan.
        </p>
        <form v-if="renaming" class="space-y-2" @submit.prevent="saveName">
            <!-- BR-SC-03 — ganti nama ikut mengubah laporan lama (SC-CONFIG-04) -->
            <AppBanner tone="info">
                Nama diperbarui di seluruh laporan, termasuk yang lama. Pakai ini hanya kalau memang
                satu hal yang sama dengan sebutan yang diperbaiki.
            </AppBanner>
            <Input
                :id="`reference-rename-${item.id}`"
                v-model="name"
                maxlength="191"
                aria-label="Nama baru"
            />
            <p v-if="error" class="text-xs text-destructive">{{ error }}</p>
            <div class="flex gap-2">
                <Button type="submit" size="sm"><Check class="size-4" />Simpan Nama</Button>
                <Button type="button" size="sm" variant="ghost" @click="renaming = false"
                    ><X class="size-4" />Batal</Button
                >
            </div>
        </form>
    </li>
</template>
