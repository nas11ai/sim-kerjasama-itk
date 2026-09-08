<script setup lang="ts">
/**
 * BR-RO-01 s/d BR-RO-09 — luaran yang benar-benar terwujud.
 *
 * Bedakan dari Target Luaran (BR-SM-14/15) yang merupakan luaran yang
 * **dijanjikan**. Kecocokan keduanya sengaja tidak ditegakkan: penelitian
 * memang menyimpang, dan memaksanya cocok mendorong peneliti berbohong di
 * target atau menyembunyikan luaran nyata.
 *
 * Daftar tipenya sudah disaring backend — taksonomi aktif ∩ jenis pengajuan —
 * jadi UI tidak mengulang aturannya. Yang ditampilkan di sini adalah
 * konsekuensinya: field wajib per tipe dan keharusan melampirkan berkas,
 * supaya peneliti tahu sebelum mencoba, bukan sesudah ditolak.
 */
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Alert, AlertDescription } from '@/Components/ui/alert'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import { Award, Info, Loader2, Paperclip, Plus } from 'lucide-vue-next'
import { useToast } from '@/Components/ui/toast/use-toast'

interface OutputType {
    code: string
    name: string
    requires_files: boolean
    required_fields: string[]
}

interface Output {
    id: number
    output_type: string
    metadata: Record<string, unknown>
    file_count: number
}

interface Props {
    submissionId: number
    outputs: Output[]
    /** Sudah disaring backend: taksonomi aktif ∩ jenis pengajuan. */
    outputTypes: OutputType[]
    canManage: boolean
}

const props = defineProps<Props>()

const { toast } = useToast()

const selectedType = ref<string>('')
const metadata = ref<Record<string, string>>({})
const filePath = ref('')
const submitting = ref(false)
const errorMessage = ref<string | null>(null)

const definition = computed(() => props.outputTypes.find((t) => t.code === selectedType.value))

const labelFor = (code: string) => props.outputTypes.find((t) => t.code === code)?.name ?? code

// Ganti tipe berarti ganti field wajib — isian lama tidak lagi berlaku.
watch(selectedType, () => {
    metadata.value = {}
    errorMessage.value = null
})

const canSubmit = computed(() => {
    if (selectedType.value === '' || submitting.value) return false

    const def = definition.value
    if (def === undefined) return false

    const semuaWajibTerisi = def.required_fields.every(
        (key) => (metadata.value[key] ?? '').trim() !== ''
    )
    const berkasCukup = !def.requires_files || filePath.value.trim() !== ''

    return semuaWajibTerisi && berkasCukup
})

function save() {
    if (!canSubmit.value) return

    submitting.value = true
    errorMessage.value = null

    router.post(
        route('user.submissions.outputs.store', props.submissionId),
        {
            output_type: selectedType.value,
            metadata: metadata.value,
            files: filePath.value.trim() === '' ? [] : [{ file_path: filePath.value.trim() }],
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Luaran tersimpan' })
                selectedType.value = ''
                metadata.value = {}
                filePath.value = ''
            },
            onError: (errors) => {
                errorMessage.value = Object.values(errors)[0] ?? 'Luaran gagal disimpan.'
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
                <Award class="h-4 w-4" />
                Luaran Terealisasi
            </CardTitle>
        </CardHeader>

        <CardContent class="space-y-4">
            <p class="text-sm text-muted-foreground">
                Luaran yang benar-benar terwujud dari kegiatan ini. Tidak harus sama persis dengan
                target yang dijanjikan — penelitian memang bisa menyimpang, dan yang dilaporkan di
                sini adalah kenyataannya.
            </p>

            <div v-if="outputs.length" class="space-y-2">
                <div
                    v-for="output in outputs"
                    :key="output.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2"
                >
                    <span class="flex items-center gap-2 text-sm">
                        <Badge variant="outline">{{ labelFor(output.output_type) }}</Badge>
                        {{ output.metadata.title ?? '—' }}
                    </span>
                    <span
                        v-if="output.file_count"
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        <Paperclip class="h-3 w-3" />
                        {{ output.file_count }} berkas
                    </span>
                </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">Belum ada luaran yang dilaporkan.</p>

            <template v-if="canManage">
                <Alert v-if="!outputTypes.length">
                    <Info class="h-4 w-4" />
                    <AlertDescription>
                        Belum ada tipe luaran yang terdaftar untuk jenis pengajuan ini. Minta Admin
                        mendaftarkannya lebih dulu.
                    </AlertDescription>
                </Alert>

                <div v-else class="space-y-3">
                    <div class="space-y-2">
                        <Label for="output-type">Tipe luaran</Label>
                        <Select v-model="selectedType">
                            <SelectTrigger id="output-type">
                                <SelectValue placeholder="Pilih tipe luaran" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="type in outputTypes"
                                    :key="type.code"
                                    :value="type.code"
                                >
                                    {{ type.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <template v-if="definition">
                        <div v-for="key in definition.required_fields" :key="key" class="space-y-2">
                            <Label :for="`meta-${key}`">
                                {{ key }} <span class="text-destructive">*</span>
                            </Label>
                            <Input :id="`meta-${key}`" v-model="metadata[key]" />
                        </div>

                        <div v-if="definition.requires_files" class="space-y-2">
                            <Label for="output-file">
                                Berkas bukti <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="output-file"
                                v-model="filePath"
                                placeholder="luaran/berkas.pdf"
                            />
                            <p class="text-xs text-muted-foreground">
                                Luaran bertipe {{ definition.name }} wajib punya minimal satu
                                berkas.
                            </p>
                        </div>
                    </template>

                    <Button :disabled="!canSubmit" @click="save">
                        <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                        <Plus v-else class="mr-2 h-4 w-4" />
                        Tambah Luaran
                    </Button>

                    <p v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
