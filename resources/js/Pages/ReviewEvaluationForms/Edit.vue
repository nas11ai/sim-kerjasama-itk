<!-- resources/js/Pages/Admin/ReviewEvaluationForms/Edit.vue -->
<script setup lang="ts">
import { computed, onMounted } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Textarea } from "@/Components/ui/textarea";
import { Switch } from "@/Components/ui/switch";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import { Badge } from "@/Components/ui/badge";
import { Plus, Trash2, GripVertical, ArrowLeft, AlertTriangle } from "lucide-vue-next";
import draggable from "vuedraggable";

// Types
interface FormPhase {
    id: number;
    title: string;
}

interface FieldType {
    id: number;
    name: string;
}

interface FieldOption {
    id?: number;
    label: string;
    value: string;
    order: number;
    temp_id: string;
}

interface ReviewFormField {
    id?: number;
    field_type_id: number | null;
    label: string;
    description: string;
    is_required: boolean;
    order: number;
    validation_rules: Record<string, any>;
    options: FieldOption[];
    temp_id: string;
    field_type?: FieldType;
    review_form_field_options?: FieldOption[];
}

interface ReviewEvaluationForm {
    id: number;
    title: string;
    description: string;
    form_phase_id: number;
    is_required: boolean;
    is_active: boolean;
    order: number;
    form_phase: FormPhase;
    review_form_fields: ReviewFormField[];
}

interface ReviewEvaluationFormData {
    title: string;
    description: string;
    form_phase_id: number;
    is_required: boolean;
    is_active: boolean;
    fields: ReviewFormField[];
    _method: string;
    [key: string]: any;
}

interface Props {
    evaluationForm: ReviewEvaluationForm;
    formPhases: FormPhase[];
    fieldTypes: FieldType[];
}

const props = defineProps<Props>();

// Form
const form = useForm<ReviewEvaluationFormData>({
    title: "",
    description: "",
    form_phase_id: 0,
    is_required: true,
    is_active: true,
    fields: [],
    _method: "PATCH",
});

// Constants
const fieldTypesRequiringOptions = ['select', 'radio', 'checkbox'];

// Methods
const generateTempId = (): string => `temp_${Date.now()}_${Math.random()}`;

const getFieldTypeName = (fieldTypeId: number | null): string => {
    const fieldType = props.fieldTypes.find(ft => ft.id === fieldTypeId);
    return fieldType?.name || '';
};

const fieldRequiresOptions = (fieldIndex: number): boolean => {
    const fieldTypeName = getFieldTypeName(form.fields[fieldIndex].field_type_id);
    return fieldTypesRequiringOptions.includes(fieldTypeName);
};

const addField = () => {
    form.fields.push({
        field_type_id: null,
        label: "",
        description: "",
        is_required: false,
        order: form.fields.length + 1,
        validation_rules: {},
        options: [],
        temp_id: generateTempId(),
    });
};

const removeField = (index: number) => {
    form.fields.splice(index, 1);
    // Reorder
    form.fields.forEach((field, idx) => {
        field.order = idx + 1;
    });
};

const addFieldOption = (fieldIndex: number) => {
    form.fields[fieldIndex].options.push({
        label: "",
        value: "",
        order: form.fields[fieldIndex].options.length + 1,
        temp_id: generateTempId(),
    });
};

const removeFieldOption = (fieldIndex: number, optionIndex: number) => {
    form.fields[fieldIndex].options.splice(optionIndex, 1);
    // Reorder options
    form.fields[fieldIndex].options.forEach((opt, idx) => {
        opt.order = idx + 1;
    });
};

const onFieldTypeChange = (fieldIndex: number) => {
    const field = form.fields[fieldIndex];

    // Clear options if field type doesn't require them
    if (!fieldRequiresOptions(fieldIndex)) {
        field.options = [];
    } else if (field.options.length === 0) {
        // Add default option if field type requires options
        addFieldOption(fieldIndex);
    }
};

const submit = () => {
    form.patch(route('admin.review-evaluation-forms.update', props.evaluationForm.id));
};

// Computed
const hasUnsavedChanges = computed(() => {
    return form.isDirty;
});

const hasError = (field: string): boolean => {
    return !!(form.errors as any)[field];
};

const getError = (field: string): string => {
    return (form.errors as any)[field] || '';
};

// Lifecycle
onMounted(() => {
    form.title = props.evaluationForm.title;
    form.description = props.evaluationForm.description || "";
    form.form_phase_id = props.evaluationForm.form_phase_id;
    form.is_required = props.evaluationForm.is_required;
    form.is_active = props.evaluationForm.is_active;

    // Convert existing fields
    form.fields = props.evaluationForm.review_form_fields.map(field => ({
        id: field.id,
        field_type_id: field.field_type?.id || null,
        label: field.label,
        description: field.description || "",
        is_required: field.is_required,
        order: field.order,
        validation_rules: field.validation_rules || {},
        options: (field.review_form_field_options || []).map(opt => ({
            id: opt.id,
            label: opt.label,
            value: opt.value || opt.label,
            order: opt.order,
            temp_id: generateTempId()
        })),
        temp_id: generateTempId()
    }));
});
</script>

