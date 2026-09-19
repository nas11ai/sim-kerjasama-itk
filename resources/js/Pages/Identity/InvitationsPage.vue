<script setup lang="ts">
/**
 * BR-IAM-16 — penerbitan dan pencabutan undangan registrasi (ADMIN-08-01).
 *
 * Tautannya muncul **sekali**, tepat setelah diterbitkan, dan tidak pernah
 * ditampilkan ulang di daftar. Daftar memperlihatkan keadaan undangan, bukan
 * kredensialnya: layar ini dibuka orang yang berhak mengelola akses, belum
 * tentu orang yang berhak memakai setiap undangan di dalamnya.
 *
 * Konfirmasi pencabutan menyebut sisa kuota yang akan mati, karena itulah
 * dampak yang sebenarnya — bukan "yakin?".
 */
import { computed, ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Alert, AlertDescription } from '@/Components/ui/alert'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Ban, Copy, Link2, Loader2 } from 'lucide-vue-next'

interface Invitation {
    id: number
    organization: string | null
    permissions: string[]
    used_count: number
    max_uses: number | null
    expires_at: string | null
    revoked_at: string | null
    is_valid: boolean
}

interface OrganizationOption {
    id: number
    name: string
}

const props = defineProps<{
    invitations: Invitation[]
    organizations: OrganizationOption[]
}>()

const page = usePage()
const terbitBaru = computed(
    () => (page.props.flash as { invitation_url?: string } | undefined)?.invitation_url
)

const form = useForm({
    organization_id: '' as string | number,
    permissions: [] as string[],
    max_uses: '' as string | number,
    expires_at: '' as string,
})

function terbitkan() {
    form.post(route('admin.invitations.store'), { preserveScroll: true })
}

const dicabut = ref<Invitation | null>(null)
const mencabut = ref(false)

// Sisa kuota adalah dampak yang dibatalkan pencabutan. Undangan tanpa batas
// pemakaian tidak punya angka, dan mengarangnya lebih buruk daripada diam.
const sisaKuota = computed(() => {
    const i = dicabut.value
    if (!i || i.max_uses === null) return null
    return Math.max(0, i.max_uses - i.used_count)
})

function cabut() {
    if (!dicabut.value) return
    mencabut.value = true
    router.delete(route('admin.invitations.revoke', { invitation: dicabut.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            mencabut.value = false
            dicabut.value = null
        },
    })
}

function salin(url: string) {
    navigator.clipboard?.writeText(url)
}

function keadaan(i: Invitation): {
    label: string
    variant: 'default' | 'secondary' | 'destructive'
} {
    if (i.revoked_at) return { label: 'Dicabut', variant: 'destructive' }
    if (!i.is_valid) return { label: 'Tidak berlaku', variant: 'secondary' }
    return { label: 'Berlaku', variant: 'default' }
}
</script>

<template>
    <Head title="Undangan Registrasi" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl space-y-6 p-4">
            <div>
                <h1 class="text-2xl font-semibold">Undangan Registrasi</h1>
                <p class="text-muted-foreground text-sm">
                    Undang peneliti eksternal lewat link registrasi — akun langsung aktif tanpa
                    verifikasi NIDN.
                </p>
            </div>

            <Alert v-if="terbitBaru">
                <Link2 class="size-4" />
                <AlertDescription class="flex flex-wrap items-center gap-2">
                    <span class="break-all font-mono text-xs">{{ terbitBaru }}</span>
                    <Button size="sm" variant="outline" @click="salin(terbitBaru)">
                        <Copy class="mr-1 size-3" /> Salin
                    </Button>
                    <span class="text-muted-foreground w-full text-xs">
                        Tautan ini hanya ditampilkan sekali. Setelah halaman ditinggalkan, ia tidak
                        bisa dibaca ulang dari daftar.
                    </span>
                </AlertDescription>
            </Alert>

            <Card>
                <CardHeader><CardTitle>Buat Undangan Baru</CardTitle></CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-1">
                        <Label for="organization_id">Organisasi Tujuan</Label>
                        <select
                            id="organization_id"
                            v-model="form.organization_id"
                            class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                        >
                            <option value="">Pilih organisasi</option>
                            <option v-for="o in props.organizations" :key="o.id" :value="o.id">
                                {{ o.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.organization_id" class="text-destructive text-xs">
                            {{ form.errors.organization_id }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <Label for="max_uses">Maks. Pemakaian</Label>
                        <Input
                            id="max_uses"
                            v-model="form.max_uses"
                            type="number"
                            min="1"
                            placeholder="Tanpa batas"
                        />
                        <p v-if="form.errors.max_uses" class="text-destructive text-xs">
                            {{ form.errors.max_uses }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <Label for="expires_at">Berlaku Sampai</Label>
                        <Input id="expires_at" v-model="form.expires_at" type="date" />
                        <p v-if="form.errors.expires_at" class="text-destructive text-xs">
                            {{ form.errors.expires_at }}
                        </p>
                    </div>

                    <div class="flex items-end">
                        <Button
                            :disabled="form.processing || !form.organization_id"
                            @click="terbitkan"
                        >
                            <Loader2 v-if="form.processing" class="mr-2 size-4 animate-spin" />
                            Buat Undangan
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Undangan Terbit</CardTitle></CardHeader>
                <CardContent>
                    <p v-if="props.invitations.length === 0" class="text-muted-foreground text-sm">
                        Belum ada undangan yang diterbitkan.
                    </p>

                    <table v-else class="w-full text-sm">
                        <thead class="text-muted-foreground text-left">
                            <tr>
                                <th class="py-2">Organisasi</th>
                                <th class="py-2">Terpakai / Kuota</th>
                                <th class="py-2">Berlaku s.d.</th>
                                <th class="py-2">Keadaan</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="i in props.invitations" :key="i.id" class="border-t">
                                <td class="py-2">{{ i.organization ?? '—' }}</td>
                                <td class="py-2">{{ i.used_count }} / {{ i.max_uses ?? '∞' }}</td>
                                <td class="py-2">{{ i.expires_at ?? 'Tanpa batas' }}</td>
                                <td class="py-2">
                                    <Badge :variant="keadaan(i).variant">{{
                                        keadaan(i).label
                                    }}</Badge>
                                </td>
                                <td class="py-2 text-right">
                                    <Button
                                        v-if="!i.revoked_at"
                                        size="sm"
                                        variant="destructive"
                                        @click="dicabut = i"
                                    >
                                        <Ban class="mr-1 size-3" /> Cabut
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>

        <Dialog :open="dicabut !== null" @close="dicabut = null">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cabut undangan ini?</DialogTitle>
                    <DialogDescription>
                        <template v-if="sisaKuota !== null">
                            Mencabut undangan ini akan menghentikan {{ sisaKuota }} pemakaian yang
                            tersisa.
                        </template>
                        <template v-else>
                            Mencabut undangan ini akan menghentikan pemakaian berikutnya.
                        </template>
                        Akun yang sudah terlanjur dibuat tidak terpengaruh.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4">
                    <Button variant="outline" @click="dicabut = null">Batal</Button>
                    <Button variant="destructive" :disabled="mencabut" @click="cabut">
                        <Loader2 v-if="mencabut" class="mr-2 size-4 animate-spin" />
                        Cabut Undangan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
