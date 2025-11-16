<!-- resources/js/Pages/Reviewer/EvaluationForm/Show.vue -->
<script setup lang="ts">
import { computed, ref, watch, onUnmounted } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Textarea } from "@/Components/ui/textarea";
import { RadioGroup, RadioGroupItem } from "@/Components/ui/radio-group";
import { Checkbox } from "@/Components/ui/checkbox";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Progress } from "@/Components/ui/progress";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/Components/ui/dialog";
import {
    ArrowLeft,
    Save,
    Send,
    FileText,
    User,
    AlertTriangle,
    Calendar,
} from "lucide-vue-next";

// Types
interface FieldType {
    id: number;
    name: string;
}

interface FieldOption {
    id: number;
    label: string;
    value?: string;
}

interface ReviewFormField {
    id: number;
    label: string;
    description?: string;
    is_required: boolean;
    field_type: FieldType;
    review_form_field_options: FieldOption[];
}

interface ReviewEvaluationForm {
    id: number;
    title: string;
    description?: string;
    review_form_fields: ReviewFormField[];
}

interface FormSubmission {
    id: number;
    form: {
        id: number;
        title: string;
    };
    submitted_by: {
        id: number;
        name: string;
    };
}

interface ReviewFormResponse {
    id: number;
    status: string;
    submitted_at?: string;
}

interface ReviewerFormAssignment {
    id: number;
    is_required: boolean;
    due_date?: string;
    review_evaluation_form: ReviewEvaluationForm;
    submission_reviewer: {
        form_submission: FormSubmission;
    };
    review_form_response?: ReviewFormResponse;
}

interface Props {
    assignment: ReviewerFormAssignment;
    response: ReviewFormResponse;
    existingResponses: Record<string, string>;
    canEdit: boolean;
    canSubmit: boolean;
    completionPercentage: number;
}

const props = defineProps<Props>();

// Forms
const form = useForm({
    responses: { ...props.existingResponses },
    final_notes: '',
});

const saveDraftForm = useForm({
    responses: { ...props.existingResponses },
    final_notes: '',
});

const submitForm = useForm({
    responses: { ...props.existingResponses },
    final_notes: '',
});

// State
const showSubmitDialog = ref(false);
const autoSaveInterval = ref<ReturnType<typeof setInterval> | null>(null);
const lastSavedAt = ref<Date | null>(null);
const isSaving = ref(false);

// Computed Properties
const hasChanges = computed(() => {
    return JSON.stringify(form.responses) !== JSON.stringify(props.existingResponses) ||
        form.final_notes !== '';
});

const completedFields = computed(() => {
    return Object.values(form.responses).filter(value => value && value.trim()).length;
});

const totalFields = computed(() => {
    return props.assignment.review_evaluation_form.review_form_fields.length;
});

const currentCompletionPercentage = computed(() => {
    if (totalFields.value === 0) return 100;
    return Math.round((completedFields.value / totalFields.value) * 100);
});

const requiredFieldsCompleted = computed(() => {
    const requiredFields = props.assignment.review_evaluation_form.review_form_fields
        .filter(field => field.is_required);

    return requiredFields.every(field => {
        const response = form.responses[field.id];
        return response && response.trim();
    });
});

const canSubmitForm = computed(() => {
    return props.canEdit && requiredFieldsCompleted.value;
});

// Methods
const getDaysUntilDue = (): string => {
    if (!props.assignment.due_date) return '';

    const due = new Date(props.assignment.due_date);
    const now = new Date();
    const diffTime = due.getTime() - now.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays < 0) {
        return `${Math.abs(diffDays)} hari terlambat`;
    } else if (diffDays === 0) {
        return 'Tenggat hari ini';
    } else if (diffDays === 1) {
        return 'Tenggat besok';
    } else {
        return `${diffDays} hari tersisa`;
    }
};

const formatDate = (dateString?: string): string => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const startAutoSave = () => {
    if (autoSaveInterval.value) {
        clearInterval(autoSaveInterval.value);
    }

    autoSaveInterval.value = setInterval(() => {
        if (props.canEdit && hasChanges.value) {
            saveDraft();
        }
    }, 30000);
};

const saveDraft = async () => {
    if (!props.canEdit || isSaving.value) return;

    isSaving.value = true;
    saveDraftForm.responses = { ...form.responses };
    saveDraftForm.final_notes = form.final_notes;

    saveDraftForm.post(route('reviewer.evaluation-form.save-draft', props.assignment.id), {
        preserveScroll: true,
        onSuccess: () => {
            lastSavedAt.value = new Date();
        },
        onFinish: () => {
            isSaving.value = false;
        }
    });
};

const submitEvaluation = () => {
    submitForm.responses = { ...form.responses };
    submitForm.final_notes = form.final_notes;

    submitForm.post(route('reviewer.evaluation-form.submit', props.assignment.id), {
        onSuccess: () => {
            showSubmitDialog.value = false;
        }
    });
};

