<!-- resources/js/Pages/User/FormPhase.vue -->
<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Checkbox } from '@/Components/ui/checkbox';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Badge } from '@/Components/ui/badge';
import { Separator } from '@/Components/ui/separator';
import { Progress } from '@/Components/ui/progress';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Clock,
    FileText,
    AlertCircle,
    CheckCircle2,
    Save,
    Upload
} from 'lucide-vue-next';

interface FormField {
    id: number;
    label: string;
    is_required: boolean;
    field_type: {
        id: number;
        name: string;
    };
    form_field_options: FormFieldOption[];
    order: number;
}

interface FormFieldOption {
    id: number;
    label: string;
}

interface Form {
    id: number;
    title: string;
    description: string;
    form_fields: FormField[];
}

interface FormAccessControl {
    id: number;
    form: Form;
    order: number;
    needs_review: boolean;
    phase_type: {
        name: string;
    };
    user_submission?: {
        id: number;
        is_submitted: boolean;
        can_proceed: boolean;
        created_at: string;
    };
}

interface FormPhase {
    id: number;
    title: string;
    description: string;
    form_access_controls: FormAccessControl[];
}

interface SubmissionPeriod {
    id: number;
    name: string;
}

interface Props {
    submissionPeriod: SubmissionPeriod;
    formPhase: FormPhase;
    currentStep?: number;
}

const props = defineProps<Props>();

const currentStepIndex = ref(props.currentStep ? props.currentStep - 1 : 0);
const isSubmitting = ref(false);

const currentForm = computed(() => {
    const formAccessControl = props.formPhase.form_access_controls[currentStepIndex.value];
    return formAccessControl?.form;
});

const currentFormAccessControl = computed(() => {
    return props.formPhase.form_access_controls[currentStepIndex.value];
});

const totalSteps = computed(() => props.formPhase.form_access_controls.length);

const progress = computed(() => {
    return ((currentStepIndex.value + 1) / totalSteps.value) * 100;
});

const canGoNext = computed(() => {
    return currentStepIndex.value < totalSteps.value - 1;
});

const canGoPrevious = computed(() => {
    return currentStepIndex.value > 0;
});

// Form data untuk current form
const formData = useForm<Record<string, any>>({});

// Initialize form data
watch(currentForm, (form) => {
    if (form) {
        const initialData: Record<string, any> = {};
        form.form_fields.forEach(field => {
            // Initialize different field types with appropriate default values
            switch (field.field_type.name.toLowerCase()) {
                case 'checkbox':
                    initialData[`field_${field.id}`] = false;
                    break;
                case 'radio':
                case 'select':
                    initialData[`field_${field.id}`] = '';
                    break;
                case 'file':
                    initialData[`field_${field.id}`] = null;
                    break;
                default:
                    initialData[`field_${field.id}`] = '';
                    break;
            }
        });

        // Load existing data if user has submission
        if (currentFormAccessControl.value?.user_submission) {
            // Load from existing submission (you'll need to implement this API)
            // For now, we'll initialize with empty data
        }

        formData.reset();
        Object.keys(initialData).forEach(key => {
            formData[key] = initialData[key];
        });
    }
}, { immediate: true });

const saveDraft = () => {
    if (!currentForm.value) return;

    const payload = {
        form_id: currentForm.value.id,
        submission_period_id: props.submissionPeriod.id,
        form_phase_id: props.formPhase.id,
        is_submitted: false,
        responses: getFormResponses()
    };

    router.post(route('user.form-submission.save-draft'), payload, {
        onSuccess: () => {
            // Handle success
        },
        onError: () => {
            // Handle error
        }
    });
};

const submitForm = () => {
    if (!currentForm.value) return;

    isSubmitting.value = true;

    const payload = {
        form_id: currentForm.value.id,
        submission_period_id: props.submissionPeriod.id,
        form_phase_id: props.formPhase.id,
        is_submitted: true,
        responses: getFormResponses()
    };

    router.post(route('user.form-submission.submit'), payload, {
        onSuccess: () => {
            // If can proceed to next step and there is next step
            if (canGoNext.value && currentFormAccessControl.value && !currentFormAccessControl.value.needs_review) {
                nextStep();
            } else {
                // Redirect to dashboard or show completion message
                router.visit(route('user.dashboard'));
            }
        },
        onError: () => {
            // Handle error
        },
        onFinish: () => {
            isSubmitting.value = false;
        }
    });
};

