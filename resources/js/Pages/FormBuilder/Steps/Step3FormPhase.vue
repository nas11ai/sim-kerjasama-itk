<script setup lang="ts">
import { computed, watch } from 'vue'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { Switch } from '@/Components/ui/switch'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group'
import { Badge } from '@/Components/ui/badge'
import { Layers, Plus, Settings } from 'lucide-vue-next'

interface PhaseData {
    use_existing: boolean
    existing_phase_id: number | null
    new_phase_title: string
    new_phase_description: string
    phase_type_id: number | null
    needs_review: boolean
}

interface Props {
    modelValue: PhaseData
    formPhases: any[]
    phaseTypes: any[]
    errors: Record<string, string>
}

const props = defineProps<Props>()
const emit = defineEmits<{
    'update:modelValue': [value: PhaseData]
}>()

const phaseData = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
})

const selectedPhaseInfo = computed(() => {
    if (!phaseData.value.use_existing || !phaseData.value.existing_phase_id) return null
    return props.formPhases.find((p) => p.id === phaseData.value.existing_phase_id)
})

const getPhaseTypeName = (phaseTypeId: number | null) => {
    if (!phaseTypeId) return 'Not selected'
    return props.phaseTypes.find((pt) => pt.id === phaseTypeId)?.name || 'Unknown'
}

const useExistingString = computed({
    get: () => (phaseData.value.use_existing ? 'true' : 'false'),
    set: (val: string) => {
        phaseData.value.use_existing = val === 'true'
    },
})

watch(
    () => phaseData.value.use_existing,
    (useExisting) => {
        if (useExisting) {
            phaseData.value.new_phase_title = ''
            phaseData.value.new_phase_description = ''
        } else {
            phaseData.value.existing_phase_id = null
        }
    }
)
</script>

