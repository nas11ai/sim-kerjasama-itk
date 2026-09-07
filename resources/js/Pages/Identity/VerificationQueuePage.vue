<script setup lang="ts">
/**
 * BR-IAM-04, BR-IAM-12, BR-IAM-13 — antrian verifikasi NIDN.
 *
 * Antrian dibangun dari status `pending` di backend, bukan dari negasi "belum
 * diverifikasi": akun jalur undangan lahir aktif tanpa NIDN, dan barisnya
 * tidak akan pernah bisa diverifikasi maupun ditolak dengan jujur.
 *
 * Alasan penolakan wajib, dan tombolnya tetap nonaktif selama alasannya
 * kosong — penolakan menutup jalan seseorang, dan tanpa alasan pendaftar tidak
 * punya apa pun untuk diperbaiki sementara mendaftar ulang mustahil.
 *
 * Bagian "Pernah ditolak" sengaja tetap ada: penolakan **tidak terminal**, dan
 * akun yang NIDN-nya sudah dikoreksi diverifikasi dari sana.
 */
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
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
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { useToast } from '@/Components/ui/toast/use-toast'
import { BadgeCheck, Check, Info, Loader2, RotateCcw, X } from 'lucide-vue-next'

interface PendingProfile {
    id: number
    name: string | null
    email: string | null
    nidn: string | null
    organization: string | null
    registered_at: string | null
}

interface RejectedProfile {
    id: number
    name: string | null
    email: string | null
    nidn: string | null
    reason: string | null
}

interface Props {
    pending: PendingProfile[]
    rejected: RejectedProfile[]
}

const props = defineProps<Props>()

const { toast } = useToast()

const dialogOpen = ref(false)
const target = ref<PendingProfile | null>(null)
const reason = ref('')
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const pendingId = ref<number | null>(null)

const canReject = computed(() => reason.value.trim().length >= 3 && !submitting.value)

function openReject(profile: PendingProfile) {
    target.value = profile
    reason.value = ''
    errorMessage.value = null
    dialogOpen.value = true
}

function verify(id: number) {
    pendingId.value = id

    router.post(
        route('admin.verifications.verify', id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast({ title: 'NIDN diverifikasi' }),
            onFinish: () => {
                pendingId.value = null
            },
        }
    )
}

function reject() {
    if (!canReject.value || target.value === null) return

    submitting.value = true
    errorMessage.value = null

    router.post(
        route('admin.verifications.reject', target.value.id),
        { reason: reason.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Pendaftaran ditolak',
                    description: 'Alasannya sudah dikirimkan ke pendaftar.',
                })
                dialogOpen.value = false
            },
            onError: (errors) => {
                errorMessage.value = errors.reason ?? 'Penolakan gagal diproses.'
            },
            onFinish: () => {
                submitting.value = false
            },
        }
    )
}
</script>

<template>
    <Head title="Antrian Verifikasi NIDN" />

    <AuthenticatedLayout>
        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Antrian Verifikasi NIDN</h1>
                    <p class="text-sm text-muted-foreground">
                        Periksa kecocokan NIDN sebelum menyetujui pendaftar baru.
                    </p>
                </div>
                <Badge variant="secondary">{{ props.pending.length }} menunggu</Badge>
            </div>

            <Alert>
                <Info class="h-4 w-4" />
                <AlertDescription>
                    Antrian ini hanya memuat pendaftar jalur mandiri. Peneliti yang masuk lewat
                    undangan tidak punya NIDN untuk diperiksa dan akunnya sudah aktif sejak
                    mendaftar.
                </AlertDescription>
            </Alert>

            <Card v-for="profile in props.pending" :key="profile.id">
                <CardContent class="flex flex-wrap items-center justify-between gap-3 pt-6">
                    <div>
                        <strong>{{ profile.name }}</strong>
                        <div class="text-sm text-muted-foreground">{{ profile.email }}</div>
                        <div class="text-xs text-muted-foreground">
                            NIDN: {{ profile.nidn }} · {{ profile.organization }} · daftar
                            {{ profile.registered_at }}
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            size="sm"
                            :disabled="pendingId === profile.id"
                            @click="verify(profile.id)"
                        >
                            <Loader2
                                v-if="pendingId === profile.id"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            <Check v-else class="mr-2 h-4 w-4" />
                            Verifikasi
                        </Button>
                        <Button variant="outline" size="sm" @click="openReject(profile)">
                            <X class="mr-2 h-4 w-4" />
                            Tolak
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <p v-if="!props.pending.length" class="text-sm text-muted-foreground">
                Tidak ada pendaftar yang menunggu verifikasi.
            </p>

            <div v-if="props.rejected.length" class="space-y-3 pt-4">
                <div>
                    <h2 class="text-lg font-semibold">Pernah Ditolak</h2>
                    <p class="text-sm text-muted-foreground">
                        Penolakan tidak permanen. Setelah NIDN dikoreksi, akun ini dapat
                        diverifikasi dari sini — mendaftar ulang mustahil karena email dan NIDN-nya
                        sudah terpakai.
                    </p>
                </div>

                <Card v-for="profile in props.rejected" :key="profile.id">
                    <CardHeader>
                        <CardTitle
                            class="flex flex-wrap items-center justify-between gap-2 text-base"
                        >
                            <span class="flex items-center gap-2">
                                {{ profile.name }}
                                <Badge variant="destructive">Ditolak</Badge>
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="pendingId === profile.id"
                                @click="verify(profile.id)"
                            >
                                <RotateCcw class="mr-2 h-4 w-4" />
                                Verifikasi Sekarang
                            </Button>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-1 text-sm">
                        <div class="text-muted-foreground">
                            {{ profile.email }} · NIDN {{ profile.nidn }}
                        </div>
                        <p v-if="profile.reason">Alasan: {{ profile.reason }}</p>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog :open="dialogOpen" @update:open="dialogOpen = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Tolak Pendaftaran</DialogTitle>
                    <DialogDescription>
                        {{ target?.name }} — NIDN {{ target?.nidn }}
                    </DialogDescription>
                </DialogHeader>

                <Alert>
                    <BadgeCheck class="h-4 w-4" />
                    <AlertDescription>
                        Penolakan tidak menghapus akun. Pendaftar tetap dapat masuk, membaca alasan
                        ini, dan diverifikasi ulang setelah NIDN-nya dikoreksi.
                    </AlertDescription>
                </Alert>

                <div class="space-y-2">
                    <Label for="rejection-reason">
                        Alasan penolakan <span class="text-destructive">*</span>
                    </Label>
                    <Textarea
                        id="rejection-reason"
                        v-model="reason"
                        rows="4"
                        placeholder="Mis. NIDN tidak ditemukan di pangkalan data PDDikti atas nama yang sama."
                    />
                    <p class="text-xs text-muted-foreground">
                        Dikirimkan ke pendaftar — tanpa ini ia tidak tahu apa yang harus diperbaiki.
                    </p>
                    <p v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="submitting" @click="dialogOpen = false">
                        Batal
                    </Button>
                    <Button variant="destructive" :disabled="!canReject" @click="reject">
                        <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                        Tolak Pendaftaran
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
