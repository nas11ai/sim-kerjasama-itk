<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Settings } from 'lucide-vue-next'
import { reactive } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import { Checkbox } from '@/Components/ui/checkbox'
import { Label } from '@/Components/ui/label'
import type { NotificationChannelData } from '@/types/system-config'
import type { FilterOption } from '@/types/submission-page'

const props = defineProps<{ rows: NotificationChannelData[]; options: FilterOption[] }>()

// NOT-CHANNEL-04 — event tanpa deklarasi: centang hanya memilih, "Tetapkan Channel" yang mengirim.
const drafts = reactive<Record<string, string[]>>({})

const selected = (row: NotificationChannelData) =>
    row.declared ? row.channels : (drafts[row.eventType] ?? [])

const ordered = (values: string[]) =>
    props.options.map((option) => option.value).filter((value) => values.includes(value))

function declare(eventType: string, channels: string[]) {
    router.put(
        route('admin.reference.notification-channels.update', eventType),
        { channels },
        { preserveScroll: true }
    )
}

function change(row: NotificationChannelData, value: string, checked: boolean | 'indeterminate') {
    const others = selected(row).filter((current) => current !== value)
    const next = ordered(checked === true ? [...others, value] : others)
    if (!row.declared) {
        drafts[row.eventType] = next
        return
    }
    // BR-NOT-06 — deklarasi tidak boleh kosong; checkbox terakhir juga dimatikan.
    if (next.length === 0) return
    declare(row.eventType, next)
}

const isLast = (row: NotificationChannelData, value: string) =>
    row.declared && row.channels.length === 1 && row.channels.includes(value)
</script>

<template>
    <SectionCard title="Channel Notifikasi">
        <ul class="divide-y">
            <li
                v-for="row in rows"
                :key="row.eventType"
                class="space-y-2 py-2 first:pt-0 last:pb-0"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="text-sm font-medium">{{ row.eventType }}</span>
                    <div class="flex gap-4">
                        <div
                            v-for="option in options"
                            :key="option.value"
                            class="flex items-center gap-2"
                        >
                            <Checkbox
                                :id="`channel-${row.eventType}-${option.value}`"
                                :model-value="selected(row).includes(option.value)"
                                :disabled="isLast(row, option.value)"
                                @update:model-value="
                                    (checked) => change(row, option.value, checked)
                                "
                            />
                            <Label
                                :for="`channel-${row.eventType}-${option.value}`"
                                class="font-normal"
                                >{{ option.label }}</Label
                            >
                        </div>
                    </div>
                </div>
                <template v-if="!row.declared">
                    <AppBanner tone="denial">
                        Event ini belum punya deklarasi channel. Tetapkan in-app, email, atau
                        keduanya sebelum event dipakai.
                    </AppBanner>
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="selected(row).length === 0"
                        @click="declare(row.eventType, selected(row))"
                        ><Settings class="size-4" />Tetapkan Channel</Button
                    >
                </template>
            </li>
        </ul>
    </SectionCard>
</template>