<template>

    <Head :title="`Edit: ${evaluationForm.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="$inertia.visit(route('admin.review-evaluation-forms.index'))">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Back to Forms
                </Button>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Edit Review Evaluation Form
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ evaluationForm.form_phase.title }}
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Basic Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="title">Title *</Label>
                                <Input id="title" v-model="form.title" placeholder="Enter form title"
                                    :class="hasError('title') ? 'border-destructive' : ''" />
                                <p v-if="hasError('title')" class="text-sm text-destructive">
                                    {{ getError('title') }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="form_phase_id">Form Phase *</Label>
                                <Select v-model="form.form_phase_id">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select form phase" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="phase in formPhases" :key="phase.id" :value="phase.id">
                                            {{ phase.title }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="hasError('form_phase_id')" class="text-sm text-destructive">
                                    {{ getError('form_phase_id') }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="description">Description</Label>
                            <Textarea id="description" v-model="form.description"
                                placeholder="Enter form description (optional)" rows="3" />
                        </div>

                        <div class="flex items-center space-x-6">
                            <div class="flex items-center space-x-2">
                                <Switch v-model="form.is_required" id="is_required" />
                                <Label for="is_required">Required for reviewers</Label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch v-model="form.is_active" id="is_active" />
                                <Label for="is_active">Active</Label>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Form Fields -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle>Form Fields</CardTitle>
                            <Button type="button" @click="addField" size="sm">
                                <Plus class="h-4 w-4 mr-2" />
                                Add Field
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="form.fields.length === 0" class="text-center py-8 text-muted-foreground">
                            <p>No fields added yet. Click "Add Field" to get started.</p>
                        </div>

                        <div v-else class="space-y-4">
                            <draggable v-model="form.fields" item-key="temp_id" handle=".drag-handle" class="space-y-4"
                                :animation="200" @end="form.fields.forEach((field, idx) => field.order = idx + 1)">
                                <template #item="{ element: field, index }">
                                    <Card class="border-2 border-dashed">
                                        <CardContent class="pt-6">
                                            <div class="flex items-start gap-4">
                                                <!-- Drag Handle -->
                                                <div class="drag-handle cursor-move p-1 hover:bg-muted rounded">
                                                    <GripVertical class="h-4 w-4 text-muted-foreground" />
                                                </div>

                                                <!-- Field Content -->
                                                <div class="flex-1 space-y-4">
                                                    <div class="grid gap-4 md:grid-cols-2">
                                                        <div class="space-y-2">
                                                            <Label>Field Type *</Label>
                                                            <Select v-model="field.field_type_id"
                                                                @update:model-value="onFieldTypeChange(index)">
                                                                <SelectTrigger>
                                                                    <SelectValue placeholder="Select field type" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem v-for="fieldType in fieldTypes"
                                                                        :key="fieldType.id" :value="fieldType.id">
                                                                        {{ fieldType.name }}
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>

                                                        <div class="space-y-2">
                                                            <Label>Label *</Label>
                                                            <Input v-model="field.label"
                                                                placeholder="Enter field label" />
                                                        </div>
                                                    </div>

                                                    <div class="space-y-2">
                                                        <Label>Description</Label>
                                                        <Textarea v-model="field.description"
                                                            placeholder="Enter field description (optional)" rows="2" />
                                                    </div>

                                                    <div class="flex items-center space-x-2">
                                                        <Switch v-model="field.is_required" />
                                                        <Label>Required field</Label>
                                                    </div>

                                                    <!-- Field Options -->
                                                    <div v-if="fieldRequiresOptions(index)" class="space-y-2">
                                                        <div class="flex items-center justify-between">
                                                            <Label>Options</Label>
                                                            <Button type="button" variant="outline" size="sm"
                                                                @click="addFieldOption(index)">
                                                                <Plus class="h-3 w-3 mr-1" />
                                                                Add Option
                                                            </Button>
                                                        </div>

                                                        <div class="space-y-2">
                                                            <div v-for="(option, optionIndex) in field.options"
                                                                :key="option.temp_id" class="flex items-center gap-2">
                                                                <Input v-model="option.label" placeholder="Option label"
                                                                    class="flex-1" />
                                                                <Input v-model="option.value"
                                                                    placeholder="Option value (optional)"
                                                                    class="flex-1" />
                                                                <Button type="button" variant="ghost" size="sm"
                                                                    @click="removeFieldOption(index, optionIndex)"
                                                                    class="text-destructive">
                                                                    <Trash2 class="h-4 w-4" />
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Field Info -->
                                                    <div class="flex items-center gap-2">
                                                        <Badge variant="outline">
                                                            Order: {{ field.order }}
                                                        </Badge>
                                                        <Badge v-if="field.id" variant="secondary" class="text-xs">
                                                            Existing
                                                        </Badge>
                                                        <Badge v-else variant="default" class="text-xs">
                                                            New
                                                        </Badge>
                                                    </div>
                                                </div>

                                                <!-- Remove Field Button -->
                                                <Button type="button" variant="ghost" size="sm"
                                                    @click="removeField(index)"
                                                    class="text-destructive hover:text-destructive">
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </template>
                            </draggable>
                        </div>
                    </CardContent>
                </Card>

                <!-- Warning Card -->
                <Card v-if="evaluationForm.review_form_fields.length > 0" class="border-amber-200 bg-amber-50">
                    <CardContent class="p-4">
                        <div class="flex items-start gap-3">
                            <AlertTriangle class="h-5 w-5 text-amber-600 mt-0.5" />
                            <div>
                                <h4 class="text-amber-800 font-medium text-sm mb-1">
                                    Update Warning
                                </h4>
                                <p class="text-amber-700 text-sm">
                                    Modifying fields may affect existing draft responses. Completed submissions will not
                                    be
                                    affected.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Form Actions -->
                <div
                    class="flex items-center justify-end space-x-2 sticky bottom-4 bg-white border rounded-lg p-4 shadow-lg">
                    <div class="flex-1 text-sm text-muted-foreground">
                        <span v-if="hasUnsavedChanges" class="text-orange-600">You have unsaved changes</span>
                    </div>
                    <Button type="button" variant="outline"
                        @click="$inertia.visit(route('admin.review-evaluation-forms.index'))">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? "Updating..." : "Update Form" }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
