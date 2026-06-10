<script setup lang="ts">
import { computed, ref } from 'vue'
import { route } from 'ziggy-js'
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import { Plus, Trash2, ArrowLeft, Calendar, Settings, FileText } from 'lucide-vue-next'
import { watch } from 'vue'
import Checkbox from '@/Components/AppCheckbox.vue'
import { useToast } from '@/Components/ui/toast'

interface FormPhase {
    id: number
    title: string
    description?: string
}

interface SubmissionRule {
    id: number
    label: string
    value: number
}

interface SubmissionDateLabel {
    id: number
    name: string
}

interface SubmissionDate {
    label: string
    date: string
    temp_id: string
}

// Fix: Add index signature to FormData interface
interface FormData {
    name: string
    submission_dates: SubmissionDate[]
    form_phase_ids: number[]
    submission_rule_ids: number[]
}

interface Props {
    formPhases: FormPhase[]
    submissionRules: SubmissionRule[]
    submissionDateLabels: SubmissionDateLabel[]
}

const props = defineProps<Props>()

const form = useForm<FormData>({
    name: '',
    submission_dates: [],
    form_phase_ids: [],
    submission_rule_ids: [],
})
const { toast } = useToast()

const errors = computed(() => (form.errors as Record<string, string>) ?? {})

// State untuk dialog tambah label baru
const showAddLabelDialog = ref(false)
const newLabelForm = useForm({
    label: '',
})

// State untuk menyimpan labels yang ditambahkan secara dinamis
const dynamicLabels = ref<SubmissionDateLabel[]>([])

// Computed untuk menggabungkan labels dari props dan yang ditambahkan secara dinamis
const allLabels = computed(() => [...props.submissionDateLabels, ...dynamicLabels.value])

const generateTempId = () => `temp_${Date.now()}_${Math.random()}`

const addSubmissionDate = () => {
    form.submission_dates.push({
        label: '',
        date: '',
        temp_id: generateTempId(),
    })
}

const addNewLabel = async () => {
    if (newLabelForm.label.trim()) {
        try {
            const response = await fetch(route('admin.submission-date-labels.store'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') || '',
                },
                body: JSON.stringify({ name: newLabelForm.label.trim() }),
            })

            if (!response.ok) {
                const text = await response.text()
                console.error('Permintaan gagal:', text)
                return
            }

            const newLabel = await response.json()
            dynamicLabels.value.push(newLabel)
            showAddLabelDialog.value = false
        } catch (error) {
            console.error('Gagal menambahkan label baru:', error)
        }
    }
}

const removeSubmissionDate = (index: number) => {
    form.submission_dates.splice(index, 1)
}

const toggleFormPhase = (phaseId: number) => {
    const index = form.form_phase_ids.indexOf(phaseId)
    if (index > -1) {
        form.form_phase_ids.splice(index, 1)
    } else {
        form.form_phase_ids.push(phaseId)
    }
}

const toggleSubmissionRule = (ruleId: number) => {
    const index = form.submission_rule_ids.indexOf(ruleId)
    if (index > -1) {
        form.submission_rule_ids.splice(index, 1)
    } else {
        form.submission_rule_ids.push(ruleId)
    }
}

const selectAllFormPhases = () => {
    if (form.form_phase_ids.length === props.formPhases.length) {
        form.form_phase_ids = []
    } else {
        form.form_phase_ids = props.formPhases.map((phase) => phase.id)
    }
}

const selectAllSubmissionRules = () => {
    if (form.submission_rule_ids.length === props.submissionRules.length) {
        form.submission_rule_ids = []
    } else {
        form.submission_rule_ids = props.submissionRules.map((rule) => rule.id)
    }
}

// const onPhaseClick = (phaseId: number) => {
//     const index = form.form_phase_ids.indexOf(phaseId)
//     if (index > -1) {
//         form.form_phase_ids.splice(index, 1)
//     } else {
//         form.form_phase_ids.push(phaseId)
//     }
// }

const submit = () => {
    form.post(route('admin.submission-periods.store'))
}

// Initialize with one date entry
if (form.submission_dates.length === 0) {
    addSubmissionDate()
}

watch(showAddLabelDialog, (val) => {
    if (val) {
        document.body.classList.add('overflow-hidden')
    } else {
        document.body.classList.remove('overflow-hidden')
    }
})
</script>

