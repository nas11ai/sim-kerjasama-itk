<script setup lang="ts">
/**
 * BR-SM-09, kanonik BR-IAM-10 — memindahkan kepemilikan pengajuan.
 *
 * Penerimanya wajib co-investigator aktif. Kalau daftarnya kosong, yang
 * ditampilkan bukan dropdown kosong melainkan penjelasan apa yang harus
 * dilakukan lebih dulu — Operator yang hanya melihat pilihan kosong tidak
 * tahu bahwa yang kurang adalah co-investigator, bukan haknya sendiri.
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
import { Info, Loader2, UserCog } from 'lucide-vue-next'
import { useToast } from '@/Components/ui/toast/use-toast'

interface Candidate {
    id: number
    name: string
    email: string
}

interface Props {
    open: boolean
    submissionId: number
    currentOwnerName: string
    /** Hanya co-investigator aktif — penyaringnya di backend, bukan di sini. */
    candidates: Candidate[]
}

const props = defineProps<Props>()
const emit = defineEmits<{ 'update:open': [boolean] }>()

const { toast } = useToast()

const successorId = ref<string>('')
const submitting = ref(false)
const errorMessage = ref<string | null>(null)

const hasCandidates = computed(() => props.candidates.length > 0)
const canSubmit = computed(() => successorId.value !== '' && !submitting.value)

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            successorId.value = ''
            errorMessage.value = null
        }
    }
)

function submit() {
    if (!canSubmit.value) return

    submitting.value = true
    errorMessage.value = null

    router.post(
        route('admin.submissions.transfer-ownership', props.submissionId),
        { successor_id: Number(successorId.value) },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Kepemilikan dipindahkan',
                    description: 'Pemilik lama tetap terdaftar sebagai anggota penelitian.',
                })
                emit('update:open', false)
            },
            onError: (errors) => {
                errorMessage.value = errors.successor_id ?? 'Pemindahan gagal diproses.'
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
                <DialogTitle>Alihkan Kepemilikan</DialogTitle>
                <DialogDescription>
                    Ketua saat ini: <strong>{{ currentOwnerName }}</strong>
                </DialogDescription>
            </DialogHeader>

            <Alert v-if="!hasCandidates">
                <Info class="h-4 w-4" />
                <AlertDescription>
                    Pengajuan ini belum punya co-investigator aktif, jadi belum ada calon penerima.
                    Tambahkan co-investigator lebih dulu sebelum memindahkan kepemilikan.
                </AlertDescription>
            </Alert>

            <template v-else>
                <div class="space-y-2">
                    <Label for="successor">Penerima kepemilikan</Label>
                    <Select v-model="successorId">
                        <SelectTrigger id="successor">
                            <SelectValue placeholder="Pilih co-investigator aktif" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="candidate in candidates"
                                :key="candidate.id"
                                :value="String(candidate.id)"
                            >
                                {{ candidate.name }} — {{ candidate.email }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
                </div>

                <Alert>
                    <UserCog class="h-4 w-4" />
                    <AlertDescription>
                        Ketua lama tetap terdaftar sebagai anggota agar jejak siapa yang mengerjakan
                        penelitian ini tidak hilang.
                    </AlertDescription>
                </Alert>
            </template>

            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="submitting"
                    @click="emit('update:open', false)"
                >
                    {{ hasCandidates ? 'Batal' : 'Tutup' }}
                </Button>
                <Button v-if="hasCandidates" :disabled="!canSubmit" @click="submit">
                    <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                    Pindahkan
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
