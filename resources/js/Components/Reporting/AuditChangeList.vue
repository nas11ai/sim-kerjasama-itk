<script setup lang="ts">
import { computed } from 'vue'

import { formatRupiah } from '@/lib/format'
import type { AuditValue } from '@/types/reporting'

// BUD-ITEM-06 — nilai lama → baru per kunci. Kunci uang anggaran tampil sebagai Rupiah.
const props = defineProps<{
    old: Record<string, AuditValue> | null
    new: Record<string, AuditValue> | null
}>()

const MONEY_KEYS = ['total', 'unit_price']

const display = (key: string, value: AuditValue | undefined): string => {
    if (value === undefined || value === null || value === '') return '—'
    if (typeof value === 'number' && MONEY_KEYS.includes(key)) return formatRupiah(value)
    return String(value)
}

const changes = computed(() => {
    const keys = [...new Set([...Object.keys(props.old ?? {}), ...Object.keys(props.new ?? {})])]
    return keys.map((key) => ({
        key,
        before: display(key, props.old?.[key]),
        after: display(key, props.new?.[key]),
    }))
})
</script>

<template>
    <p v-if="changes.length === 0" class="text-sm text-muted-foreground">
        Tidak ada perubahan nilai yang tercatat.
    </p>
    <dl v-else class="flex flex-col gap-2 rounded-md border p-3 text-sm">
        <div v-for="change in changes" :key="change.key" class="min-w-0">
            <dt class="font-mono text-xs text-muted-foreground">{{ change.key }}</dt>
            <dd class="break-words">{{ change.before }} → {{ change.after }}</dd>
        </div>
    </dl>
</template>