<template>
    <Head title="Buat Periode Pengiriman" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="sm"
                    @click="$inertia.visit(route('admin.submission-periods.index'))"
                >
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Kembali
                </Button>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Buat Periode Pengiriman Baru
                </h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <form class="space-y-6" @submit.prevent="submit">
                <!-- Period Basic Info -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Calendar class="h-5 w-5" />
                            Informasi Periode
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Nama Periode *</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="Masukkan nama periode pengiriman"
                                :class="errors.name ? 'border-destructive' : ''"
                            />
                            <p v-if="errors.name" class="text-sm text-destructive">
                                {{ errors.name }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Submission Dates -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2">
                                <Calendar class="h-5 w-5" />
                                Tanggal Pengiriman *
                            </CardTitle>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="addSubmissionDate"
                            >
                                <Plus class="h-4 w-4 mr-2" />
                                Tambah Tanggal
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div
                                v-for="(date, index) in form.submission_dates"
                                :key="date.temp_id"
                                class="flex items-end gap-4 p-4 border rounded-lg"
                            >
                                <div class="flex-1 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <Label :for="`date_label_${index}`">Label Tanggal *</Label>
                                        <!-- Custom Modal instead of Dialog -->
                                        <div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                class="text-xs h-6 px-2"
                                                @click="showAddLabelDialog = true"
                                            >
                                                <Plus class="h-3 w-3 mr-1" />
                                                Tambah Baru
                                            </Button>

                                            <!-- Modal Overlay -->
                                            <div
                                                v-if="showAddLabelDialog"
                                                class="fixed inset-0 z-50"
                                            >
                                                <!-- Background hitam -->
                                                <div
                                                    class="absolute inset-0 bg-black/80"
                                                    @click="showAddLabelDialog = false"
                                                />

                                                <!-- Modal content -->
                                                <div
                                                    class="relative z-10 flex items-center justify-center min-h-screen p-4"
                                                >
                                                    <div
                                                        class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 max-h-[calc(100vh-2rem)] overflow-y-auto"
                                                    >
                                                        <h3 class="text-lg font-semibold mb-4">
                                                            Tambah Label Tanggal Baru
                                                        </h3>
                                                        <div class="space-y-4">
                                                            <div class="space-y-2">
                                                                <Label for="new-label"
                                                                    >Nama Label
                                                                </Label>
                                                                <Input
                                                                    id="new-label"
                                                                    v-model="newLabelForm.label"
                                                                    placeholder="Enter label name"
                                                                    autofocus
                                                                    @keyup.enter="addNewLabel"
                                                                />
                                                            </div>
                                                            <div
                                                                class="flex justify-end gap-2 pt-4"
                                                            >
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    @click="
                                                                        showAddLabelDialog = false
                                                                    "
                                                                >
                                                                    Batal
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    :disabled="
                                                                        !newLabelForm.label.trim()
                                                                    "
                                                                    @click="addNewLabel"
                                                                >
                                                                    Tambah Label
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <Select :id="`date_label_${index}`" v-model="date.label">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select date label" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="labelOption in allLabels"
                                                :key="labelOption.id"
                                                :value="labelOption.name"
                                            >
                                                {{ labelOption.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>

                                    <p
                                        v-if="errors[`submission_dates.${index}.label`]"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors[`submission_dates.${index}.label`] }}
                                    </p>
                                </div>
                                <div class="flex-1 space-y-2">
                                    <Label :for="`date_${index}`">Tanggal *</Label>
                                    <Input
                                        :id="`date_${index}`"
                                        v-model="date.date"
                                        type="datetime-local"
                                    />

                                    <p
                                        v-if="errors[`submission_dates.${index}.date`]"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors[`submission_dates.${index}.date`] }}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:text-destructive"
                                    :disabled="form.submission_dates.length === 1"
                                    @click="removeSubmissionDate(index)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                        <p v-if="errors.submission_dates" class="text-sm text-destructive mt-2">
                            {{ errors.submission_dates }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Form Phases -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2">
                                <Settings class="h-5 w-5" />
                                Tahap Formulir *
                                <Badge variant="secondary" class="ml-2">
                                    {{ form.form_phase_ids.length }} dipilih
                                </Badge>
                            </CardTitle>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="selectAllFormPhases"
                            >
                                {{
                                    form.form_phase_ids.length === props.formPhases.length
                                        ? 'Batal Pilih Semua'
                                        : 'Pilih Semua'
                                }}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="props.formPhases.length === 0"
                            class="text-center py-8 text-muted-foreground"
                        >
                            <Settings class="h-12 w-12 mx-auto mb-4 opacity-50" />
                            <p>Tidak ada tahap formulir aktif tersedia.</p>
                        </div>
                        <div v-else class="grid gap-3 md:grid-cols-2">
                            <div
                                v-for="phase in props.formPhases"
                                :key="phase.id"
                                class="flex w-full items-center space-x-3 p-3 border rounded-lg hover:bg-muted/50 cursor-pointer"
                                @click="toggleFormPhase(phase.id)"
                            >
                                <Checkbox :checked="form.form_phase_ids.includes(phase.id)" />
                                <div class="flex-1 min-w-0">
                                    <Label class="cursor-pointer font-medium">
                                        {{ phase.title }}
                                    </Label>
                                    <p
                                        v-if="phase.description"
                                        class="text-sm text-muted-foreground mt-1"
                                    >
                                        {{ phase.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <p v-if="errors.form_phase_ids" class="text-sm text-destructive mt-2">
                            {{ errors.form_phase_ids }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Submission Rules (Optional) -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2">
                                <FileText class="h-5 w-5" />
                                Aturan Pengiriman (Opsional)
                                <Badge variant="outline" class="ml-2">
                                    {{ form.submission_rule_ids.length }}
                                    dipilih
                                </Badge>
                            </CardTitle>
                            <Button
                                v-if="props.submissionRules.length > 0"
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="selectAllSubmissionRules"
                            >
                                {{
                                    form.submission_rule_ids.length === props.submissionRules.length
                                        ? 'Batal Pilih Semua'
                                        : 'Pilih Semua'
                                }}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="props.submissionRules.length === 0"
                            class="text-center py-8 text-muted-foreground"
                        >
                            <FileText class="h-12 w-12 mx-auto mb-4 opacity-50" />
                            <p>Tidak ada aturan pengiriman tersedia.</p>
                        </div>
                        <div v-else class="grid gap-3 md:grid-cols-2">
                            <div
                                v-for="rule in props.submissionRules"
                                :key="rule.id"
                                class="flex w-full items-center space-x-3 p-3 border rounded-lg hover:bg-muted/50 cursor-pointer"
                                @click="toggleSubmissionRule(rule.id)"
                            >
                                <Checkbox :checked="form.submission_rule_ids.includes(rule.id)" />
                                <div class="flex-1">
                                    <Label class="cursor-pointer font-medium">
                                        {{ rule.label }}
                                    </Label>
                                    <p class="text-sm text-muted-foreground">
                                        Nilai: {{ rule.value }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Preview Summary -->
                <Card
                    v-if="form.name || form.submission_dates.some((d) => d.label || d.date)"
                    class="border-blue-200 bg-blue-50"
                >
                    <CardHeader>
                        <CardTitle class="text-blue-900"> Pratinjau Ringkasan </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="form.name">
                            <h4 class="font-medium text-blue-800 mb-1">Nama Periode</h4>
                            <p class="text-blue-700">
                                {{ form.name }}
                            </p>
                        </div>

                        <div v-if="form.submission_dates.some((d) => d.label || d.date)">
                            <h4 class="font-medium text-blue-800 mb-2">Tanggal</h4>
                            <div class="space-y-1">
                                <template
                                    v-for="(submissionDate, index) in form.submission_dates"
                                    :key="index"
                                >
                                    <div
                                        v-if="submissionDate.label || submissionDate.date"
                                        class="text-sm text-blue-600"
                                    >
                                        <strong
                                            >{{
                                                submissionDate.label || 'Tanggal Tidak Bernama'
                                            }}:</strong
                                        >
                                        {{
                                            submissionDate.date
                                                ? new Date(submissionDate.date).toLocaleString()
                                                : 'Tanggal Tidak Ditetapkan'
                                        }}
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div v-if="form.form_phase_ids.length > 0">
                            <h4 class="font-medium text-blue-800 mb-2">Tahap Formulir Dipilih</h4>
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="phaseId in form.form_phase_ids"
                                    :key="phaseId"
                                    variant="outline"
                                    class="text-blue-700 border-blue-300"
                                >
                                    {{ props.formPhases.find((p) => p.id === phaseId)?.title }}
                                </Badge>
                            </div>
                        </div>

                        <div v-if="form.submission_rule_ids.length > 0">
                            <h4 class="font-medium text-blue-800 mb-2">
                                Aturan Pengiriman Dipilih
                            </h4>
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="ruleId in form.submission_rule_ids"
                                    :key="ruleId"
                                    variant="outline"
                                    class="text-blue-700 border-blue-300"
                                >
                                    {{ props.submissionRules.find((r) => r.id === ruleId)?.label }}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="$inertia.visit(route('admin.submission-periods.index'))"
                    >
                        Batal
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Membuat...' : 'Buat Periode Pengiriman' }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