const getFormResponses = () => {
    if (!currentForm.value) return [];

    return currentForm.value.form_fields.map(field => ({
        form_field_id: field.id,
        value: formData[`field_${field.id}`] || ''
    }));
};

const nextStep = () => {
    if (canGoNext.value) {
        currentStepIndex.value++;
    }
};

const previousStep = () => {
    if (canGoPrevious.value) {
        currentStepIndex.value--;
    }
};

const goToStep = (stepIndex: number) => {
    // Only allow going to completed steps or current step
    const targetFormAccessControl = props.formPhase.form_access_controls[stepIndex];
    if (targetFormAccessControl?.user_submission?.is_submitted || stepIndex <= currentStepIndex.value) {
        currentStepIndex.value = stepIndex;
    }
};

const renderFormField = (field: FormField) => {
    const fieldKey = `field_${field.id}`;
    const fieldType = field.field_type.name.toLowerCase();

    switch (fieldType) {
        case 'text':
            return {
                component: 'Input',
                props: {
                    type: 'text',
                    placeholder: `Masukkan ${field.label.toLowerCase()}...`
                }
            };
        case 'email':
            return {
                component: 'Input',
                props: {
                    type: 'email',
                    placeholder: `Masukkan alamat email...`
                }
            };
        case 'number':
            return {
                component: 'Input',
                props: {
                    type: 'number',
                    placeholder: `Masukkan ${field.label.toLowerCase()}...`
                }
            };
        case 'url':
            return {
                component: 'Input',
                props: {
                    type: 'url',
                    placeholder: `Masukkan URL...`
                }
            };
        case 'phone':
            return {
                component: 'Input',
                props: {
                    type: 'tel',
                    placeholder: `Masukkan nomor telepon...`
                }
            };
        case 'date':
            return {
                component: 'Input',
                props: {
                    type: 'date'
                }
            };
        case 'time':
            return {
                component: 'Input',
                props: {
                    type: 'time'
                }
            };
        case 'textarea':
            return {
                component: 'Textarea',
                props: {
                    placeholder: `Masukkan ${field.label.toLowerCase()}...`,
                    rows: 4
                }
            };
        case 'select':
            return {
                component: 'Select',
                options: field.form_field_options
            };
        case 'radio':
            return {
                component: 'RadioGroup',
                options: field.form_field_options
            };
        case 'checkbox':
            return {
                component: 'Checkbox'
            };
        case 'file':
            return {
                component: 'File',
                props: {
                    accept: '*/*'
                }
            };
        default:
            return {
                component: 'Input',
                props: {
                    type: 'text',
                    placeholder: `Masukkan ${field.label.toLowerCase()}...`
                }
            };
    }
};

const isStepCompleted = (stepIndex: number) => {
    const formAccessControl = props.formPhase.form_access_controls[stepIndex];
    return formAccessControl?.user_submission?.is_submitted || false;
};

const isStepPendingReview = (stepIndex: number) => {
    const formAccessControl = props.formPhase.form_access_controls[stepIndex];
    return formAccessControl?.user_submission?.is_submitted &&
        formAccessControl?.needs_review &&
        !formAccessControl?.user_submission?.can_proceed;
};

const canAccessStep = (stepIndex: number) => {
    // Can access current step, completed steps, or next step if previous is completed
    if (stepIndex <= currentStepIndex.value) return true;
    if (stepIndex === 0) return true;

    const previousFormAccessControl = props.formPhase.form_access_controls[stepIndex - 1];
    return previousFormAccessControl?.user_submission?.can_proceed || false;
};

const handleFileUpload = (event: Event, fieldId: number) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        formData[`field_${fieldId}`] = file;
    }
};
</script>

