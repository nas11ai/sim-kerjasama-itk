<script setup lang="ts">
/**
 * BR-SCH-01, BR-SCH-02, BR-SCH-03, BR-SCH-10 — pengelolaan skema.
 *
 * Sebelum layar ini skema hanya bisa dibuat lewat seeder, padahal M1 sudah
 * memakai `max_budget` untuk menolak anggaran dan `max_members` untuk menolak
 * anggota. Batas yang mengikat pengajuan tapi tidak bisa disunting siapa pun
 * adalah batas yang cepat atau lambat salah.
 *
 * Jumlah pengajuan aktif ditampilkan di baris yang sama dengan tombol
 * Nonaktifkan: itulah angka yang menentukan apakah tombolnya akan bekerja
 * (BR-SCH-01), dan menyembunyikannya membuat Admin menekan lalu ditolak tanpa
 * tahu sebabnya.
 */
import { computed, ref } from 'vue'
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
import { Info, Loader2, Pencil, Plus, Power, PowerOff } from 'lucide-vue-next'

interface Scheme {
    id: number
    name: string
    code: string
    max_budget: number
    max_members: number
    duration_months: number
    is_active: boolean
    active_submissions_count: number
}

interface Props {
    schemes: Scheme[]
}

const props = defineProps<Props>()

const dialogOpen = ref(false)
const editing = ref<Scheme | null>(null)

const form = useForm({
    name: '',
    code: '',
    max_budget: 0,
    max_members: 0,
    duration_months: 12,
})

const rupiah = (value: number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value)

const title = computed(() => (editing.value === null ? 'Buat Skema' : 'Edit Skema'))

function openCreate() {
    editing.value = null
    form.reset()
    form.clearErrors()
    dialogOpen.value = true
}

function openEdit(scheme: Scheme) {
    editing.value = scheme
    form.clearErrors()
    form.name = scheme.name
    form.code = scheme.code
    form.max_budget = scheme.max_budget
    form.max_members = scheme.max_members
    form.duration_months = scheme.duration_months
    dialogOpen.value = true
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false
        },
    }

    if (editing.value === null) {
        form.post(route('admin.schemes.store'), options)
    } else {
        form.put(route('admin.schemes.update', editing.value.id), options)
    }
}

function toggle(scheme: Scheme) {
    const name = scheme.is_active ? 'admin.schemes.deactivate' : 'admin.schemes.activate'

    router.post(route(name, scheme.id), {}, { preserveScroll: true })
}
</script>

<template>
    <Head title="Skema Penelitian" />

    <AuthenticatedLayout>
        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Skema Penelitian</h1>
                    <p class="text-sm text-muted-foreground">
                        Batas anggaran, anggota, dan durasi yang mengikat pengajuan.
                    </p>
                </div>
                <Button @click="openCreate">
                    <Plus class="mr-2 h-4 w-4" />
                    Buat Skema
                </Button>
            </div>

            <Alert>
                <Info class="h-4 w-4" />
                <AlertDescription>
                    Skema yang masih dipakai pengajuan aktif tidak dapat dinonaktifkan — termasuk
                    pengajuan yang sudah disetujui, karena kegiatannya masih berjalan dan laporan
                    monevnya masih memakai batas skema ini.
                </AlertDescription>
            </Alert>

            <Card v-for="scheme in props.schemes" :key="scheme.id">
                <CardHeader>
                    <CardTitle class="flex flex-wrap items-center justify-between gap-2 text-base">
                        <span class="flex items-center gap-2">
                            {{ scheme.name }}
                            <Badge variant="outline">{{ scheme.code }}</Badge>
                        </span>
                        <Badge :variant="scheme.is_active ? 'secondary' : 'destructive'">
                            {{ scheme.is_active ? 'Aktif' : 'Nonaktif' }}
                        </Badge>
                    </CardTitle>
                </CardHeader>

                <CardContent class="space-y-3">
                    <div class="grid gap-2 text-sm sm:grid-cols-4">
                        <div>
                            <span class="text-muted-foreground">Batas anggaran</span>
                            <div>{{ rupiah(scheme.max_budget) }}</div>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Maks. anggota</span>
                            <div>{{ scheme.max_members }}</div>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Durasi</span>
                            <div>{{ scheme.duration_months }} bulan</div>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Pengajuan aktif</span>
                            <div>{{ scheme.active_submissions_count }}</div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" @click="openEdit(scheme)">
                            <Pencil class="mr-2 h-4 w-4" />
                            Edit
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            :disabled="scheme.is_active && scheme.active_submissions_count > 0"
                            @click="toggle(scheme)"
                        >
                            <component
                                :is="scheme.is_active ? PowerOff : Power"
                                class="mr-2 h-4 w-4"
                            />
                            {{ scheme.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </Button>
                        <p
                            v-if="scheme.is_active && scheme.active_submissions_count > 0"
                            class="self-center text-xs text-muted-foreground"
                        >
                            Terhalang {{ scheme.active_submissions_count }} pengajuan aktif.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <p v-if="!props.schemes.length" class="text-sm text-muted-foreground">
                Belum ada skema. Buat satu untuk mulai membatasi anggaran dan anggota pengajuan.
            </p>
        </div>

        <Dialog :open="dialogOpen" @update:open="dialogOpen = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ title }}</DialogTitle>
                </DialogHeader>

                <div class="space-y-3">
                    <div class="space-y-2">
                        <Label for="scheme-name">Nama</Label>
                        <Input id="scheme-name" v-model="form.name" />
                        <p v-if="form.errors.name" class="text-sm text-destructive">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="scheme-code">Kode</Label>
                        <Input id="scheme-code" v-model="form.code" />
                        <p v-if="form.errors.code" class="text-sm text-destructive">
                            {{ form.errors.code }}
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="space-y-2">
                            <Label for="scheme-budget">Batas anggaran</Label>
                            <Input
                                id="scheme-budget"
                                v-model.number="form.max_budget"
                                type="number"
                            />
                            <p v-if="form.errors.max_budget" class="text-sm text-destructive">
                                {{ form.errors.max_budget }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="scheme-members">Maks. anggota</Label>
                            <Input
                                id="scheme-members"
                                v-model.number="form.max_members"
                                type="number"
                            />
                            <p v-if="form.errors.max_members" class="text-sm text-destructive">
                                {{ form.errors.max_members }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="scheme-duration">Durasi (bulan)</Label>
                            <Input
                                id="scheme-duration"
                                v-model.number="form.duration_months"
                                type="number"
                            />
                            <p v-if="form.errors.duration_months" class="text-sm text-destructive">
                                {{ form.errors.duration_months }}
                            </p>
                        </div>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Batas anggaran dan jumlah anggota harus lebih dari nol — skema bernilai nol
                        akan menolak setiap pengajuan tanpa ada yang tahu sebabnya.
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
