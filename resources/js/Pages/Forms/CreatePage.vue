<!-- resources/js/Pages/Forms/Create.vue -->
<script setup lang="ts">
import { route } from 'ziggy-js'
import { computed } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { Switch } from '@/Components/ui/switch'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import { Badge } from '@/Components/ui/badge'
import { Plus, Trash2, GripVertical, ArrowLeft } from 'lucide-vue-next'
import draggable from 'vuedraggable'

interface FormType {
    id: number
    name: string
}

interface FieldType {
    id: number
    name: string
}

interface FieldOption {
    label: string
    temp_id?: string
}

interface FormField {
    field_type_id: number | null
    label: string
    is_required: boolean
    options: FieldOption[]
    temp_id: string
}

interface FormData {
    title: string
    description: string
    form_type_id: number | null
    is_active: boolean
    fields: FormField[]
}

interface Props {
    formTypes: FormType[]
    fieldTypes: FieldType[]
}

const props = defineProps<Props>()

// Type the form properly with the expected structure
const form = useForm<FormData>({
    title: '',
    description: '',
    form_type_id: null,
    is_active: true,
    fields: [],
})

interface FieldError {
    label?: string
    field_type_id?: string
    options?: string[]
}

type ErrorsType = Partial<{
    title: string
    description: string
    form_type_id: string
    fields: FieldError[]
}>

const errors = computed<ErrorsType>(() => (form.errors ?? {}) as ErrorsType)

const fieldTypesWithOptions = ['select', 'radio', 'checkbox']

const generateTempId = () => `temp_${Date.now()}_${Math.random()}`

const addField = () => {
    form.fields.push({
        field_type_id: null,
        label: '',
        is_required: false,
        options: [],
        temp_id: generateTempId(),
    })
}

const removeField = (index: number) => {
    form.fields.splice(index, 1)
}

const addOption = (fieldIndex: number) => {
    form.fields[fieldIndex].options.push({
        label: '',
        temp_id: generateTempId(),
    })
}

const removeOption = (fieldIndex: number, optionIndex: number) => {
    form.fields[fieldIndex].options.splice(optionIndex, 1)
}

const getFieldTypeName = (fieldTypeId: number | null): string => {
    if (!fieldTypeId) return ''
    const fieldType = props.fieldTypes.find((ft) => ft.id === fieldTypeId)
    return fieldType?.name || ''
}

const fieldTypeRequiresOptions = (fieldTypeId: number | null): boolean => {
    if (!fieldTypeId) return false
    const fieldTypeName = getFieldTypeName(fieldTypeId)
    return fieldTypesWithOptions.includes(fieldTypeName)
}

const submit = () => {
    form.post(route('admin.forms.store'))
}
</script>

