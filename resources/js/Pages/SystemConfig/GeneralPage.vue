<script setup lang="ts">
// Frame: ADMIN-08-10
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import PageHeader from '@/Components/app/PageHeader.vue'
import NotificationChannelCard from '@/Components/SystemConfig/NotificationChannelCard.vue'
import ReferenceCategoryCard from '@/Components/SystemConfig/ReferenceCategoryCard.vue'
import ReferenceItemCreateCard from '@/Components/SystemConfig/ReferenceItemCreateCard.vue'
import ReferenceItemsDialog from '@/Components/SystemConfig/ReferenceItemsDialog.vue'
import { Tabs, TabsList, TabsTrigger } from '@/Components/ui/tabs'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { GeneralPageProps, ReferenceCategoryData, ReferenceGroup } from '@/types/system-config'

const props = defineProps<GeneralPageProps>()

// W112 — tab menyaring kartu di klien: pengelompokan tampilan atas belasan baris.
const tabs: { value: ReferenceGroup | 'all'; label: string }[] = [
    { value: 'all', label: 'Semua Kategori' },
    { value: 'output', label: 'Referensi Luaran' },
    { value: 'scheme', label: 'Referensi Skema' },
    { value: 'other', label: 'Lainnya' },
]
const cards: { group: ReferenceGroup; title: string }[] = [
    { group: 'output', title: 'Referensi Luaran' },
    { group: 'scheme', title: 'Referensi Skema & Submission' },
    { group: 'other', title: 'Anggaran' },
]

const tab = ref<string | number>('all')
const visibleCards = computed(() =>
    cards
        .filter((card) => tab.value === 'all' || tab.value === card.group)
        .map((card) => ({
            ...card,
            categories: props.categories.filter((category) => category.group === card.group),
        }))
        .filter((card) => card.categories.length > 0)
)

// BR-NOT-06 — channel notifikasi termasuk tab "Lainnya" (W112).
const showsChannels = computed(() => tab.value === 'all' || tab.value === 'other')

const managedKey = ref<string | null>(null)
const managing = ref(false)
// Dibaca ulang dari props supaya dialog ikut segar setelah setiap aksi.
const managed = computed(
    () => props.categories.find((category) => category.key === managedKey.value) ?? null
)

function manage(category: ReferenceCategoryData) {
    managedKey.value = category.key
    managing.value = true
}
</script>

<template>
    <Head title="Konfigurasi Umum" />
    <AppLayout>
        <PageHeader
            title="Konfigurasi Umum"
            subtitle="Kelola data acuan yang dipakai di seluruh aplikasi."
        />
        <Tabs v-model="tab" class="mb-6">
            <TabsList class="flex-wrap justify-start">
                <TabsTrigger v-for="item in tabs" :key="item.value" :value="item.value">{{
                    item.label
                }}</TabsTrigger>
            </TabsList>
        </Tabs>
        <div class="grid gap-6 lg:grid-cols-2">
            <ReferenceCategoryCard
                v-for="card in visibleCards"
                :key="card.group"
                :title="card.title"
                :categories="card.categories"
                @manage="manage"
            />
            <ReferenceItemCreateCard :categories="categories" />
        </div>
        <NotificationChannelCard
            v-if="showsChannels"
            class="mt-6"
            :rows="notificationChannels"
            :options="channelOptions"
        />
        <ReferenceItemsDialog v-model:open="managing" :category="managed" />
    </AppLayout>
</template>
