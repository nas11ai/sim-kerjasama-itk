<script setup lang="ts">
/**
 * BR-FE-08, BR-FE-15, BR-FE-16 — keadaan batas waktu satu fase.
 *
 * Fase yang lewat batas tetap ditampilkan, hanya terkunci (BR-FE-15) —
 * menghilangkannya membuat peneliti tidak bisa membaca apa yang sudah ia
 * kirim. Yang ditutup adalah kemampuan mengubah, bukan kemampuan melihat.
 *
 * `reason` sudah ditahan backend untuk peneliti (BR-FE-16); komponen ini tidak
 * mengulang aturannya, ia hanya menampilkan apa yang sampai. Kalau aturannya
 * diulang di sini, dua tempat itu akan menyimpang dan yang di layar akan kalah
 * penting dibanding yang di payload.
 */
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
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
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { CalendarClock, Loader2, Lock, LockOpen, ShieldCheck } from 'lucide-vue-next'
import { useToast } from '@/Components/ui/toast/use-toast'

interface OverrideInfo {
    id: number
    status: 'active' | 'expired'
    expires_at: string | null
    /** null untuk peneliti — hanya Operator dan Admin yang boleh membacanya. */
    reason: string | null
}

interface Props {
    submissionId: number
    isOpen: boolean
    deadline: string | null
    formPhaseDetailId: number | null
    override: OverrideInfo | null
    canGrantOverride: boolean
}

const props = defineProps<Props>()

const { toast } = useToast()

const dialogOpen = ref(false)
const reason = ref('')
const expiresAt = ref('')
const submitting = ref(false)
const errorMessage = ref<string | null>(null)

const canSubmit = computed(() => reason.value.trim().length >= 3 && !submitting.value)

const formatted = (value: string | null) =>
    value === null
        ? null
        : new Date(value.replace(' ', 'T')).toLocaleString('id-ID', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })

function openDialog() {
    reason.value = ''
    expiresAt.value = ''
    errorMessage.value = null
    dialogOpen.value = true
}

function grant() {
    if (!canSubmit.value || props.formPhaseDetailId === null) return

    submitting.value = true
    errorMessage.value = null

    router.post(
        route('admin.submissions.overrides.store', props.submissionId),
        {
            form_phase_detail_id: props.formPhaseDetailId,
            reason: reason.value.trim(),
            expires_at: expiresAt.value === '' ? null : expiresAt.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Perpanjangan diberikan',
                    description: 'Peneliti diberi tahu bahwa aksesnya dibuka kembali.',
                })
                dialogOpen.value = false
            },
            onError: (errors) => {
                errorMessage.value =
                    errors.reason ?? errors.expires_at ?? 'Perpanjangan gagal diberikan.'
            },
            onFinish: () => {
                submitting.value = false
            },
        }
    )
}

function revoke() {
    if (props.override === null) return

    router.delete(
        route('admin.submissions.overrides.destroy', [
            String(props.submissionId),
            String(props.override.id),
        ]),
        {
            preserveScroll: true,
            onSuccess: () => toast({ title: 'Perpanjangan dicabut' }),
        }
    )
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center justify-between gap-2 text-base">
                <span class="flex items-center gap-2">
                    <component :is="isOpen ? LockOpen : Lock" class="h-4 w-4" />
                    Batas Waktu Fase
                </span>
                <Badge :variant="isOpen ? 'secondary' : 'destructive'">
                    {{ isOpen ? 'Masih dapat diisi' : 'Terkunci' }}
                </Badge>
            </CardTitle>
        </CardHeader>

        <CardContent class="space-y-4">
            <div v-if="deadline" class="flex items-center gap-2 text-sm text-muted-foreground">
                <CalendarClock class="h-4 w-4" />
                Batas fase: {{ formatted(deadline) }}
            </div>

            <!-- BR-FE-16 — status perpanjangan terbaca semua pihak; alasannya
                 hanya muncul kalau backend memang mengirimkannya. -->
            <Alert v-if="override">
                <ShieldCheck class="h-4 w-4" />
                <AlertDescription class="space-y-1">
                    <p>
                        Perpanjangan
                        <strong>{{
                            override.status === 'active' ? 'aktif' : 'sudah berakhir'
                        }}</strong>
                        <template v-if="override.expires_at">
                            — berlaku sampai {{ formatted(override.expires_at) }}
                        </template>
                    </p>
                    <p v-if="override.reason" class="text-muted-foreground">
                        Alasan: {{ override.reason }}
                    </p>
                </AlertDescription>
            </Alert>

            <!-- BR-FE-08 — penolakannya menyebut jalan keluarnya. -->
            <Alert v-if="!isOpen && !canGrantOverride">
                <Lock class="h-4 w-4" />
                <AlertDescription>
                    Batas waktu fase ini sudah lewat, jadi isinya tidak dapat diubah lagi. Anda
                    tetap dapat membacanya. Hubungi Operator LPPM kalau perlu perpanjangan.
                </AlertDescription>
            </Alert>

            <div v-if="canGrantOverride" class="flex flex-wrap gap-2">
                <Button variant="outline" @click="openDialog">
                    <ShieldCheck class="mr-2 h-4 w-4" />
                    Beri Perpanjangan
                </Button>
                <Button
                    v-if="override && override.status === 'active'"
                    variant="ghost"
                    @click="revoke"
                >
                    Cabut Perpanjangan
                </Button>
            </div>
        </CardContent>
    </Card>

    <Dialog :open="dialogOpen" @update:open="dialogOpen = $event">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Beri Perpanjangan Akses</DialogTitle>
                <DialogDescription>
                    Berlaku hanya untuk pengajuan ini pada fase ini.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-2">
                <Label for="override-reason">
                    Alasan <span class="text-destructive">*</span>
                </Label>
                <Textarea
                    id="override-reason"
                    v-model="reason"
                    rows="4"
                    placeholder="Mis. kendala jaringan kampus saat batas waktu berjalan."
                />
                <p class="text-xs text-muted-foreground">
                    Tersimpan sebagai jejak audit dan hanya terbaca Operator serta Admin — peneliti
                    melihat status perpanjangannya, bukan alasannya.
                </p>
            </div>

            <div class="space-y-2">
                <Label for="override-expires">Berlaku sampai</Label>
                <Input id="override-expires" v-model="expiresAt" type="datetime-local" />
                <p class="text-xs text-muted-foreground">
                    Kosongkan kalau perpanjangan tidak dibatasi waktu.
                </p>
            </div>

            <p v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>

            <DialogFooter>
                <Button variant="outline" :disabled="submitting" @click="dialogOpen = false">
                    Batal
                </Button>
                <Button :disabled="!canSubmit" @click="grant">
                    <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                    Beri Perpanjangan
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
