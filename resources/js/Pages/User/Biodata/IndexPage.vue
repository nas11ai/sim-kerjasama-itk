<!-- resources\js\Pages\User\Biodata\IndexPage.vue -->
<script setup lang="ts">
import { route } from 'ziggy-js'
import { ref, computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import {
    Card,
    CardHeader,
    CardContent,
    CardTitle,
    CardDescription,
    CardFooter,
} from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { Checkbox } from '@/Components/ui/checkbox'
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import { Badge } from '@/Components/ui/badge'
import { Progress } from '@/Components/ui/progress'
import Alert from '@/Components/AppAlert.vue'
import {
    Send,
    Upload,
    AlertCircle,
    CheckCircle2,
    FileText,
    User,
    Mail,
    Phone,
    Calendar,
    GraduationCap,
    Shield,
    Info,
    ArrowLeft,
    type LucideIcon,
} from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

interface FormFieldOption {
    id: number
    label: string
    value: string
}

interface FormField {
    id: number
    label: string
    is_required: boolean
    order: number
    helper_text?: string
    field_type: {
        id: number
        name: string
    }
    form_field_options: FormFieldOption[]
}

interface Form {
    id: number
    title: string
    description?: string
    form_fields: FormField[]
}

interface ExistingSubmission {
    id: number
    is_submitted: boolean
    status: string
    created_at: string
    updated_at: string
}

interface BiodataStatus {
    required: boolean
    completed: boolean
    showAllMenus: boolean
    status?: string
    message?: string
}

interface Props {
    form: Form
    existingSubmission?: ExistingSubmission | null
    existingResponses?: Record<number, string>
    canEdit: boolean
    biodataStatus?: BiodataStatus
}

const props = defineProps<Props>()

const isSubmitting = ref(false)
const isEditing = computed(() => !!props.existingSubmission)

// Alert state
const showAlert = ref(false)
const alertType = ref<'success' | 'error'>('success')
const alertMessage = ref('')
const alertTitle = ref('')

// Initialize form data
const initializeFormData = () => {
    const data: Record<string, string | boolean | number | File | null> = {}

    props.form.form_fields.forEach((field) => {
        const existingValue = props.existingResponses?.[field.id]

        switch (field.field_type.name.toLowerCase()) {
            case 'checkbox':
                data[`field_${field.id}`] = existingValue === 'true' || existingValue === '1'
                break
            case 'radio':
            case 'select':
                data[`field_${field.id}`] = existingValue || ''
                break
            case 'file':
                data[`field_${field.id}`] = null
                break
            case 'number':
                data[`field_${field.id}`] = existingValue ? Number(existingValue) : ''
                break
            default:
                data[`field_${field.id}`] = existingValue || ''
                break
        }
    })

    return data
}

const formData = useForm({
    form_id: props.form.id,
    is_submitted: false,
    responses: initializeFormData(),
})

const fieldErrors = computed(() => formData.errors as Record<string, string>)

// Progress calculation
const progress = computed(() => {
    const total = props.form.form_fields.length
    const filled = props.form.form_fields.filter((field) => {
        const value = formData.responses[`field_${field.id}`]
        return value && (typeof value !== 'string' || value.trim() !== '')
    }).length
    return Math.round((filled / total) * 100)
})

// Get field icon based on type
const getFieldIcon = (fieldTypeName: string) => {
    const iconMap: Record<string, LucideIcon> = {
        text: User,
        email: Mail,
        phone: Phone,
        date: Calendar,
        textarea: FileText,
        select: GraduationCap,
        radio: Shield,
        checkbox: CheckCircle2,
        file: Upload,
    }
    return iconMap[fieldTypeName.toLowerCase()] || Info
}

const handleFileUpload = (event: Event, fieldId: number) => {
    const target = event.target as HTMLInputElement
    const file = target.files?.[0]
    if (file) {
        if (file.size > 10 * 1024 * 1024) {
            showCustomAlert('error', 'File Terlalu Besar', 'Ukuran file maksimal 10MB.')
            target.value = ''
            return
        }
        formData.responses[`field_${fieldId}`] = file
    }
}

const showCustomAlert = (type: 'success' | 'error', title: string, message: string) => {
    alertType.value = type
    alertTitle.value = title
    alertMessage.value = message
    showAlert.value = true

    setTimeout(() => {
        showAlert.value = false
    }, 5000)
}

const dismissCustomAlert = () => {
    showAlert.value = false
}

const getFormResponses = () => {
    const responses: Array<{ form_field_id: number; value: string | number | boolean | File | null }> = []

    props.form.form_fields.forEach((field) => {
        const value = formData.responses[`field_${field.id}`]

        if (field.field_type.name.toLowerCase() !== 'file') {
            responses.push({
                form_field_id: field.id,
                value: value !== null && value !== undefined ? value : '',
            })
        } else if (value && typeof value === 'string') {
            responses.push({
                form_field_id: field.id,
                value: value,
            })
        }
    })

    return responses
}

const submitForm = () => {
    if (!props.canEdit) {
        showCustomAlert('error', 'Tidak Dapat Mengedit', 'Anda tidak dapat mengedit formulir ini.')
        return
    }

    // Validate required fields
    const missingFields: string[] = []
    props.form.form_fields.forEach((field) => {
        if (field.is_required) {
            const value = formData.responses[`field_${field.id}`]

            // Special handling for checkbox - it's valid if it's true OR false (boolean)
            if (field.field_type.name.toLowerCase() === 'checkbox') {
                // Checkbox hanya invalid jika undefined/null
                if (value === undefined || value === null) {
                    missingFields.push(field.label)
                }
            } else {
                // For other fields, check if empty
                if (!value || (typeof value === 'string' && value.trim() === '')) {
                    missingFields.push(field.label)
                }
            }
        }
    })

    if (missingFields.length > 0) {
        showCustomAlert(
            'error',
            'Field Belum Lengkap',
            `Harap lengkapi field berikut: ${missingFields.join(', ')}`
        )
        return
    }

    isSubmitting.value = true

    const hasFileUploads = props.form.form_fields.some((field) => {
        const value = formData.responses[`field_${field.id}`]
        return field.field_type.name.toLowerCase() === 'file' && value instanceof File
    })

    if (hasFileUploads) {
        const formDataPayload = new FormData()
        formDataPayload.append('form_id', String(props.form.id))
        formDataPayload.append('is_submitted', 'true')

        const responses: Array<{ form_field_id: number; value: string | number | boolean | File | null }> = []
        props.form.form_fields.forEach((field) => {
            const value = formData.responses[`field_${field.id}`]

            if (field.field_type.name.toLowerCase() === 'file' && value instanceof File) {
                formDataPayload.append(`file_uploads[${field.id}]`, value)
                responses.push({
                    form_field_id: field.id,
                    value: '',
                })
            } else {
                // Convert boolean to string for checkbox
                const finalValue = typeof value === 'boolean' ? (value ? '1' : '0') : value || ''
                responses.push({
                    form_field_id: field.id,
                    value: finalValue,
                })
            }
        })

        responses.forEach((response, index) => {
            formDataPayload.append(
                `responses[${index}][form_field_id]`,
                String(response.form_field_id)
            )
            formDataPayload.append(`responses[${index}][value]`, String(response.value))
        })

        formData
            .transform(() => formDataPayload)
            .post(route('user.biodata.submit'), {
                forceFormData: true,
                onSuccess: () => {
                    router.visit(route('user.dashboard'))
                },
                onError: (errors) => {
                    console.error('Submit errors:', errors)
                    const firstError = Object.values(errors)[0]
                    showCustomAlert(
                        'error',
                        'Gagal Mengirim',
                        typeof firstError === 'string'
                            ? firstError
                            : 'Terjadi kesalahan saat mengirim biodata.'
                    )
                },
                onFinish: () => {
                    isSubmitting.value = false
                },
            })
    } else {
        const responses = getFormResponses()

        // Convert boolean values to string for checkbox fields
        const processedResponses = responses.map((response) => {
            const field = props.form.form_fields.find((f) => f.id === response.form_field_id)
            if (field?.field_type.name.toLowerCase() === 'checkbox') {
                return {
                    ...response,
                    value:
                        typeof response.value === 'boolean'
                            ? response.value
                                ? '1'
                                : '0'
                            : response.value,
                }
            }
            return response
        })

        const payload = {
            form_id: props.form.id,
            is_submitted: true,
            responses: processedResponses,
        }

        formData
            .transform(() => payload)
            .post(route('user.biodata.submit'), {
                onSuccess: () => {
                    router.visit(route('user.dashboard'))
                },
                onError: (errors) => {
                    console.error('Submit errors:', errors)
                    const firstError = Object.values(errors)[0]
                    showCustomAlert(
                        'error',
                        'Gagal Mengirim',
                        typeof firstError === 'string'
                            ? firstError
                            : 'Terjadi kesalahan saat mengirim biodata.'
                    )
                },
                onFinish: () => {
                    isSubmitting.value = false
                },
            })
    }
}

const getStatusVariant = (status: string): 'default' | 'destructive' | 'outline' => {
    const variants: Record<string, 'default' | 'destructive' | 'outline'> = {
        pending: 'default',
        under_review: 'default',
        approved: 'outline',
        rejected: 'destructive',
        needs_revision: 'destructive',
    }
    return variants[status] || 'secondary'
}

const getStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
        pending: 'Menunggu Persetujuan',
        under_review: 'Sedang Direview',
        approved: 'Disetujui',
        rejected: 'Ditolak',
        needs_revision: 'Perlu Revisi',
    }
    return labels[status] || status
}