<template>
    <Head title="Buat Formulir" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.forms.index')">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft class="h-4 w-4 mr-2" />
                            Kembali
                        </Button>
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Buat Formulir Baru
                    </h2>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <form class="space-y-6" @submit.prevent="submit">
                <!-- Form Basic Info -->
                <Card>
                    <CardHeader>
                        <CardTitle>Informasi Formulir</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid gap-6 md:grid-cols-2">
                            <!-- Form Title -->
                            <div class="space-y-1.5">
                                <Label for="title">Judul Formulir *</Label>
                                <Input
                                    id="title"
                                    v-model="form.title"
                                    placeholder="Masukkan judul formulir"
                                    :aria-invalid="!!errors.title"
                                    :aria-describedby="errors.title ? 'error-title' : undefined"
                                    :class="
                                        errors.title
                                            ? 'border-destructive focus-visible:ring-destructive'
                                            : ''
                                    "
                                />
                                <p
                                    v-if="errors.title"
                                    id="error-title"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.title }}
                                </p>
                            </div>

                            <!-- Form Type -->
                            <div class="space-y-1.5">
                                <Label for="form_type">Tipe Formulir *</Label>
                                <Select v-model="form.form_type_id">
                                    <SelectTrigger
                                        id="form_type"
                                        :aria-invalid="!!errors.form_type_id"
                                        :aria-describedby="
                                            errors.form_type_id ? 'error-form-type' : undefined
                                        "
                                        :class="
                                            errors.form_type_id
                                                ? 'border-destructive focus-visible:ring-destructive'
                                                : ''
                                        "
                                    >
                                        <SelectValue placeholder="Pilih tipe formulir" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="formType in props.formTypes"
                                            :key="formType.id"
                                            :value="formType.id"
                                        >
                                            {{ formType.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p
                                    v-if="errors.form_type_id"
                                    id="error-form-type"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.form_type_id }}
                                </p>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="space-y-1.5">
                            <Label for="description">Deskripsi</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                placeholder="Masukkan deskripsi formulir (opsional)"
                                rows="3"
                            />
                        </div>

                        <!-- Active switch -->
                        <div class="flex items-center space-x-2">
                            <Switch id="is_active" v-model="form.is_active" />
                            <Label for="is_active">Aktif</Label>
                        </div>
                    </CardContent>
                </Card>

                <!-- Form Fields -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle>Isian Formulir</CardTitle>
                            <!-- Tombol Add Field hanya ditampilkan ketika tidak ada fields -->
                            <Button
                                v-if="form.fields.length === 0"
                                type="button"
                                size="sm"
                                @click="addField"
                            >
                                <Plus class="h-4 w-4 mr-2" />
                                Tambah Isian
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="form.fields.length === 0"
                            class="text-center py-8 text-muted-foreground"
                        >
                            <p>
                                Belum ada isian yang ditambahkan. Klik "Tambah Isian" untuk memulai.
                            </p>
                        </div>

                        <div v-else class="space-y-4">
                            <draggable
                                v-model="form.fields"
                                item-key="temp_id"
                                handle=".drag-handle"
                                class="space-y-4"
                                :animation="200"
                            >
                                <template #item="{ element: field, index }">
                                    <Card class="border-2 border-dashed">
                                        <CardContent class="pt-6">
                                            <div class="flex items-start gap-4">
                                                <div
                                                    class="drag-handle cursor-move p-1 hover:bg-muted rounded"
                                                >
                                                    <GripVertical
                                                        class="h-4 w-4 text-muted-foreground"
                                                    />
                                                </div>

                                                <div class="flex-1 space-y-4">
                                                    <div class="grid gap-4 md:grid-cols-2">
                                                        <div class="space-y-2">
                                                            <Label>Tipe Isian *</Label>
                                                            <Select v-model="field.field_type_id">
                                                                <SelectTrigger>
                                                                    <SelectValue
                                                                        placeholder="Pilih tipe isian"
                                                                    />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem
                                                                        v-for="fieldType in props.fieldTypes"
                                                                        :key="fieldType.id"
                                                                        :value="fieldType.id"
                                                                    >
                                                                        {{ fieldType.name }}
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>

                                                        <div class="space-y-2">
                                                            <Label>Label Isian *</Label>
                                                            <Input
                                                                v-model="field.label"
                                                                placeholder="Masukkan label isian"
                                                            />
                                                        </div>
                                                    </div>

                                                    <div class="flex items-center space-x-2">
                                                        <Switch
                                                            :id="`required_${index}`"
                                                            v-model="field.is_required"
                                                        />
                                                        <Label :for="`required_${index}`"
                                                            >Isian Wajib
                                                        </Label>
                                                    </div>

                                                    <!-- Field Options -->
                                                    <div
                                                        v-if="
                                                            fieldTypeRequiresOptions(
                                                                field.field_type_id
                                                            )
                                                        "
                                                        class="space-y-3"
                                                    >
                                                        <div
                                                            class="flex items-center justify-between"
                                                        >
                                                            <Label class="text-sm font-medium"
                                                                >Opsi</Label
                                                            >
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                @click="addOption(index)"
                                                            >
                                                                <Plus class="h-3 w-3 mr-1" />
                                                                Tambah Opsi
                                                            </Button>
                                                        </div>

                                                        <div
                                                            v-if="field.options.length === 0"
                                                            class="text-sm text-muted-foreground"
                                                        >
                                                            Belum ada opsi yang ditambahkan.
                                                        </div>

                                                        <div v-else class="space-y-2">
                                                            <div
                                                                v-for="(
                                                                    option, optionIndex
                                                                ) in field.options"
                                                                :key="option.temp_id || optionIndex"
                                                                class="flex items-center gap-2"
                                                            >
                                                                <Input
                                                                    v-model="option.label"
                                                                    placeholder="Label opsi"
                                                                    class="flex-1"
                                                                />
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    @click="
                                                                        removeOption(
                                                                            index,
                                                                            optionIndex as number
                                                                        )
                                                                    "
                                                                >
                                                                    <Trash2
                                                                        class="h-4 w-4 text-destructive"
                                                                    />
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Field Preview -->
                                                    <div
                                                        v-if="field.field_type_id && field.label"
                                                        class="mt-4 p-3 bg-muted rounded-lg"
                                                    >
                                                        <Label
                                                            class="text-sm text-muted-foreground mb-2 block"
                                                            >Pratinjau:</Label
                                                        >
                                                        <div class="space-y-2">
                                                            <Label>
                                                                {{ field.label }}
                                                                <Badge
                                                                    v-if="field.is_required"
                                                                    variant="destructive"
                                                                    class="ml-2 text-xs"
                                                                >
                                                                    Wajib
                                                                </Badge>
                                                            </Label>

                                                            <!-- Preview based on field type -->
                                                            <div
                                                                v-if="
                                                                    getFieldTypeName(
                                                                        field.field_type_id
                                                                    ) === 'textarea'
                                                                "
                                                            >
                                                                <Textarea
                                                                    placeholder="Ini adalah pratinjau"
                                                                    disabled
                                                                />
                                                            </div>
                                                            <div
                                                                v-else-if="
                                                                    fieldTypeRequiresOptions(
                                                                        field.field_type_id
                                                                    ) && field.options.length > 0
                                                                "
                                                            >
                                                                <div class="space-y-1">
                                                                    <div
                                                                        v-for="option in field.options"
                                                                        :key="option.temp_id"
                                                                        class="flex items-center space-x-2"
                                                                    >
                                                                        <input
                                                                            :type="
                                                                                getFieldTypeName(
                                                                                    field.field_type_id
                                                                                ) === 'checkbox'
                                                                                    ? 'checkbox'
                                                                                    : 'radio'
                                                                            "
                                                                            disabled
                                                                            class="h-4 w-4"
                                                                        />
                                                                        <span class="text-sm">{{
                                                                            option.label
                                                                        }}</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div v-else>
                                                                <Input
                                                                    :type="
                                                                        getFieldTypeName(
                                                                            field.field_type_id
                                                                        )
                                                                    "
                                                                    placeholder="Ini adalah pratinjau"
                                                                    disabled
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="text-destructive hover:text-destructive"
                                                    @click="removeField(index)"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </template>
                            </draggable>

                            <!-- Tombol Add Field di bawah ketika sudah ada fields -->
                            <div class="flex justify-center pt-4">
                                <Button
                                    type="button"
                                    size="sm"
                                    class="w-full max-w-xs"
                                    @click="addField"
                                >
                                    <Plus class="h-4 w-4 mr-2" />
                                    Tambah Field Lain
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="$inertia.visit(route('admin.forms.index'))"
                    >
                        Batal
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Membuat...' : 'Buat Formulir' }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
