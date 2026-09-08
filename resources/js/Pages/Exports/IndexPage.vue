<script setup lang="ts">
/**
 * BR-RPT-02, BR-RPT-03, BR-RPT-12 — ekspor data.
 *
 * Tiga keadaan berkas yang sengaja dibedakan di layar, karena tindakan
 * lanjutannya berbeda: siap diunduh, tautan kedaluwarsa (berkas masih ada —
 * muat ulang halaman untuk tautan baru), dan berkas terhapus setelah masa
 * simpan (tidak ada yang bisa diambil — ekspor ulang).
 */
import { Head, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Alert, AlertDescription } from '@/Components/ui/alert'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Label } from '@/Components/ui/label'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table'
import { Download, Info, Loader2, RotateCcw, Trash2 } from 'lucide-vue-next'

interface ExportRow {
    id: number
    type: string
    type_value: string
    status: string
    row_count: number
    attempts: number
    failure_reason: string | null
    completed_at: string | null
    file_is_gone: boolean
    download_url: string | null
}

const props = defineProps<{
    types: { value: string; label: string }[]
    threshold: number
    retentionHours: number
    jobs: ExportRow[]
}>()

const form = useForm({ type: props.types[0]?.value ?? '' })

const statusLabel: Record<string, string> = {
    queued: 'Antre',
    processing: 'Diproses',
    completed: 'Selesai',
    failed: 'Gagal',
}

const statusVariant = (status: string) =>
    status === 'failed' ? 'destructive' : status === 'completed' ? 'secondary' : 'outline'

const formatTime = (value: string | null) =>
    value === null
        ? '—'
        : new Date(value).toLocaleString('id-ID', {
              day: '2-digit',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
          })

function submit() {
    form.post(route('exports.store'), { preserveScroll: true })
}

function again(row: ExportRow) {
    router.post(route('exports.store'), { type: row.type_value }, { preserveScroll: true })
}
</script>

<template>
    <Head title="Export Data" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Export Data</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <Alert>
                    <Info class="h-4 w-4" />
                    <AlertDescription>
                        Ekspor sampai {{ threshold }} baris selesai langsung; di atas itu
                        dijadwalkan di latar belakang dan Anda diberi tahu saat berkasnya siap.
                        Berkas dihapus otomatis setelah {{ retentionHours }} jam — setelah itu
                        ekspornya perlu dijalankan ulang.
                    </AlertDescription>
                </Alert>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Minta Ekspor Baru</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="space-y-2">
                            <Label for="export-type">Jenis Export</Label>
                            <Select id="export-type" v-model="form.type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih jenis" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="type in types"
                                        :key="type.value"
                                        :value="type.value"
                                    >
                                        {{ type.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.type" class="text-sm text-destructive">
                                {{ form.errors.type }}
                            </p>
                        </div>
                        <Button :disabled="form.processing" @click="submit">
                            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                            <Download v-else class="mr-2 h-4 w-4" />
                            Minta Ekspor
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Riwayat Ekspor</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p
                            v-if="jobs.length === 0"
                            class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground"
                        >
                            Belum ada permintaan ekspor.
                        </p>

                        <Table v-else>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Jenis</TableHead>
                                    <TableHead class="w-24">Baris</TableHead>
                                    <TableHead class="w-28">Status</TableHead>
                                    <TableHead class="w-36">Selesai</TableHead>
                                    <TableHead class="w-40 text-right">Berkas</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="row in jobs" :key="row.id">
                                    <TableCell class="font-medium">{{ row.type }}</TableCell>
                                    <TableCell>{{ row.row_count }}</TableCell>
                                    <TableCell>
                                        <Badge :variant="statusVariant(row.status)">
                                            {{ statusLabel[row.status] ?? row.status }}
                                        </Badge>
                                        <p
                                            v-if="row.status === 'failed'"
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ row.attempts }} percobaan — sistem berhenti mencoba.
                                        </p>
                                    </TableCell>
                                    <TableCell class="text-xs">
                                        {{ formatTime(row.completed_at) }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <a v-if="row.download_url" :href="row.download_url">
                                            <Button variant="outline" size="sm">
                                                <Download class="mr-1 h-4 w-4" />
                                                Unduh
                                            </Button>
                                        </a>
                                        <div
                                            v-else-if="row.file_is_gone"
                                            class="flex items-center justify-end gap-2"
                                        >
                                            <span
                                                class="flex items-center gap-1 text-xs text-muted-foreground"
                                            >
                                                <Trash2 class="h-3 w-3" />
                                                Sudah dihapus
                                            </span>
                                            <Button variant="ghost" size="sm" @click="again(row)">
                                                <RotateCcw class="h-4 w-4" />
                                                <span class="sr-only">Ekspor ulang</span>
                                            </Button>
                                        </div>
                                        <Button
                                            v-else-if="row.status === 'failed'"
                                            variant="ghost"
                                            size="sm"
                                            @click="again(row)"
                                        >
                                            <RotateCcw class="mr-1 h-4 w-4" />
                                            Coba Lagi
                                        </Button>
                                        <span v-else class="text-xs text-muted-foreground">—</span>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