const shouldShowBiodataAlert = computed(() => {
    return props.biodataStatus?.message && !props.biodataStatus.completed
})

const biodataAlertType = computed<'success' | 'error'>(() => {
    return props.biodataStatus?.completed ? 'success' : 'error'
})

const shouldShowSubmissionAlert = computed(() => {
    return !props.canEdit && props.existingSubmission
})

const submissionAlertType = computed<'success' | 'error'>(() => {
    return props.existingSubmission?.status === 'approved' ? 'success' : 'error'
})

const submissionAlertMessage = computed(() => {
    if (!props.existingSubmission) return ''

    const status = props.existingSubmission.status
    if (status === 'approved') {
        return 'Biodata Anda telah disetujui.'
    } else if (status === 'pending') {
        return 'Biodata Anda sedang menunggu persetujuan.'
    } else {
        return 'Biodata Anda sedang dalam proses review.'
    }
})

const goBack = () => {
    router.visit(route('user.dashboard'))
}
</script>

<template>
    <Head title="Formulir Biodata" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" class="hover:bg-primary/10" @click="goBack">
                        <ArrowLeft class="h-5 w-5" />
                    </Button>
                    <div class="p-2 bg-primary/10 rounded-lg">
                        <FileText class="h-6 w-6 text-primary" />
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800">
                            {{ isEditing ? 'Edit Biodata' : 'Lengkapi Biodata' }}
                        </h2>
                        <p class="text-sm text-muted-foreground mt-0.5">
                            {{ form.description || 'Silakan lengkapi biodata Anda' }}
                        </p>
                    </div>
                </div>
                <Badge
                    v-if="existingSubmission"
                    :variant="getStatusVariant(existingSubmission.status)"
                    class="text-xs px-3 py-1"
                >
                    {{ getStatusLabel(existingSubmission.status) }}
                </Badge>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6 py-6 px-4 sm:px-6 lg:px-8">
            <!-- Custom Alert -->
            <Transition name="alert">
                <Alert
                    v-if="showAlert"
                    :type="alertType"
                    :title="alertTitle"
                    :message="alertMessage"
                    @dismiss="dismissCustomAlert"
                />
            </Transition>

            <!-- Biodata Status Alert -->
            <Alert
                v-if="shouldShowBiodataAlert"
                :type="biodataAlertType"
                title="Perhatian"
                :message="biodataStatus!.message!"
                :dismissible="false"
            />

            <!-- Submission Status Alert -->
            <Alert
                v-if="shouldShowSubmissionAlert"
                :type="submissionAlertType"
                :title="`Status: ${getStatusLabel(existingSubmission!.status)}`"
                :message="submissionAlertMessage"
                :dismissible="false"
            />

            <!-- Progress Card (only show when editing) -->
            <Card v-if="canEdit" class="border-primary/20">
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <CheckCircle2 class="h-4 w-4 text-primary" />
                            <span class="text-sm font-medium">Progress Pengisian</span>
                        </div>
                        <span class="text-sm font-semibold text-primary">{{ progress }}%</span>
                    </div>
                    <Progress :model-value="progress" class="h-2" />
                    <p class="text-xs text-muted-foreground mt-2">
                        {{ props.form.form_fields.length }} field tersedia
                    </p>
                </CardContent>
            </Card>

            <!-- Main Form -->
            <Card>
                <CardHeader class="bg-linear-to-r from-primary/5 to-primary/10">
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        {{ form.title }}
                    </CardTitle>
                    <CardDescription v-if="form.description">
                        {{ form.description }}
                    </CardDescription>
                </CardHeader>

                <CardContent class="pt-6">
                    <form class="space-y-8" @submit.prevent="submitForm">
                        <!-- Form Fields -->
                        <div
                            v-for="(field, index) in form.form_fields"
                            :key="field.id"
                            class="space-y-3 pb-6"
                            :class="{ 'border-b': index < form.form_fields.length - 1 }"
                        >
                            <div class="flex items-start gap-3">
                                <component
                                    :is="getFieldIcon(field.field_type.name)"
                                    class="h-5 w-5 text-muted-foreground mt-0.5 shrink-0"
                                />
                                <div class="flex-1 space-y-2">
                                    <Label
                                        :for="`field_${field.id}`"
                                        class="text-base flex items-center gap-2"
                                    >
                                        {{ field.label }}
                                        <Badge
                                            v-if="field.is_required"
                                            variant="destructive"
                                            class="text-xs px-1.5 py-0"
                                        >
                                            Wajib
                                        </Badge>
                                    </Label>

                                    <!-- Helper Text -->
                                    <p
                                        v-if="field.helper_text"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ field.helper_text }}
                                    </p>

                                    <!-- Text, Email, Number, URL, Phone, Date, Time -->
                                    <Input
                                        v-if="
                                            [
                                                'text',
                                                'email',
                                                'number',
                                                'url',
                                                'phone',
                                                'date',
                                                'time',
                                            ].includes(field.field_type.name.toLowerCase())
                                        "
                                        :id="`field_${field.id}`"
                                        v-model="formData.responses[`field_${field.id}`]"
                                        :type="field.field_type.name.toLowerCase()"
                                        :placeholder="`Masukkan ${field.label.toLowerCase()}...`"
                                        :required="field.is_required"
                                        :disabled="!canEdit"
                                        class="max-w-2xl"
                                    />

                                    <!-- Textarea -->
                                    <Textarea
                                        v-else-if="
                                            field.field_type.name.toLowerCase() === 'textarea'
                                        "
                                        :id="`field_${field.id}`"
                                        v-model="formData.responses[`field_${field.id}`]"
                                        rows="4"
                                        :placeholder="`Masukkan ${field.label.toLowerCase()}...`"
                                        :required="field.is_required"
                                        :disabled="!canEdit"
                                        class="max-w-2xl"
                                    />

                                    <!-- Select/Dropdown -->
                                    <Select
                                        v-else-if="field.field_type.name.toLowerCase() === 'select'"
                                        v-model="formData.responses[`field_${field.id}`]"
                                        :disabled="!canEdit"
                                    >
                                        <SelectTrigger class="max-w-md">
                                            <SelectValue
                                                :placeholder="`Pilih ${field.label.toLowerCase()}...`"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="option in field.form_field_options"
                                                :key="option.id"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>

                                    <!-- Radio Group -->
                                    <RadioGroup
                                        v-else-if="field.field_type.name.toLowerCase() === 'radio'"
                                        v-model="formData.responses[`field_${field.id}`]"
                                        :disabled="!canEdit"
                                        class="space-y-2"
                                    >
                                        <div
                                            v-for="option in field.form_field_options"
                                            :key="option.id"
                                            class="flex items-center space-x-2"
                                        >
                                            <RadioGroupItem
                                                :id="`field_${field.id}_${option.id}`"
                                                :value="option.value"
                                            />
                                            <Label
                                                :for="`field_${field.id}_${option.id}`"
                                                class="font-normal cursor-pointer"
                                            >
                                                {{ option.label }}
                                            </Label>
                                        </div>
                                    </RadioGroup>

                                    <!-- Checkbox -->
                                    <div
                                        v-else-if="
                                            field.field_type.name.toLowerCase() === 'checkbox'
                                        "
                                        class="flex items-center space-x-2"
                                    >
                                        <Checkbox
                                            :id="`field_${field.id}`"
                                            v-model:checked="
                                                formData.responses[`field_${field.id}`]
                                            "
                                            :disabled="!canEdit"
                                        />
                                        <Label
                                            :for="`field_${field.id}`"
                                            class="font-normal cursor-pointer"
                                        >
                                            Ya, saya setuju
                                        </Label>
                                    </div>

                                    <!-- File Upload -->
                                    <div
                                        v-else-if="field.field_type.name.toLowerCase() === 'file'"
                                        class="space-y-2"
                                    >
                                        <div
                                            class="flex items-center gap-2 p-4 border-2 border-dashed rounded-lg hover:border-primary/50 transition-colors"
                                        >
                                            <Input
                                                :id="`field_${field.id}`"
                                                type="file"
                                                :disabled="!canEdit"
                                                class="flex-1 cursor-pointer file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-primary-foreground hover:file:bg-primary/90"
                                                @change="handleFileUpload($event, field.id)"
                                            />
                                        </div>
                                        <p
                                            v-if="existingResponses?.[field.id]"
                                            class="text-xs text-muted-foreground flex items-center gap-1"
                                        >
                                            <CheckCircle2 class="h-3 w-3 text-green-600" />
                                            File saat ini: {{ existingResponses[field.id] }}
                                        </p>
                                    </div>

                                    <!-- Default fallback -->
                                    <Input
                                        v-else
                                        :id="`field_${field.id}`"
                                        v-model="formData.responses[`field_${field.id}`]"
                                        type="text"
                                        :placeholder="`Masukkan ${field.label.toLowerCase()}...`"
                                        :required="field.is_required"
                                        :disabled="!canEdit"
                                        class="max-w-2xl"
                                    />

                                    <!-- Field Error -->
                                    <div
                                        v-if="fieldErrors[`field_${field.id}`]"
                                        class="text-xs text-destructive flex items-center gap-1"
                                    >
                                        <AlertCircle class="h-3 w-3" />
                                        {{ fieldErrors[`field_${field.id}`] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </CardContent>

                <CardFooter class="bg-muted/30 flex flex-col sm:flex-row justify-between gap-4">
                    <!-- Submission Info -->
                    <div v-if="existingSubmission" class="text-xs text-muted-foreground space-y-1">
                        <p class="flex items-center gap-1">
                            <Calendar class="h-3 w-3" />
                            Dibuat:
                            {{
                                new Date(existingSubmission.created_at).toLocaleDateString(
                                    'id-ID',
                                    {
                                        dateStyle: 'long',
                                    }
                                )
                            }}
                        </p>
                        <p class="flex items-center gap-1">
                            <Calendar class="h-3 w-3" />
                            Diperbarui:
                            {{
                                new Date(existingSubmission.updated_at).toLocaleDateString(
                                    'id-ID',
                                    {
                                        dateStyle: 'long',
                                    }
                                )
                            }}
                        </p>
                    </div>

                    <div class="flex gap-3 sm:ml-auto">
                        <Button type="button" variant="outline" class="gap-2" @click="goBack">
                            <ArrowLeft class="h-4 w-4" />
                            Kembali
                        </Button>

                        <Button
                            v-if="canEdit"
                            type="button"
                            :disabled="isSubmitting || formData.processing"
                            class="gap-2"
                            @click="submitForm"
                        >
                            <Send class="h-4 w-4" />
                            {{ isSubmitting ? 'Mengirim...' : 'Serahkan Biodata' }}
                        </Button>
                    </div>
                </CardFooter>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
