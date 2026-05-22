<!-- resources/js/Pages/FormBuilder/Steps/Step4ReviewSettings.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import { Switch } from '@/Components/ui/switch'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import { Badge } from '@/Components/ui/badge'
import { Alert, AlertDescription } from '@/Components/ui/alert'
import { Plus, Trash2, GripVertical, AlertCircle, ClipboardList } from 'lucide-vue-next'
import draggable from 'vuedraggable'

interface EvaluationFormField {
    field_type_id: number | null
    label: string
    description: string
    is_required: boolean
    validation_rules: Record<string, any>
    options: Array<{ label: string; value: string; temp_id: string }>
    temp_id: string
}

interface EvaluationForm {
    title: string
    description: string
    is_required: boolean
    fields: EvaluationFormField[]
    temp_id: string
}

interface Props {
    modelValue: EvaluationForm[]
    needsReview: boolean
    fieldTypes: any[]
    errors: Record<string, string>
}

const props = defineProps<Props>()
const emit = defineEmits<{
    'update:modelValue': [value: EvaluationForm[]]
    'update:needsReview': [value: boolean]
}>()

const evaluationForms = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
})

const needsReview = computed({
    get: () => props.needsReview,
    set: (value) => emit('update:needsReview', value),
})

const fieldTypesWithOptions = ['select', 'radio', 'checkbox']

const generateTempId = () => `temp_${Date.now()}_${Math.random()}`

const addEvaluationForm = () => {
    evaluationForms.value.push({
        title: '',
        description: '',
        is_required: true,
        fields: [],
        temp_id: generateTempId(),
    })
}

const removeEvaluationForm = (index: number) => {
    evaluationForms.value.splice(index, 1)
}

const addField = (formIndex: number) => {
    evaluationForms.value[formIndex].fields.push({
        field_type_id: null,
        label: '',
        description: '',
        is_required: false,
        validation_rules: {},
        options: [],
        temp_id: generateTempId(),
    })
}

const removeField = (formIndex: number, fieldIndex: number) => {
    evaluationForms.value[formIndex].fields.splice(fieldIndex, 1)
}

const addFieldOption = (formIndex: number, fieldIndex: number) => {
    evaluationForms.value[formIndex].fields[fieldIndex].options.push({
        label: '',
        value: '',
        temp_id: generateTempId(),
    })
}

const removeFieldOption = (formIndex: number, fieldIndex: number, optionIndex: number) => {
    evaluationForms.value[formIndex].fields[fieldIndex].options.splice(optionIndex, 1)
}

const getFieldTypeName = (fieldTypeId: number | null): string => {
    if (!fieldTypeId) return ''
    return props.fieldTypes.find((ft) => ft.id === fieldTypeId)?.name || ''
}

const fieldRequiresOptions = (fieldTypeId: number | null): boolean => {
    const typeName = getFieldTypeName(fieldTypeId)
    return fieldTypesWithOptions.includes(typeName)
}
</script>

