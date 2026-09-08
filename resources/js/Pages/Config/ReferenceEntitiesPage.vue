<script setup lang="ts">
/**
 * BR-SC-01, BR-SC-02, BR-SC-03 — Konfigurasi Umum.
 *
 * Jumlah pemakai ditampilkan di baris yang sama dengan tombol Hapus: itulah
 * angka yang menentukan apakah tombolnya akan bekerja, dan menyembunyikannya
 * membuat Admin menekan lalu ditolak tanpa tahu sebabnya.
 *
 * Acuan read-only tidak diberi tombol sama sekali. Tombol yang menolak saat
 * ditekan menyiratkan bahwa orang yang lebih berwenang bisa menekannya —
 * padahal tidak ada.
 */
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Alert, AlertDescription } from '@/Components/ui/alert'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table'
import { Checkbox } from '@/Components/ui/checkbox'
import { Bell, Info, Loader2, Lock, Pencil, Plus, Power, PowerOff, Trash2 } from 'lucide-vue-next'

interface RecordRow {
    id: number
    name: string
    is_active: boolean | null
    usage_count: number
}

interface EntityGroup {
    key: string
    label: string
    used_by: string
    editable: boolean
    deactivatable: boolean
    records: RecordRow[]
}

interface ChannelSetting {
    event_type: string
    channels: string[]
}

const props = defineProps<{
    entities: EntityGroup[]
    notificationChannels: ChannelSetting[]
    channelOptions: { value: string; label: string }[]
}>()

// BR-NOT-06 — channel adalah konfigurasi. Yang tidak bisa disunting adalah
// ada-tidaknya deklarasi: barisnya lahir dari kelas notifikasi, bukan dari
// layar ini.
function toggleChannel(setting: ChannelSetting, channel: string, checked: unknown) {
    const next =
        checked === true
            ? [...new Set([...setting.channels, channel])]
            : setting.channels.filter((c) => c !== channel)

    router.put(
        route('admin.reference.notification-channels.update', setting.event_type),
        { channels: next },
        { preserveScroll: true }
    )
}

const dialogOpen = ref(false)
const activeEntity = ref<EntityGroup | null>(null)
const editing = ref<RecordRow | null>(null)

const form = useForm({ name: '' })

function openCreate(entity: EntityGroup) {
    activeEntity.value = entity
    editing.value = null
    form.reset()
    form.clearErrors()
    dialogOpen.value = true
}

function openEdit(entity: EntityGroup, record: RecordRow) {
    activeEntity.value = entity
    editing.value = record
    form.clearErrors()
    form.name = record.name
    dialogOpen.value = true
}

function save() {
    const entity = activeEntity.value

    if (entity === null) {
        return
    }

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false
        },
    }

    if (editing.value === null) {
        form.post(route('admin.reference.store', entity.key), options)
    } else {
        form.put(route('admin.reference.update', [entity.key, String(editing.value.id)]), options)
    }
}

function toggle(entity: EntityGroup, record: RecordRow) {
    router.post(
        route('admin.reference.toggle', [entity.key, String(record.id)]),
        { is_active: !record.is_active },
        { preserveScroll: true }
    )
}

