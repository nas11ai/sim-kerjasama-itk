<script setup lang="ts">
/**
 * BR-REV-07 — penolakan hanya lewat aksi eksplisit Operator (ADR 0006).
 *
 * Alasannya wajib, dan itu bukan formalitas: penolakan menutup pengajuan
 * selamanya, dan tanpa alasan tertulis peneliti tidak punya dasar untuk
 * memperbaiki maupun mengajukan banding. Tombol kirim karena itu tetap
 * nonaktif selama alasannya kosong — penolakan tidak boleh tergelincir.
 */
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog'
import { Alert, AlertDescription } from '@/Components/ui/alert'
import { Button } from '@/Components/ui/button'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { AlertTriangle, Loader2 } from 'lucide-vue-next'
import { useToast } from '@/Components/ui/toast/use-toast'

interface Props {
    open: boolean
    submissionId: number
    submissionTitle: string
}

const props = defineProps<Props>()
const emit = defineEmits<{ 'update:open': [boolean] }>()

const { toast } = useToast()

const reason = ref('')
const submitting = ref(false)
const errorMessage = ref<string | null>(null)

const canSubmit = computed(() => reason.value.trim().length > 0 && !submitting.value)

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            reason.value = ''
            errorMessage.value = null
        }
    }
)

function submit() {
    if (!canSubmit.value) return

    submitting.value = true
    errorMessage.value = null

    router.post(
        route('admin.submissions.reject', props.submissionId),
        { reason: reason.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Pengajuan ditolak',
                    description: 'Alasan penolakan sudah dikirim ke pengaju.',
                })
                emit('update:open', false)
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
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Tolak Pengajuan</DialogTitle>
                <DialogDescription>{{ submissionTitle }}</DialogDescription>
            </DialogHeader>

            <Alert variant="destructive">
                <AlertTriangle class="h-4 w-4" />
                <AlertDescription>
                    Penolakan bersifat final dan tidak dapat dibatalkan. Pengajuan yang keliru
                    ditolak harus diajukan ulang sebagai pengajuan baru.
                </AlertDescription>
            </Alert>

            <div class="space-y-2">
                <Label for="rejection-reason">
                    Alasan penolakan <span class="text-destructive">*</span>
                </Label>
                <Textarea
                    id="rejection-reason"
                    v-model="reason"
                    rows="5"
                    placeholder="Jelaskan dasar penolakan agar pengaju dapat memperbaikinya."
                />
                <p class="text-xs text-muted-foreground">
                    Alasan ini dikirim ke pengaju dan tersimpan sebagai jejak keputusan.
                </p>
                <p v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="submitting"
                    @click="emit('update:open', false)"
                >
                    Batal
                </Button>
                <Button variant="destructive" :disabled="!canSubmit" @click="submit">
                    <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                    Tolak Pengajuan
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