const getFieldComponent = (field: ReviewFormField): string => {
    const fieldType = field.field_type.name.toLowerCase();

    switch (fieldType) {
        case 'text':
        case 'email':
        case 'url':
        case 'number':
            return 'input';
        case 'textarea':
            return 'textarea';
        case 'select':
            return 'select';
        case 'radio':
            return 'radio';
        case 'checkbox':
            return 'checkbox';
        case 'date':
            return 'date';
        default:
            return 'input';
    }
};

const getInputType = (fieldTypeName: string): string => {
    switch (fieldTypeName.toLowerCase()) {
        case 'email':
            return 'email';
        case 'url':
            return 'url';
        case 'number':
            return 'number';
        case 'date':
            return 'date';
        default:
            return 'text';
    }
};

const handleCheckboxChange = (fieldId: number, optionValue: string, checked: boolean) => {
    const currentValues = form.responses[fieldId] ? form.responses[fieldId].split(',') : [];

    if (checked) {
        if (!currentValues.includes(optionValue)) {
            currentValues.push(optionValue);
        }
    } else {
        const index = currentValues.indexOf(optionValue);
        if (index > -1) {
            currentValues.splice(index, 1);
        }
    }

    form.responses[fieldId] = currentValues.join(',');
};

// Watchers
watch(() => form.responses, () => {
    if (props.canEdit) {
        startAutoSave();
    }
}, { deep: true });

// Lifecycle
if (props.canEdit) {
    startAutoSave();
}

onUnmounted(() => {
    if (autoSaveInterval.value) {
        clearInterval(autoSaveInterval.value);
    }
});
</script>