function destroy(entity: EntityGroup, record: RecordRow) {
    router.delete(route('admin.reference.destroy', [entity.key, String(record.id)]), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Konfigurasi Umum" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Konfigurasi Umum</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <Alert>
                    <Info class="h-4 w-4" />
                    <AlertDescription>
                        Data acuan yang masih dipakai tidak dapat dihapus — nonaktifkan saja agar
                        tidak lagi ditawarkan tanpa menghapus jejak yang sudah memakainya. Mengganti
                        nama berarti "ini hal yang sama dengan sebutan yang diperbaiki": laporan
                        lama ikut memakai nama baru. Kalau kategorinya berubah makna, buat entri
                        baru lalu nonaktifkan yang lama.
                    </AlertDescription>
                </Alert>

                <Card>
                    <CardHeader class="flex flex-row items-start gap-3">
                        <Bell class="mt-1 h-5 w-5 text-muted-foreground" />
                        <div>
                            <CardTitle class="text-base">Channel Notifikasi</CardTitle>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Peristiwa yang menuntut tindakan segera sebaiknya dikirim lewat
                                keduanya. Email untuk segalanya akan membuat email berhenti dibaca
                                untuk apa pun — dan yang pertama hilang justru yang mendesak.
                            </p>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tipe Event</TableHead>
                                    <TableHead
                                        v-for="option in props.channelOptions"
                                        :key="option.value"
                                        class="w-28"
                                    >
                                        {{ option.label }}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="setting in props.notificationChannels"
                                    :key="setting.event_type"
                                >
                                    <TableCell class="font-medium">
                                        {{ setting.event_type }}
                                    </TableCell>
                                    <TableCell
                                        v-for="option in props.channelOptions"
                                        :key="option.value"
                                    >
                                        <Checkbox
                                            :model-value="setting.channels.includes(option.value)"
                                            @update:model-value="
                                                (value) =>
                                                    toggleChannel(setting, option.value, value)
                                            "
                                        />
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card v-for="entity in entities" :key="entity.key">
                    <CardHeader class="flex flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle class="flex items-center gap-2 text-base">
                                {{ entity.label }}
                                <Badge v-if="!entity.editable" variant="outline">
                                    <Lock class="mr-1 h-3 w-3" />
                                    Hanya lewat seeder
                                </Badge>
                            </CardTitle>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Dipakai oleh {{ entity.used_by }}.
                            </p>
                        </div>
                        <Button v-if="entity.editable" size="sm" @click="openCreate(entity)">
                            <Plus class="mr-2 h-4 w-4" />
                            Tambah
                        </Button>
                    </CardHeader>

                    <CardContent>
                        <p
                            v-if="entity.records.length === 0"
                            class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground"
                        >
                            Belum ada item.
                        </p>

                        <Table v-else>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nama</TableHead>
                                    <TableHead class="w-32">Dipakai</TableHead>
                                    <TableHead class="w-28">Status</TableHead>
                                    <TableHead class="w-56 text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="record in entity.records" :key="record.id">
                                    <TableCell class="font-medium">{{ record.name }}</TableCell>
                                    <TableCell>
                                        <Badge
                                            :variant="
                                                record.usage_count > 0 ? 'secondary' : 'outline'
                                            "
                                        >
                                            {{ record.usage_count }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            v-if="record.is_active !== null"
                                            :variant="
                                                record.is_active ? 'secondary' : 'destructive'
                                            "
                                        >
                                            {{ record.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </Badge>
                                        <span v-else class="text-xs text-muted-foreground">—</span>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div v-if="entity.editable" class="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="openEdit(entity, record)"
                                            >
                                                <Pencil class="h-4 w-4" />
                                                <span class="sr-only">Ganti nama</span>
                                            </Button>
                                            <Button
                                                v-if="entity.deactivatable"
                                                variant="ghost"
                                                size="sm"
                                                @click="toggle(entity, record)"
                                            >
                                                <component
                                                    :is="record.is_active ? PowerOff : Power"
                                                    class="h-4 w-4"
                                                />
                                                <span class="sr-only">
                                                    {{
                                                        record.is_active
                                                            ? 'Nonaktifkan'
                                                            : 'Aktifkan'
                                                    }}
                                                </span>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                :disabled="record.usage_count > 0"
                                                :title="
                                                    record.usage_count > 0
                                                        ? `Masih dipakai ${record.usage_count} ${entity.used_by}`
                                                        : 'Hapus'
                                                "
                                                @click="destroy(entity, record)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                                <span class="sr-only">Hapus</span>
                                            </Button>
                                        </div>
                                        <span v-else class="text-xs text-muted-foreground">
                                            Tidak dapat diubah dari layar
                                        </span>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog :open="dialogOpen" @update:open="dialogOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{ editing === null ? 'Tambah' : 'Ganti Nama' }}
                        {{ activeEntity?.label }}
                    </DialogTitle>
                </DialogHeader>

                <div class="space-y-2">
                    <Label for="reference-name">Nama</Label>
                    <Input id="reference-name" v-model="form.name" />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                    <p v-if="editing !== null" class="text-xs text-muted-foreground">
                        Nama baru akan tampil di seluruh laporan, termasuk yang lama — tautannya
                        tidak berubah. Untuk kategori yang berubah makna, buat entri baru lalu
                        nonaktifkan yang ini.
                    </p>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="dialogOpen = false">Batal</Button>
                    <Button :disabled="form.processing" @click="save">
                        <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                        Simpan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
