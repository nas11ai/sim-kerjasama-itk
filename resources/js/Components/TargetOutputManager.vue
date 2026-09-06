<script setup lang="ts">
/**
 * BR-SM-14, BR-SM-15 — target luaran yang dijanjikan pengajuan.
 *
 * Tipe disaring dua tingkat, dan UI-nya menunjukkan itu: yang ditawarkan
 * hanya tipe yang lolos taksonomi **dan** izin skema. Menawarkan tipe yang
 * pasti ditolak backend membuat peneliti mencoba lalu gagal tanpa tahu
 * sebabnya.
 *
 * Target boleh berubah sampai pengajuan ditolak atau ditarik — termasuk
 * setelah disetujui, karena target menyesuaikan jalannya kegiatan.
 */
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
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
import { Info, Loader2, Plus, Target } from 'lucide-vue-next'
import { useToast } from '@/Components/ui/toast/use-toast'

interface OutputType {
    code: string
    name: string
}

interface TargetOutput {
    id: number
    output_type: string
}

interface Props {
    submissionId: number
    targetOutputs: TargetOutput[]
    /** Sudah disaring backend: taksonomi ∩ izin skema. */
    allowedTypes: OutputType[]
    canManage: boolean
}

const props = defineProps<Props>()

const { toast } = useToast()

const selectedType = ref<string>('')
const submitting = ref(false)
const errorMessage = ref<string | null>(null)

const canSubmit = computed(() => selectedType.value !== '' && !submitting.value)

const labelFor = (code: string) => props.allowedTypes.find((t) => t.code === code)?.name ?? code

function addTarget() {
    if (!canSubmit.value) return

    submitting.value = true
    errorMessage.value = null

    router.post(
        route('user.submissions.target-outputs.store', props.submissionId),
        { output_type: selectedType.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Target luaran ditambahkan' })
                selectedType.value = ''
            },
            onError: (errors) => {
                errorMessage.value = errors.output_type ?? 'Target luaran gagal disimpan.'
            },
            onFinish: () => {
                submitting.value = false
            },
        }
    )
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-base">
                <Target class="h-4 w-4" />
                Target Luaran
            </CardTitle>
        </CardHeader>

        <CardContent class="space-y-4">
            <p class="text-sm text-muted-foreground">
                Luaran yang dijanjikan pengajuan ini. Realisasinya dilaporkan terpisah setelah
                pengajuan disetujui, dan tidak harus sama persis — penelitian memang bisa
                menyimpang.
            </p>

            <div v-if="targetOutputs.length" class="flex flex-wrap gap-2">
                <Badge v-for="target in targetOutputs" :key="target.id" variant="secondary">
                    {{ labelFor(target.output_type) }}
                </Badge>
            </div>
            <p v-else class="text-sm text-muted-foreground">Belum ada target luaran.</p>

            <template v-if="canManage">
                <Alert v-if="!allowedTypes.length">
                    <Info class="h-4 w-4" />
                    <AlertDescription>
                        Pilih skema lebih dulu — daftar tipe luaran yang diizinkan berasal dari
                        skema.
                    </AlertDescription>
                </Alert>

                <div v-else class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <div class="flex-1 space-y-2">
                        <Label for="target-type">Tambah target</Label>
                        <Select v-model="selectedType">
                            <SelectTrigger id="target-type">
                                <SelectValue placeholder="Pilih tipe luaran" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="type in allowedTypes"
                                    :key="type.code"
                                    :value="type.code"
                                >
                                    {{ type.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button :disabled="!canSubmit" @click="addTarget">
                        <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                        <Plus v-else class="mr-2 h-4 w-4" />
                        Tambah
                    </Button>
                </div>

                <p v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
            </template>
        </CardContent>
    </Card>
</template>