<template>
    <div class="space-y-6">
        <!-- Option Selection -->
        <Card>
            <CardHeader>
                <CardTitle>Konfigurasi Tahap Formulir</CardTitle>
                <CardDescription
                    >Pilih untuk menggunakan tahap yang sudah ada atau membuat tahap
                    baru</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-4">
                <RadioGroup v-model="useExistingString" class="space-y-3">
                    <div
                        class="flex items-center space-x-3 p-4 border rounded-lg cursor-pointer hover:bg-muted/50"
                        :class="phaseData.use_existing ? 'border-primary bg-primary/5' : ''"
                        @click="phaseData.use_existing = true"
                    >
                        <RadioGroupItem id="use_existing" value="true" />
                        <Label for="use_existing" class="flex-1 cursor-pointer">
                            <div class="flex items-center gap-2 font-medium">
                                <Layers class="h-4 w-4" />
                                Gunakan Tahap Formulir yang Ada
                            </div>
                            <p class="text-sm text-muted-foreground mt-1">
                                Tambahkan formulir ini ke tahap yang sudah ada
                            </p>
                        </Label>
                    </div>

                    <div
                        class="flex items-center space-x-3 p-4 border rounded-lg cursor-pointer hover:bg-muted/50"
                        :class="!phaseData.use_existing ? 'border-primary bg-primary/5' : ''"
                        @click="phaseData.use_existing = false"
                    >
                        <RadioGroupItem id="create_new" value="false" />
                        <Label for="create_new" class="flex-1 cursor-pointer">
                            <div class="flex items-center gap-2 font-medium">
                                <Plus class="h-4 w-4" />
                                Buat Tahap Formulir Baru
                            </div>
                            <p class="text-sm text-muted-foreground mt-1">
                                Atur tahap baru dengan pengaturan khusus
                            </p>
                        </Label>
                    </div>
                </RadioGroup>
            </CardContent>
        </Card>

        <!-- Existing Phase Selection -->
        <Card v-if="phaseData.use_existing">
            <CardHeader>
                <CardTitle>Pilih Tahap yang Ada</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label>Tahap Formulir *</Label>
                    <Select v-model="phaseData.existing_phase_id">
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih tahap formulir" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="phase in formPhases"
                                :key="phase.id"
                                :value="phase.id"
                            >
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ phase.title }}</span>
                                    <span
                                        v-if="phase.description"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ phase.description }}
                                    </span>
                                </div>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="errors['phase.existing_phase_id']" class="text-sm text-destructive">
                        {{ errors['phase.existing_phase_id'] }}
                    </p>
                </div>

                <!-- Selected Phase Info -->
                <Card v-if="selectedPhaseInfo" class="border-blue-200 bg-blue-50">
                    <CardContent class="p-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-blue-900"
                                    >Tahap Terpilih</span
                                >
                                <Badge variant="outline" class="text-blue-700">
                                    {{ selectedPhaseInfo.form_phase_details?.length || 0 }} detail
                                </Badge>
                            </div>
                            <h4 class="font-semibold text-blue-900">
                                {{ selectedPhaseInfo.title }}
                            </h4>
                            <p v-if="selectedPhaseInfo.description" class="text-sm text-blue-700">
                                {{ selectedPhaseInfo.description }}
                            </p>
                            <div class="flex items-center gap-2 text-sm text-blue-600">
                                <Badge
                                    :variant="selectedPhaseInfo.is_active ? 'default' : 'secondary'"
                                >
                                    {{ selectedPhaseInfo.is_active ? 'Aktif' : 'Tidak Aktif' }}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </CardContent>
        </Card>

        <!-- New Phase Creation -->
        <Card v-else>
            <CardHeader>
                <CardTitle>Buat Tahap Baru</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="new_phase_title">Judul Tahap *</Label>
                    <Input
                        id="new_phase_title"
                        v-model="phaseData.new_phase_title"
                        placeholder="Masukkan judul tahap"
                        :class="errors['phase.new_phase_title'] ? 'border-destructive' : ''"
                    />
                    <p v-if="errors['phase.new_phase_title']" class="text-sm text-destructive">
                        {{ errors['phase.new_phase_title'] }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="new_phase_description">Deskripsi Tahap</Label>
                    <Textarea
                        id="new_phase_description"
                        v-model="phaseData.new_phase_description"
                        placeholder="Masukkan deskripsi tahap (opsional)"
                        rows="3"
                    />
                </div>
            </CardContent>
        </Card>

        <!-- Phase Type Selection -->
        <Card>
            <CardHeader>
                <CardTitle>Jenis Tahap *</CardTitle>
                <CardDescription>Pilih jenis tahap untuk formulir ini</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label>Jenis Tahap</Label>
                    <Select v-model="phaseData.phase_type_id">
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih jenis tahap" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="phaseType in phaseTypes"
                                :key="phaseType.id"
                                :value="phaseType.id"
                            >
                                {{ phaseType.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="errors['phase.phase_type_id']" class="text-sm text-destructive">
                        {{ errors['phase.phase_type_id'] }}
                    </p>
                </div>

                <div class="flex items-center space-x-2 p-4 border rounded-lg">
                    <Switch id="needs_review" v-model="phaseData.needs_review" />
                    <Label for="needs_review" class="flex-1">
                        <div class="font-medium">Perlu direview</div>
                        <p class="text-sm text-muted-foreground">
                            Aktifkan jika pengajuan pada tahap ini memerlukan review oleh reviewer
                        </p>
                    </Label>
                </div>
            </CardContent>
        </Card>

        <!-- Phase Preview -->
        <Card class="border-green-200 bg-green-50">
            <CardContent class="p-4">
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <Settings class="h-5 w-5 text-green-600" />
                        <h3 class="font-medium text-green-900">Pratinjau Konfigurasi Tahap</h3>
                    </div>
                    <div class="grid gap-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-green-700">Mode:</span>
                            <Badge variant="outline" class="text-green-700">
                                {{
                                    phaseData.use_existing
                                        ? 'Menggunakan Tahap yang Ada'
                                        : 'Membuat Tahap Baru'
                                }}
                            </Badge>
                        </div>
                        <div
                            v-if="phaseData.use_existing && selectedPhaseInfo"
                            class="flex items-center justify-between"
                        >
                            <span class="text-green-700">Tahap:</span>
                            <span class="font-medium text-green-900">{{
                                selectedPhaseInfo.title
                            }}</span>
                        </div>
                        <div
                            v-if="!phaseData.use_existing && phaseData.new_phase_title"
                            class="flex items-center justify-between"
                        >
                            <span class="text-green-700">Tahap Baru:</span>
                            <span class="font-medium text-green-900">{{
                                phaseData.new_phase_title
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-green-700">Jenis Tahap:</span>
                            <span class="font-medium text-green-900">{{
                                getPhaseTypeName(phaseData.phase_type_id)
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-green-700">Perlu direview:</span>
                            <Badge :variant="phaseData.needs_review ? 'default' : 'secondary'">
                                {{ phaseData.needs_review ? 'Ya' : 'Tidak' }}
                            </Badge>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
