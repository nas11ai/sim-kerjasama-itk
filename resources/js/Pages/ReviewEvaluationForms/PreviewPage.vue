<!-- resources/js/Pages/Admin/ReviewEvaluationForms/Preview.vue -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group'
import { Checkbox } from '@/Components/ui/checkbox'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Separator } from '@/Components/ui/separator'
import { ArrowLeft, FileText, Eye, Edit, Star, AlertTriangle } from 'lucide-vue-next'
import { computed } from 'vue'

interface FieldType {
    id: number
    name: string
}

interface FieldOption {
    id: number
    label: string
    value?: string
}

interface ReviewFormField {
    id: number
    label: string
    description?: string
    is_required: boolean
    order: number
    field_type: FieldType
    review_form_field_options: FieldOption[]
}

interface ReviewEvaluationForm {
    id: number
    title: string
    description?: string
    is_required: boolean
    is_active: boolean
    order: number
    review_form_fields: ReviewFormField[]
}

interface Props {
    evaluationForm: ReviewEvaluationForm
}

const props = defineProps<Props>()

const getInputType = (fieldTypeName: string): string => {
    switch (fieldTypeName.toLowerCase()) {
        case 'email':
            return 'email'
        case 'url':
            return 'url'
        case 'number':
            return 'number'
        case 'date':
            return 'date'
        default:
            return 'text'
    }
}

const getFieldComponent = (field: ReviewFormField) => {
    const fieldType = field.field_type.name.toLowerCase()

    switch (fieldType) {
        case 'text':
        case 'email':
        case 'url':
        case 'number':
            return 'input'
        case 'textarea':
            return 'textarea'
        case 'select':
            return 'select'
        case 'radio':
            return 'radio'
        case 'checkbox':
            return 'checkbox'
        case 'date':
            return 'date'
        default:
            return 'input'
    }
}

const requiredFieldsCount = computed(() => {
    return props.evaluationForm.review_form_fields.filter((field) => field.is_required).length
})

const totalFieldsCount = computed(() => {
    return props.evaluationForm.review_form_fields.length
})
</script>

