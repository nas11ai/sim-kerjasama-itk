<script setup lang="ts">
/**
 * BR-RPT-04, BR-RPT-05 — jejak audit.
 *
 * Tidak ada tombol Hapus maupun Sunting di layar ini, dan itu bukan
 * kelalaian: append-only berarti jalurnya memang tidak ada. Tombol yang
 * menolak saat ditekan justru menyiratkan bahwa penghapusan mungkin dilakukan
 * oleh orang yang lebih berwenang.
 */
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table'
import { ChevronLeft, ChevronRight, Inbox, Lock, ShieldCheck } from 'lucide-vue-next'

interface AuditEntry {
    id: number
    description: string
    subject_type: string
    subject_id: number | null
    causer: { id: number; name: string } | null
    properties: Record<string, unknown> | null
    created_at: string | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

const props = defineProps<{
    entries: {
        data: AuditEntry[]
        links: PaginationLink[]
        from: number | null
        to: number | null
        total: number
    }
    seesEverything: boolean
}>()

const subtitle = computed(() =>
    props.seesEverything
        ? 'Catatan seluruh aktivitas sistem — permanen dan tidak dapat dihapus.'
        : 'Riwayat pengajuan yang Anda ikuti. Riwayat sistem secara keseluruhan hanya terbuka bagi Operator LPPM.'
)

const prevLink = computed(() => props.entries.links[0]?.url ?? null)
const nextLink = computed(() => props.entries.links[props.entries.links.length - 1]?.url ?? null)

const formatTime = (value: string | null) => {
    if (!value) {
        return '—'
    }

    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const subjectLabel = (entry: AuditEntry) =>
    entry.subject_id === null ? entry.subject_type : `${entry.subject_type} #${entry.subject_id}`

// Bentuk `old`/`new` diseragamkan AuditTrail supaya baris ini tidak perlu
// mengenali sekian bentuk properti yang berbeda-beda.
const changePairs = (entry: AuditEntry) => {
    const before = (entry.properties?.old ?? null) as Record<string, unknown> | null
    const after = (entry.properties?.new ?? null) as Record<string, unknown> | null

    if (before === null && after === null) {
        return []
    }

    const keys = new Set([...Object.keys(before ?? {}), ...Object.keys(after ?? {})])

    return [...keys].map((key) => ({
        key,
        before: before?.[key] ?? null,
        after: after?.[key] ?? null,
    }))
}

const show = (value: unknown) =>
    value === null || value === undefined || value === '' ? '—' : String(value)
</script>

<template>
    <Head title="Jejak Audit" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Jejak Audit</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <Card>
                    <CardHeader class="flex flex-row items-start gap-3">
                        <ShieldCheck class="mt-1 h-5 w-5 text-muted-foreground" />
                        <div>
                            <CardTitle class="text-base">
                                {{ seesEverything ? 'Seluruh Sistem' : 'Pengajuan Saya' }}
                            </CardTitle>
                            <p class="mt-1 text-sm text-muted-foreground">{{ subtitle }}</p>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Lock class="h-3.5 w-3.5" />
                            Entri audit bersifat permanen — tidak dapat dihapus maupun disunting.
                            Koreksi dicatat sebagai tindakan baru.
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">
                            Entri
                            <Badge variant="secondary" class="ml-2">{{ entries.total }}</Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="entries.data.length === 0"
                            class="flex flex-col items-center gap-2 rounded-md border border-dashed p-10 text-center"
                        >
                            <Inbox class="h-8 w-8 text-muted-foreground/50" />
                            <p class="text-sm text-muted-foreground">
                                Belum ada aktivitas tercatat.
                            </p>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="whitespace-nowrap">Waktu</TableHead>
                                        <TableHead>Subjek</TableHead>
                                        <TableHead>Aksi</TableHead>
                                        <TableHead>Pelaku</TableHead>
                                        <TableHead>Perubahan</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="entry in entries.data" :key="entry.id">
                                        <TableCell class="whitespace-nowrap text-xs">
                                            {{ formatTime(entry.created_at) }}
                                        </TableCell>
                                        <TableCell class="whitespace-nowrap">
                                            {{ subjectLabel(entry) }}
                                        </TableCell>
                                        <TableCell class="font-medium">
                                            {{ entry.description }}
                                        </TableCell>
                                        <TableCell>
                                            {{ entry.causer?.name ?? 'Sistem' }}
                                        </TableCell>
                                        <TableCell>
                                            <span
                                                v-if="changePairs(entry).length === 0"
                                                class="text-muted-foreground"
                                            >
                                                —
                                            </span>
                                            <div v-else class="space-y-0.5 text-xs">
                                                <div
                                                    v-for="pair in changePairs(entry)"
                                                    :key="pair.key"
                                                >
                                                    <span class="text-muted-foreground">
                                                        {{ pair.key }}:
                                                    </span>
                                                    {{ show(pair.before) }} →
                                                    <strong>{{ show(pair.after) }}</strong>
                                                </div>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <div
                            v-if="entries.data.length > 0"
                            class="mt-4 flex items-center justify-between"
                        >
                            <p class="text-xs text-muted-foreground">
                                Menampilkan {{ entries.from }}–{{ entries.to }} dari
                                {{ entries.total }}
                            </p>
                            <div class="flex gap-2">
                                <Link v-if="prevLink" :href="prevLink">
                                    <Button variant="outline" size="sm">
                                        <ChevronLeft class="h-4 w-4" />
                                        Sebelumnya
                                    </Button>
                                </Link>
                                <Link v-if="nextLink" :href="nextLink">
                                    <Button variant="outline" size="sm">
                                        Berikutnya
                                        <ChevronRight class="h-4 w-4" />
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