<template>
    <div class="space-y-6">
        <!-- Review Toggle -->
        <Card>
            <CardContent class="p-6">
                <div class="flex items-center space-x-4">
                    <Switch id="enable_review" v-model="needsReview" />
                    <Label for="enable_review" class="flex-1">
                        <div class="font-medium text-base">Aktifkan Review & Evaluasi</div>
                        <p class="text-sm text-muted-foreground">
                            Wajibkan reviewer untuk mengisi formulir evaluasi sebelum menyetujui
                            pengajuan
                        </p>
                    </Label>
                </div>
            </CardContent>
        </Card>

        <!-- No Review Message -->
        <Alert v-if="!needsReview">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>
                Review dinonaktifkan. Pengajuan tidak akan memerlukan formulir evaluasi. Anda dapat
                melewati ke langkah berikutnya.
            </AlertDescription>
        </Alert>

        <!-- Evaluation Forms Section -->
        <template v-if="needsReview">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                Formulir Evaluasi
                                <Badge variant="secondary">
                                    {{ evaluationForms.length }}
                                </Badge>
                            </CardTitle>
                            <CardDescription>
                                Buat formulir yang harus diisi reviewer saat melakukan evaluasi
                                pengajuan
                            </CardDescription>
                        </div>
                        <Button type="button" size="sm" @click="addEvaluationForm">
                            <Plus class="h-4 w-4 mr-2" />
                            Tambah Formulir
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="evaluationForms.length === 0" class="text-center py-12">
                        <ClipboardList
                            class="h-12 w-12 mx-auto text-muted-foreground mb-4 opacity-50"
                        />
                        <h3 class="text-lg font-medium mb-2">Tidak Ada Formulir Evaluasi</h3>
                        <p class="text-muted-foreground mb-4">
                            Tambahkan setidaknya satu formulir evaluasi untuk diisi reviewer
                        </p>
                        <Button type="button" @click="addEvaluationForm">
                            <Plus class="h-4 w-4 mr-2" />
                            Tambah Formulir Pertama
                        </Button>
                    </div>

                    <div v-else class="space-y-6">
                        <draggable
                            v-model="evaluationForms"
                            item-key="temp_id"
                            handle=".drag-handle"
                            class="space-y-6"
                            :animation="200"
                        >
                            <template #item="{ element: evalForm, index: formIndex }">
                                <Card class="border-2">
                                    <CardHeader class="bg-muted/30">
                                        <div class="flex items-start gap-4">
                                            <div
                                                class="drag-handle cursor-move p-1 hover:bg-muted rounded mt-1"
                                            >
                                                <GripVertical
                                                    class="h-4 w-4 text-muted-foreground"
                                                />
                                            </div>

                                            <div class="flex-1 space-y-4">
                                                <div class="grid gap-4 md:grid-cols-2">
                                                    <div class="space-y-2">
                                                        <Label>Judul Formulir *</Label>
                                                        <Input
                                                            v-model="evalForm.title"
                                                            placeholder="Masukkan judul formulir evaluasi"
                                                        />
                                                    </div>

                                                    <div class="flex items-center space-x-2 pt-6">
                                                        <Switch
                                                            :id="`form_required_${formIndex}`"
                                                            v-model="evalForm.is_required"
                                                        />
                                                        <Label :for="`form_required_${formIndex}`">
                                                            Wajib untuk semua reviewer
                                                        </Label>
                                                    </div>
                                                </div>

                                                <div class="space-y-2">
                                                    <Label>Deskripsi</Label>
                                                    <Textarea
                                                        v-model="evalForm.description"
                                                        placeholder="Masukkan deskripsi formulir (opsional)"
                                                        rows="2"
                                                    />
                                                </div>
                                            </div>

                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                class="text-destructive"
                                                @click="removeEvaluationForm(formIndex)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </CardHeader>

                                    <CardContent class="pt-6">
                                        <!-- Evaluation Form Fields -->
                                        <div class="space-y-4">
                                            <div class="flex items-center justify-between">
                                                <Label class="text-base font-semibold"
                                                    >Isian Formulir</Label
                                                >
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    @click="addField(formIndex)"
                                                >
                                                    <Plus class="h-3 w-3 mr-2" />
                                                    Tambah Isian
                                                </Button>
                                            </div>

                                            <div
                                                v-if="evalForm.fields.length === 0"
                                                class="text-center py-6 text-muted-foreground border-2 border-dashed rounded-lg"
                                            >
                                                <p>
                                                    Belum ada isian. Klik “Tambah Isian” untuk
                                                    membuat kriteria evaluasi.
                                                </p>
                                            </div>

                                            <div v-else class="space-y-3">
                                                <Card
                                                    v-for="(field, fieldIndex) in evalForm.fields"
                                                    :key="field.temp_id"
                                                    class="border"
                                                >
                                                    <CardContent class="p-4">
                                                        <div class="flex items-start gap-3">
                                                            <div class="flex-1 space-y-3">
                                                                <div
                                                                    class="grid gap-3 md:grid-cols-2"
                                                                >
                                                                    <div class="space-y-2">
                                                                        <Label class="text-sm"
                                                                            >Tipe Isian *</Label
                                                                        >
                                                                        <Select
                                                                            v-model="
                                                                                field.field_type_id
                                                                            "
                                                                        >
                                                                            <SelectTrigger
                                                                                class="h-9"
                                                                            >
                                                                                <SelectValue
                                                                                    placeholder="Pilih tipe"
                                                                                />
                                                                            </SelectTrigger>
                                                                            <SelectContent>
                                                                                <SelectItem
                                                                                    v-for="ft in fieldTypes"
                                                                                    :key="ft.id"
                                                                                    :value="ft.id"
                                                                                >
                                                                                    {{ ft.name }}
                                                                                </SelectItem>
                                                                            </SelectContent>
                                                                        </Select>
                                                                    </div>

                                                                    <div class="space-y-2">
                                                                        <Label class="text-sm"
                                                                            >Label *</Label
                                                                        >
                                                                        <Input
                                                                            v-model="field.label"
                                                                            placeholder="Label isian"
                                                                            class="h-9"
                                                                        />
                                                                    </div>
                                                                </div>

                                                                <div class="space-y-2">
                                                                    <Label class="text-sm"
                                                                        >Deskripsi</Label
                                                                    >
                                                                    <Input
                                                                        v-model="field.description"
                                                                        placeholder="Deskripsi isian (opsional)"
                                                                        class="h-9"
                                                                    />
                                                                </div>

                                                                <div
                                                                    class="flex items-center space-x-2"
                                                                >
                                                                    <Switch
                                                                        :id="`field_required_${formIndex}_${fieldIndex}`"
                                                                        v-model="field.is_required"
                                                                    />
                                                                    <Label
                                                                        :for="`field_required_${formIndex}_${fieldIndex}`"
                                                                        class="text-sm"
                                                                    >
                                                                        Isian wajib
                                                                    </Label>
                                                                </div>

                                                                <!-- Field Options -->
                                                                <div
                                                                    v-if="
                                                                        fieldRequiresOptions(
                                                                            field.field_type_id
                                                                        )
                                                                    "
                                                                    class="space-y-2"
                                                                >
                                                                    <div
                                                                        class="flex items-center justify-between"
                                                                    >
                                                                        <Label class="text-sm"
                                                                            >Opsi</Label
                                                                        >
                                                                        <Button
                                                                            type="button"
                                                                            size="sm"
                                                                            variant="ghost"
                                                                            @click="
                                                                                addFieldOption(
                                                                                    formIndex,
                                                                                    fieldIndex as number
                                                                                )
                                                                            "
                                                                        >
                                                                            <Plus
                                                                                class="h-3 w-3 mr-1"
                                                                            />
                                                                            Tambah
                                                                        </Button>
                                                                    </div>
                                                                    <div class="space-y-2">
                                                                        <div
                                                                            v-for="(
                                                                                option, optionIndex
                                                                            ) in field.options"
                                                                            :key="option.temp_id"
                                                                            class="flex gap-2"
                                                                        >
                                                                            <Input
                                                                                v-model="
                                                                                    option.label
                                                                                "
                                                                                placeholder="Label opsi"
                                                                                class="h-8"
                                                                            />
                                                                            <Input
                                                                                v-model="
                                                                                    option.value
                                                                                "
                                                                                placeholder="Nilai (opsional)"
                                                                                class="h-8"
                                                                            />
                                                                            <Button
                                                                                type="button"
                                                                                variant="ghost"
                                                                                size="sm"
                                                                                @click="
                                                                                    removeFieldOption(
                                                                                        formIndex,
                                                                                        fieldIndex as number,
                                                                                        optionIndex as number
                                                                                    )
                                                                                "
                                                                            >
                                                                                <Trash2
                                                                                    class="h-3 w-3 text-destructive"
                                                                                />
                                                                            </Button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                class="text-destructive"
                                                                @click="
                                                                    removeField(
                                                                        formIndex,
                                                                        fieldIndex as number
                                                                    )
                                                                "
                                                            >
                                                                <Trash2 class="h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                    </CardContent>
                                                </Card>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </template>
                        </draggable>
                    </div>
                </CardContent>
            </Card>
        </template>
    </div>
</template>