<template>
    <Head :title="`Pratinjau: ${evaluationForm.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="sm"
                    @click="$inertia.visit(route('admin.review-evaluation-forms.index'))"
                >
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Kembali
                </Button>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Pratinjau: {{ evaluationForm.title }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Ini adalah tampilan formulir evaluasi bagi reviewer.
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Preview Header -->
            <Card class="border-blue-200 bg-blue-50">
                <CardContent class="p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="shrink-0">
                                <Eye class="h-8 w-8 text-blue-600" />
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-blue-900">Mode Pratinjau</h3>
                                <p class="text-sm text-blue-700">
                                    Anda sedang melihat bagaimana formulir ini muncul bagi reviewer.
                                    isian dinonaktifkan untuk pratinjau.
                                </p>
                            </div>
                        </div>

                        <Button
                            variant="outline"
                            @click="
                                $inertia.visit(
                                    route('admin.review-evaluation-forms.edit', evaluationForm.id)
                                )
                            "
                        >
                            <Edit class="h-4 w-4 mr-2" />
                            Edit Formulir
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Form Information -->
            <Card>
                <CardContent class="p-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold">
                                    {{ evaluationForm.title }}
                                </h3>
                                <p
                                    v-if="evaluationForm.description"
                                    class="text-muted-foreground mt-1"
                                >
                                    {{ evaluationForm.description }}
                                </p>
                            </div>

                            <div class="flex items-center space-x-4">
                                <Badge
                                    :variant="evaluationForm.is_required ? 'default' : 'outline'"
                                >
                                    {{ evaluationForm.is_required ? 'Wajib' : 'Opsional' }}
                                </Badge>
                                <Badge
                                    :variant="evaluationForm.is_active ? 'default' : 'secondary'"
                                >
                                    {{ evaluationForm.is_active ? 'Aktif' : 'Tidak Aktif' }}
                                </Badge>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="text-center p-4 bg-muted rounded-lg">
                                <div class="text-2xl font-bold">
                                    {{ totalFieldsCount }}
                                </div>
                                <div class="text-sm text-muted-foreground">Total Isian</div>
                            </div>

                            <div class="text-center p-4 bg-orange-100 rounded-lg">
                                <div class="text-2xl font-bold text-orange-700">
                                    {{ requiredFieldsCount }}
                                </div>
                                <div class="text-sm text-orange-600">Isian Wajib</div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Instructions -->
            <Card v-if="evaluationForm.description">
                <CardContent class="p-6">
                    <div class="flex items-start space-x-3">
                        <FileText class="h-5 w-5 text-blue-500 mt-0.5" />
                        <div>
                            <h3 class="font-medium mb-2">Instruksi</h3>
                            <p class="text-muted-foreground">
                                {{ evaluationForm.description }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Form Fields Preview -->
            <div class="space-y-4">
                <div class="flex items-center space-x-2">
                    <h3 class="text-lg font-medium">Isian Formulir</h3>
                    <Badge variant="outline">
                        {{ evaluationForm.review_form_fields.length }} isian
                    </Badge>
                </div>

                <div
                    v-if="evaluationForm.review_form_fields.length === 0"
                    class="text-center py-12"
                >
                    <FileText class="h-16 w-16 mx-auto text-gray-400 mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 mb-2">
                        Belum ada isian ditambahkan
                    </h3>
                    <p class="text-gray-500 mb-4">
                        Formulir ini belum memiliki isian. Tambahkan beberapa isian agar formulir
                        berfungsi.
                    </p>
                    <Button
                        @click="
                            $inertia.visit(
                                route('admin.review-evaluation-forms.edit', evaluationForm.id)
                            )
                        "
                    >
                        <Edit class="h-4 w-4 mr-2" />
                        Tambah Isian
                    </Button>
                </div>

                <div v-else class="space-y-4">
                    <div v-for="field in evaluationForm.review_form_fields" :key="field.id">
                        <Card>
                            <CardContent class="p-6">
                                <div class="space-y-4">
                                    <!-- Field Header -->
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <Label class="text-base font-medium">
                                                {{ field.label }}
                                                <span
                                                    v-if="field.is_required"
                                                    class="text-red-500 ml-1"
                                                    >*</span
                                                >
                                            </Label>
                                            <p
                                                v-if="field.description"
                                                class="text-sm text-muted-foreground mt-1"
                                            >
                                                {{ field.description }}
                                            </p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <Badge
                                                :variant="
                                                    field.is_required ? 'default' : 'secondary'
                                                "
                                            >
                                                {{ field.is_required ? 'Wajib' : 'Opsional' }}
                                            </Badge>
                                            <Badge variant="outline">
                                                {{ field.field_type.name }}
                                            </Badge>
                                        </div>
                                    </div>

                                    <!-- Field Preview -->
                                    <div class="space-y-2">
                                        <!-- Text Input -->
                                        <div v-if="getFieldComponent(field) === 'input'">
                                            <Input
                                                :type="getInputType(field.field_type.name)"
                                                :placeholder="`Sample ${field.label.toLowerCase()}`"
                                                disabled
                                                class="w-full"
                                            />
                                        </div>

                                        <!-- Textarea -->
                                        <div v-else-if="getFieldComponent(field) === 'textarea'">
                                            <Textarea
                                                :placeholder="`Sample ${field.label.toLowerCase()} response...`"
                                                disabled
                                                rows="3"
                                                class="w-full"
                                            />
                                        </div>

                                        <!-- Select -->
                                        <div v-else-if="getFieldComponent(field) === 'select'">
                                            <Select disabled>
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Pilih opsi" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="option in field.review_form_field_options"
                                                        :key="option.id"
                                                        :value="option.value || option.label"
                                                    >
                                                        {{ option.label }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <div class="mt-2 text-xs text-muted-foreground">
                                                {{ field.review_form_field_options.length }} opsi
                                                tersedia
                                            </div>
                                        </div>

                                        <!-- Radio Group -->
                                        <div v-else-if="getFieldComponent(field) === 'radio'">
                                            <RadioGroup disabled class="space-y-2">
                                                <div
                                                    v-for="option in field.review_form_field_options"
                                                    :key="option.id"
                                                    class="flex items-center space-x-2"
                                                >
                                                    <RadioGroupItem
                                                        :id="`preview_${field.id}_${option.id}`"
                                                        :value="option.value || option.label"
                                                        disabled
                                                    />
                                                    <Label
                                                        :for="`preview_${field.id}_${option.id}`"
                                                        class="opacity-60"
                                                    >
                                                        {{ option.label }}
                                                    </Label>
                                                </div>
                                            </RadioGroup>
                                        </div>

                                        <!-- Checkbox Group -->
                                        <div v-else-if="getFieldComponent(field) === 'checkbox'">
                                            <div class="space-y-2">
                                                <div
                                                    v-for="option in field.review_form_field_options"
                                                    :key="option.id"
                                                    class="flex items-center space-x-2"
                                                >
                                                    <Checkbox
                                                        :id="`preview_${field.id}_${option.id}`"
                                                        disabled
                                                    />
                                                    <Label
                                                        :for="`preview_${field.id}_${option.id}`"
                                                        class="opacity-60"
                                                    >
                                                        {{ option.label }}
                                                    </Label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Date Input -->
                                        <div v-else-if="getFieldComponent(field) === 'date'">
                                            <Input type="date" disabled class="w-full" />
                                        </div>

                                        <!-- Field Info -->
                                        <div class="text-xs text-muted-foreground pt-2">
                                            <div class="flex items-center space-x-4">
                                                <span>Urutan: {{ field.order }}</span>
                                                <span>Tipe: {{ field.field_type.name }}</span>
                                                <span
                                                    v-if="
                                                        field.review_form_field_options.length > 0
                                                    "
                                                >
                                                    Opsi:
                                                    {{ field.review_form_field_options.length }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Additional Notes Section Preview -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center space-x-2">
                                <FileText class="h-5 w-5" />
                                <span>Catatan Tambahan (Opsional)</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Textarea
                                placeholder="Reviewer dapat menambahkan komentar atau catatan tambahan tentang evaluasi mereka di sini..."
                                disabled
                                rows="4"
                                class="w-full"
                            />
                            <div class="text-xs text-muted-foreground mt-2">
                                Bagian ini memungkinkan reviewer untuk memberikan konteks tambahan
                                atau penjelasan atas tanggapan mereka.
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Form Actions Preview -->
            <Card>
                <CardContent class="p-4">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Tindakan formulir yang akan dilihat oleh reviewer:
                        </div>
                        <div class="flex items-center space-x-2">
                            <Button variant="outline" disabled size="sm"> Simpan Draft </Button>
                            <Button disabled size="sm"> Kirim Evaluasi </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Form Statistics -->
            <Card>
                <CardHeader>
                    <CardTitle>Statistik Formulir</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-4">
                        <div class="text-center p-4 bg-blue-50 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">
                                {{ totalFieldsCount }}
                            </div>
                            <div class="text-sm text-blue-600">Total Isian</div>
                        </div>
                        <div class="text-center p-4 bg-red-50 rounded-lg">
                            <div class="text-2xl font-bold text-red-600">
                                {{ requiredFieldsCount }}
                            </div>
                            <div class="text-sm text-red-600">Isian Wajib</div>
                        </div>
                        <div class="text-center p-4 bg-green-50 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">
                                {{ totalFieldsCount - requiredFieldsCount }}
                            </div>
                            <div class="text-sm text-green-600">Isian Opsional</div>
                        </div>
                        <div class="text-center p-4 bg-purple-50 rounded-lg">
                            <div class="text-2xl font-bold text-purple-600">
                                {{ evaluationForm.order }}
                            </div>
                            <div class="text-sm text-purple-600">Urutan Formulir</div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Warning for Missing Fields -->
            <Card v-if="totalFieldsCount === 0" class="border-amber-200 bg-amber-50">
                <CardContent class="p-6">
                    <div class="flex items-start space-x-3">
                        <AlertTriangle class="h-5 w-5 text-amber-600 mt-0.5" />
                        <div>
                            <h3 class="font-medium text-amber-800 mb-1">Formulir Tidak Lengkap</h3>
                            <p class="text-amber-700 text-sm mb-3">
                                Formulir evaluasi ini tidak memiliki isian apapun. Reviewer tidak
                                akan dapat memberikan evaluasi yang bermakna tanpa adanya isian
                                formulir.
                            </p>
                            <Button
                                size="sm"
                                class="bg-amber-600 hover:bg-amber-700"
                                @click="
                                    $inertia.visit(
                                        route(
                                            'admin.review-evaluation-forms.edit',
                                            evaluationForm.id
                                        )
                                    )
                                "
                            >
                                <Edit class="h-4 w-4 mr-2" />
                                Tambah Isian Sekarang
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Action Buttons -->
            <div
                class="flex items-center justify-between bg-white border rounded-lg p-4 sticky bottom-4"
            >
                <div class="text-sm text-muted-foreground">
                    Pratinjau selesai. Inilah cara reviewer akan berinteraksi dengan formulir
                    evaluasi Anda.
                </div>

                <div class="flex items-center space-x-2">
                    <Button
                        variant="outline"
                        @click="$inertia.visit(route('admin.review-evaluation-forms.index'))"
                    >
                        Kembali ke Daftar
                    </Button>
                    <Button
                        @click="
                            $inertia.visit(
                                route('admin.review-evaluation-forms.edit', evaluationForm.id)
                            )
                        "
                    >
                        <Edit class="h-4 w-4 mr-2" />
                        Edit Formulir
                    </Button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