<template>

    <Head :title="`${formPhase.title} - ${submissionPeriod.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="router.visit(route('user.dashboard'))">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Kembali ke Dashboard
                </Button>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ formPhase.title }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ submissionPeriod.name }}
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Progress Header -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between mb-4">
                        <CardTitle>Progress Pengisian Form</CardTitle>
                        <Badge variant="outline">
                            Step {{ currentStepIndex + 1 }} of {{ totalSteps }}
                        </Badge>
                    </div>
                    <Progress :value="progress" class="h-2" />
                </CardHeader>
            </Card>

            <div class="grid gap-6 lg:grid-cols-12">
                <!-- Steps Sidebar -->
                <div class="lg:col-span-3">
                    <Card class="sticky top-6">
                        <CardHeader>
                            <CardTitle class="text-lg">Langkah-langkah</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2">
                                <button v-for="(formAccessControl, index) in formPhase.form_access_controls"
                                    :key="formAccessControl.id" @click="goToStep(index)"
                                    :disabled="!canAccessStep(index)"
                                    class="w-full flex items-center gap-3 p-3 text-left rounded-lg border transition-colors"
                                    :class="{
                                        'bg-primary text-primary-foreground border-primary': index === currentStepIndex,
                                        'bg-green-50 border-green-200 text-green-800': isStepCompleted(index) && index !== currentStepIndex,
                                        'bg-yellow-50 border-yellow-200 text-yellow-800': isStepPendingReview(index) && index !== currentStepIndex,
                                        'hover:bg-muted': canAccessStep(index) && index !== currentStepIndex && !isStepCompleted(index) && !isStepPendingReview(index),
                                        'opacity-50 cursor-not-allowed': !canAccessStep(index)
                                    }">
                                    <div class="flex-shrink-0">
                                        <CheckCircle2 v-if="isStepCompleted(index)" class="h-5 w-5" />
                                        <Clock v-else-if="isStepPendingReview(index)" class="h-5 w-5" />
                                        <span v-else
                                            class="flex items-center justify-center w-5 h-5 rounded-full text-xs font-medium border-2">
                                            {{ index + 1 }}
                                        </span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-sm truncate">{{ formAccessControl.form.title }}</p>
                                        <p class="text-xs opacity-75 truncate">{{ formAccessControl.phase_type.name }}
                                        </p>
                                    </div>
                                </button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Main Form Content -->
                <div class="lg:col-span-9">
                    <Card v-if="currentForm">
                        <CardHeader>
                            <div class="flex items-start justify-between">
                                <div>
                                    <CardTitle class="text-xl">{{ currentForm.title }}</CardTitle>
                                    <p class="text-muted-foreground mt-1">{{ currentForm.description }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge v-if="currentFormAccessControl?.needs_review" variant="outline">
                                        <AlertCircle class="h-3 w-3 mr-1" />
                                        Perlu Review
                                    </Badge>
                                    <Badge variant="secondary">
                                        {{ currentFormAccessControl?.phase_type.name }}
                                    </Badge>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent>
                            <!-- Show completed message if form is already submitted -->
                            <div v-if="currentFormAccessControl?.user_submission?.is_submitted" class="mb-6">
                                <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                                    <div class="flex items-center gap-2">
                                        <CheckCircle2 class="h-5 w-5 text-green-600" />
                                        <div>
                                            <p class="font-medium text-green-800">Form Sudah Diserahkan</p>
                                            <p class="text-sm text-green-700">
                                                Diserahkan pada {{ new
                                                    Date(currentFormAccessControl.user_submission.created_at).toLocaleString('id-ID')
                                                }}
                                            </p>
                                            <p v-if="currentFormAccessControl.needs_review && !currentFormAccessControl.user_submission.can_proceed"
                                                class="text-sm text-yellow-700 mt-1">
                                                Form sedang dalam proses review. Anda tidak dapat melanjutkan ke step
                                                selanjutnya sampai review selesai.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Fields -->
                            <div v-else class="space-y-6">
                                <div v-for="field in currentForm.form_fields" :key="field.id" class="space-y-2">
                                    <Label :for="`field_${field.id}`" class="flex items-center gap-2">
                                        {{ field.label }}
                                        <Badge v-if="field.is_required" variant="destructive" class="text-xs">
                                            Wajib
                                        </Badge>
                                    </Label>

                                    <!-- Text, Email, Number, URL, Phone, Date, Time Inputs -->
                                    <Input
                                        v-if="['text', 'email', 'number', 'url', 'phone', 'date', 'time'].includes(field.field_type.name.toLowerCase())"
                                        :id="`field_${field.id}`" v-model="formData[`field_${field.id}`]"
                                        v-bind="renderFormField(field).props" />

                                    <!-- Textarea -->
                                    <Textarea v-else-if="field.field_type.name.toLowerCase() === 'textarea'"
                                        :id="`field_${field.id}`" v-model="formData[`field_${field.id}`]"
                                        v-bind="renderFormField(field).props" />

                                    <!-- Select Dropdown -->
                                    <Select v-else-if="field.field_type.name.toLowerCase() === 'select'"
                                        v-model="formData[`field_${field.id}`]">
                                        <SelectTrigger>
                                            <SelectValue :placeholder="`Pilih ${field.label.toLowerCase()}...`" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="option in field.form_field_options" :key="option.id"
                                                :value="option.id.toString()">
                                                {{ option.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>

                                    <!-- Radio Group -->
                                    <RadioGroup v-else-if="field.field_type.name.toLowerCase() === 'radio'"
                                        v-model="formData[`field_${field.id}`]" class="space-y-2">
                                        <div v-for="option in field.form_field_options" :key="option.id"
                                            class="flex items-center space-x-2">
                                            <RadioGroupItem :value="option.id.toString()"
                                                :id="`${field.id}_${option.id}`" />
                                            <Label :for="`${field.id}_${option.id}`" class="text-sm font-normal">
                                                {{ option.label }}
                                            </Label>
                                        </div>
                                    </RadioGroup>

                                    <!-- Single Checkbox -->
                                    <div v-else-if="field.field_type.name.toLowerCase() === 'checkbox'"
                                        class="flex items-center space-x-2">
                                        <Checkbox :id="`field_${field.id}`" v-model="formData[`field_${field.id}`]" />
                                        <Label :for="`field_${field.id}`" class="text-sm font-normal">
                                            Ya, saya setuju
                                        </Label>
                                    </div>

                                    <!-- File Upload -->
                                    <div v-else-if="field.field_type.name.toLowerCase() === 'file'" class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <Input :id="`field_${field.id}`" type="file"
                                                @change="handleFileUpload($event, field.id)"
                                                class="file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-primary-foreground hover:file:bg-primary/80" />
                                            <Upload class="h-4 w-4 text-muted-foreground" />
                                        </div>
                                        <p class="text-xs text-muted-foreground">
                                            Upload file yang sesuai dengan persyaratan
                                        </p>
                                    </div>

                                    <!-- Fallback for unknown field types -->
                                    <Input v-else :id="`field_${field.id}`" v-model="formData[`field_${field.id}`]"
                                        type="text" :placeholder="`Masukkan ${field.label.toLowerCase()}...`" />
                                </div>

                                <Separator />

                                <!-- Form Actions -->
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Button type="button" variant="outline" @click="previousStep"
                                            :disabled="!canGoPrevious">
                                            <ArrowLeft class="h-4 w-4 mr-2" />
                                            Sebelumnya
                                        </Button>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <Button type="button" variant="outline" @click="saveDraft"
                                            :disabled="formData.processing">
                                            <Save class="h-4 w-4 mr-2" />
                                            Simpan Draft
                                        </Button>

                                        <Button type="button" @click="submitForm"
                                            :disabled="isSubmitting || formData.processing">
                                            {{ isSubmitting ? 'Menyerahkan...' : 'Serahkan Form' }}
                                            <Check class="h-4 w-4 ml-2" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Completion Card -->
                    <Card v-if="currentStepIndex >= totalSteps - 1 && isStepCompleted(currentStepIndex)">
                        <CardContent class="text-center py-12">
                            <CheckCircle2 class="h-16 w-16 text-green-600 mx-auto mb-4" />
                            <h3 class="text-xl font-semibold mb-2">Form Phase Selesai!</h3>
                            <p class="text-muted-foreground mb-6">
                                Anda telah menyelesaikan semua form dalam phase "{{ formPhase.title }}".
                            </p>
                            <Button @click="router.visit(route('user.dashboard'))">
                                Kembali ke Dashboard
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