<template>

    <Head :title="`Evaluasi: ${assignment.review_evaluation_form.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="$inertia.visit(route('reviewer.assignments.index'))">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Kembali
                </Button>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ assignment.review_evaluation_form.title }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Evaluasi untuk: {{ assignment.submission_reviewer.form_submission.form.title }}
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Status and Progress Card -->
            <Card>
                <CardContent class="p-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Progress Info -->
                        <div class="space-y-4">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium">Progres</span>
                                    <span class="text-sm text-muted-foreground">
                                        {{ completedFields }}/{{ totalFields }} isian selesai
                                    </span>
                                </div>
                                <Progress :value="currentCompletionPercentage" class="h-2" />
                                <p class="text-xs text-muted-foreground mt-1">
                                    {{ currentCompletionPercentage }}% selesai
                                </p>
                            </div>

                            <div class="flex items-center space-x-4">
                                <div class="flex items-center space-x-2">
                                    <User class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm">
                                        {{ assignment.submission_reviewer.form_submission.submitted_by.name }}
                                    </span>
                                </div>

                                <Badge :variant="assignment.is_required ? 'default' : 'outline'">
                                    {{ assignment.is_required ? 'Wajib' : 'Opsional' }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Due Date & Status -->
                        <div class="space-y-4">
                            <div v-if="assignment.due_date" class="flex items-center space-x-2">
                                <Calendar class="h-4 w-4 text-muted-foreground" />
                                <div>
                                    <div class="text-sm font-medium">
                                        {{ formatDate(assignment.due_date) }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ getDaysUntilDue() }}
                                    </div>
                                </div>
                            </div>

                            <div v-if="lastSavedAt" class="flex items-center space-x-2 text-sm text-muted-foreground">
                                <Save class="h-4 w-4" />
                                <span>Terakhir disimpan: {{ formatDate(lastSavedAt.toISOString()) }}</span>
                            </div>

                            <div v-if="isSaving" class="flex items-center space-x-2 text-sm text-blue-600">
                                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                                <span>Menyimpan...</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Form Description -->
            <Card v-if="assignment.review_evaluation_form.description">
                <CardContent class="p-6">
                    <div class="flex items-start space-x-3">
                        <FileText class="h-5 w-5 text-blue-500 mt-0.5" />
                        <div>
                            <h3 class="font-medium mb-2">Instruksi</h3>
                            <p class="text-muted-foreground">
                                {{ assignment.review_evaluation_form.description }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Evaluation Form -->
            <form @submit.prevent class="space-y-6">
                <!-- Form Fields -->
                <div v-for="field in assignment.review_evaluation_form.review_form_fields" :key="field.id"
                    class="space-y-4">
                    <Card>
                        <CardContent class="p-6">
                            <div class="space-y-4">
                                <!-- Field Header -->
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <Label :for="`field_${field.id}`" class="text-base font-medium">
                                            {{ field.label }}
                                            <span v-if="field.is_required" class="text-red-500 ml-1">*</span>
                                        </Label>
                                        <p v-if="field.description" class="text-sm text-muted-foreground mt-1">
                                            {{ field.description }}
                                        </p>
                                    </div>
                                    <Badge v-if="field.is_required" variant="secondary" class="ml-2">
                                        Wajib
                                    </Badge>
                                </div>

                                <!-- Text Input -->
                                <Input v-if="getFieldComponent(field) === 'input'" :id="`field_${field.id}`"
                                    v-model="form.responses[field.id]" :type="getInputType(field.field_type.name)"
                                    :placeholder="`Enter ${field.label.toLowerCase()}`" :disabled="!canEdit"
                                    class="w-full" />

                                <!-- Textarea -->
                                <Textarea v-else-if="getFieldComponent(field) === 'textarea'" :id="`field_${field.id}`"
                                    v-model="form.responses[field.id]"
                                    :placeholder="`Enter ${field.label.toLowerCase()}`" :disabled="!canEdit" rows="4"
                                    class="w-full" />

                                <!-- Select -->
                                <Select v-else-if="getFieldComponent(field) === 'select'"
                                    v-model="form.responses[field.id]" :disabled="!canEdit">
                                    <SelectTrigger>
                                        <SelectValue :placeholder="`Select ${field.label.toLowerCase()}`" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="option in field.review_form_field_options" :key="option.id"
                                            :value="option.value || option.label">
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>

                                <!-- Radio Group -->
                                <RadioGroup v-else-if="getFieldComponent(field) === 'radio'"
                                    v-model="form.responses[field.id]" :disabled="!canEdit" class="space-y-2">
                                    <div v-for="option in field.review_form_field_options" :key="option.id"
                                        class="flex items-center space-x-2">
                                        <RadioGroupItem :value="option.value || option.label"
                                            :id="`${field.id}_${option.id}`" />
                                        <Label :for="`${field.id}_${option.id}`">
                                            {{ option.label }}
                                        </Label>
                                    </div>
                                </RadioGroup>

                                <!-- Checkbox Group -->
                                <div v-else-if="getFieldComponent(field) === 'checkbox'" class="space-y-2">
                                    <div v-for="option in field.review_form_field_options" :key="option.id"
                                        class="flex items-center space-x-2">
                                        <Checkbox :id="`${field.id}_${option.id}`"
                                            :checked="form.responses[field.id]?.includes(option.value || option.label)"
                                            :disabled="!canEdit" @update:checked="(checked: boolean) => {
                                                handleCheckboxChange(
                                                    field.id,
                                                    option.value || option.label,
                                                    checked
                                                );
                                            }" />
                                        <Label :for="`${field.id}_${option.id}`">
                                            {{ option.label }}
                                        </Label>
                                    </div>
                                </div>

                                <!-- Date Input -->
                                <Input v-else-if="getFieldComponent(field) === 'date'" :id="`field_${field.id}`"
                                    v-model="form.responses[field.id]" type="date" :disabled="!canEdit"
                                    class="w-full" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Final Notes -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center space-x-2">
                            <FileText class="h-5 w-5" />
                            <span>Catatan Tambahan (Opsional)</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Textarea v-model="form.final_notes"
                            placeholder="Tambahkan catatan atau komentar tambahan tentang evaluasi ini..."
                            :disabled="!canEdit" rows="4" class="w-full" />
                    </CardContent>
                </Card>

                <!-- Action Buttons -->
                <div class="flex items-center justify-between bg-white border rounded-lg p-4 sticky bottom-4">
                    <div class="flex items-center space-x-4">
                        <div class="text-sm text-muted-foreground">
                            {{ completedFields }}/{{ totalFields }} isian selesai
                        </div>
                        <div v-if="!requiredFieldsCompleted" class="flex items-center space-x-2 text-amber-600">
                            <AlertTriangle class="h-4 w-4" />
                            <span class="text-sm">Lengkapi isian wajib untuk mengirimkan</span>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <!-- Save Draft Button -->
                        <Button type="button" variant="outline" @click="saveDraft"
                            :disabled="!canEdit || isSaving || !hasChanges">
                            <Save class="h-4 w-4 mr-2" />
                            {{ isSaving ? 'Menyimpan...' : 'Simpan Draft' }}
                        </Button>

                        <!-- Submit Button with Confirmation -->
                        <Dialog v-if="canEdit" v-model:open="showSubmitDialog">
                            <DialogTrigger as-child>
                                <Button :disabled="!canSubmitForm" class="min-w-[120px]">
                                    <Send class="h-4 w-4 mr-2" />
                                    Kirim Evaluasi
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Kirim Evaluasi</DialogTitle>
                                    <DialogDescription>
                                        Apakah Anda yakin ingin mengirim evaluasi ini? Setelah dikirim,
                                        Anda tidak akan dapat mengubah jawaban Anda.
                                    </DialogDescription>
                                </DialogHeader>
                                <div class="space-y-2 py-4">
                                    <div class="text-sm">
                                        <strong>Status Penyelesaian:</strong>
                                        {{ completedFields }}/{{ totalFields }} isian selesai
                                    </div>
                                    <div class="text-sm">
                                        <strong>Isian Wajib:</strong>
                                        {{ requiredFieldsCompleted ? 'Semua selesai' : 'Beberapa belum lengkap' }}
                                    </div>
                                </div>
                                <DialogFooter>
                                    <Button variant="outline" @click="showSubmitDialog = false">
                                        Batal
                                    </Button>
                                    <Button @click="submitEvaluation" :disabled="submitForm.processing">
                                        {{ submitForm.processing ? 'Mengirim...' : 'Kirim' }}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>

                        <!-- View Only Message -->
                        <div v-else class="text-sm text-muted-foreground">
                            Evaluasi ini tidak dapat diedit
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
