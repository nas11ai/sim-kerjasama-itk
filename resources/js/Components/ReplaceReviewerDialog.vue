<script setup lang="ts">
/**
 * BR-REV-10 — mengganti reviewer pada satu siklus.
 *
 * Record lama ditandai `replaced`, bukan dihapus: evaluasinya adalah alasan
 * di balik keputusan yang mungkin sudah diambil. UI menyebutkan itu supaya
 * Operator tahu ia tidak sedang menghapus apa pun.
 *
 * Ulir diskusi yang masih terbuka ikut berpindah ke pengganti (BR-REV-15) —
 * tanpa itu catatan tersebut tidak akan pernah bisa ditutup.
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import { History, Loader2 } from 'lucide-vue-next'
import { useToast } from '@/Components/ui/toast/use-toast'

interface ReviewerOption {
    id: number
    name: string
    email: string
}

interface Props {
    open: boolean
    submissionId: number
    outgoingReviewerId: number
    outgoingReviewerName: string
    availableReviewers: ReviewerOption[]
}

const props = defineProps<Props>()
const emit = defineEmits<{ 'update:open': [boolean] }>()

const { toast } = useToast()

const incomingId = ref<string>('')
const submitting = ref(false)
const errorMessage = ref<string | null>(null)

const canSubmit = computed(() => incomingId.value !== '' && !submitting.value)

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            incomingId.value = ''
            errorMessage.value = null
        }
    }
)

function submit() {
    if (!canSubmit.value) return

    submitting.value = true
    errorMessage.value = null

    router.post(
        // Ziggy menuntut parameter rute berupa string, bukan number.
        route('admin.submissions.replace-reviewer', [
            String(props.submissionId),
            String(props.outgoingReviewerId),
        ]),
        { incoming_reviewer_id: Number(incomingId.value) },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Reviewer diganti',
                    description: 'Evaluasi reviewer sebelumnya tetap tersimpan sebagai riwayat.',
                })
                emit('update:open', false)
            },
            onError: (errors) => {
                errorMessage.value =
                    errors.incoming_reviewer_id ??
                    errors.reviewer_id ??
                    'Penggantian gagal diproses.'
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
                <DialogTitle>Ganti Reviewer</DialogTitle>
                <DialogDescription>
                    Menggantikan <strong>{{ outgoingReviewerName }}</strong> pada siklus ini.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-2">
                <Label for="incoming-reviewer">Reviewer pengganti</Label>
                <Select v-model="incomingId">
                    <SelectTrigger id="incoming-reviewer">
                        <SelectValue placeholder="Pilih reviewer" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="reviewer in availableReviewers"
                            :key="reviewer.id"
                            :value="String(reviewer.id)"
                        >
                            {{ reviewer.name }} — {{ reviewer.email }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
            </div>

            <Alert>
                <History class="h-4 w-4" />
                <AlertDescription>
                    Evaluasi reviewer sebelumnya tidak dihapus — ia tetap terbaca sebagai riwayat.
                    Catatan diskusi yang masih terbuka berpindah ke reviewer pengganti.
                </AlertDescription>
            </Alert>

            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="submitting"
                    @click="emit('update:open', false)"
                >
                    Batal
                </Button>
                <Button :disabled="!canSubmit" @click="submit">
                    <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                    Ganti Reviewer
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
