<!-- resources/js/Pages/FormBuilder/Steps/Step4ReviewSettings.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { Switch } from '@/Components/ui/switch';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Badge } from '@/Components/ui/badge';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Plus, Trash2, GripVertical, AlertCircle, ClipboardList } from 'lucide-vue-next';
import draggable from 'vuedraggable';

interface EvaluationFormField {
    field_type_id: number | null;
    label: string;
    description: string;
    is_required: boolean;
    validation_rules: Record<string, any>;
    options: Array<{ label: string; value: string; temp_id: string }>;
    temp_id: string;
}

interface EvaluationForm {
    title: string;
    description: string;
    is_required: boolean;
    fields: EvaluationFormField[];
    temp_id: string;
}

interface Props {
    modelValue: EvaluationForm[];
    needsReview: boolean;
    fieldTypes: any[];
    errors: Record<string, string>;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:modelValue': [value: EvaluationForm[]];
    'update:needsReview': [value: boolean];
}>();

const evaluationForms = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const needsReview = computed({
    get: () => props.needsReview,
    set: (value) => emit('update:needsReview', value),
});

const fieldTypesWithOptions = ['select', 'radio', 'checkbox'];

const generateTempId = () => `temp_${Date.now()}_${Math.random()}`;

const addEvaluationForm = () => {
    evaluationForms.value.push({
        title: '',
        description: '',
        is_required: true,
        fields: [],
        temp_id: generateTempId(),
    });
};

const removeEvaluationForm = (index: number) => {
    evaluationForms.value.splice(index, 1);
};

const addField = (formIndex: number) => {
    evaluationForms.value[formIndex].fields.push({
        field_type_id: null,
        label: '',
        description: '',
        is_required: false,
        validation_rules: {},
        options: [],
        temp_id: generateTempId(),
    });
};

const removeField = (formIndex: number, fieldIndex: number) => {
    evaluationForms.value[formIndex].fields.splice(fieldIndex, 1);
};

const addFieldOption = (formIndex: number, fieldIndex: number) => {
    evaluationForms.value[formIndex].fields[fieldIndex].options.push({
        label: '',
        value: '',
        temp_id: generateTempId(),
    });
};

const removeFieldOption = (formIndex: number, fieldIndex: number, optionIndex: number) => {
    evaluationForms.value[formIndex].fields[fieldIndex].options.splice(optionIndex, 1);
};

const getFieldTypeName = (fieldTypeId: number | null): string => {
    if (!fieldTypeId) return '';
    return props.fieldTypes.find((ft) => ft.id === fieldTypeId)?.name || '';
};

const fieldRequiresOptions = (fieldTypeId: number | null): boolean => {
    const typeName = getFieldTypeName(fieldTypeId);
    return fieldTypesWithOptions.includes(typeName);
};
</script>

<template>
    <div class="space-y-6">
        <!-- Review Toggle -->
        <Card>
            <CardContent class="p-6">
                <div class="flex items-center space-x-4">
                    <Switch v-model="needsReview" id="enable_review" />
                    <Label for="enable_review" class="flex-1">
                        <div class="font-medium text-base">Enable Review & Evaluation</div>
                        <p class="text-sm text-muted-foreground">
                            Require reviewers to complete evaluation forms before approving submissions
                        </p>
                    </Label>
                </div>
            </CardContent>
        </Card>

        <!-- No Review Message -->
        <Alert v-if="!needsReview">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>
                Review is disabled. Submissions will not require evaluation forms.
                You can skip to the next step.
            </AlertDescription>
        </Alert>

        <!-- Evaluation Forms Section -->
        <template v-if="needsReview">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                Evaluation Forms
                                <Badge variant="secondary">{{ evaluationForms.length }}</Badge>
                            </CardTitle>
                            <CardDescription>
                                Create forms that reviewers must complete when evaluating submissions
                            </CardDescription>
                        </div>
                        <Button type="button" @click="addEvaluationForm" size="sm">
                            <Plus class="h-4 w-4 mr-2" />
                            Add Form
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="evaluationForms.length === 0" class="text-center py-12">
                        <ClipboardList class="h-12 w-12 mx-auto text-muted-foreground mb-4 opacity-50" />
                        <h3 class="text-lg font-medium mb-2">No Evaluation Forms</h3>
                        <p class="text-muted-foreground mb-4">
                            Add at least one evaluation form for reviewers to complete
                        </p>
                        <Button type="button" @click="addEvaluationForm">
                            <Plus class="h-4 w-4 mr-2" />
                            Add First Form
                        </Button>
                    </div>

                    <div v-else class="space-y-6">
                        <draggable v-model="evaluationForms" item-key="temp_id" handle=".drag-handle" class="space-y-6"
                            :animation="200">
                            <template #item="{ element: evalForm, index: formIndex }">
                                <Card class="border-2">
                                    <CardHeader class="bg-muted/30">
                                        <div class="flex items-start gap-4">
                                            <div class="drag-handle cursor-move p-1 hover:bg-muted rounded mt-1">
                                                <GripVertical class="h-4 w-4 text-muted-foreground" />
                                            </div>

                                            <div class="flex-1 space-y-4">
                                                <div class="grid gap-4 md:grid-cols-2">
                                                    <div class="space-y-2">
                                                        <Label>Form Title *</Label>
                                                        <Input v-model="evalForm.title"
                                                            placeholder="Enter evaluation form title" />
                                                    </div>

                                                    <div class="flex items-center space-x-2 pt-6">
                                                        <Switch v-model="evalForm.is_required"
                                                            :id="`form_required_${formIndex}`" />
                                                        <Label :for="`form_required_${formIndex}`">Required for all
                                                            reviewers</Label>
                                                    </div>
                                                </div>

                                                <div class="space-y-2">
                                                    <Label>Description</Label>
                                                    <Textarea v-model="evalForm.description"
                                                        placeholder="Enter form description (optional)" rows="2" />
                                                </div>
                                            </div>

                                            <Button type="button" variant="ghost" size="sm"
                                                @click="removeEvaluationForm(formIndex)" class="text-destructive">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </CardHeader>

                                    <CardContent class="pt-6">
                                        <!-- Evaluation Form Fields -->
                                        <div class="space-y-4">
                                            <div class="flex items-center justify-between">
                                                <Label class="text-base font-semibold">Form Fields</Label>
                                                <Button type="button" @click="addField(formIndex)" size="sm"
                                                    variant="outline">
                                                    <Plus class="h-3 w-3 mr-2" />
                                                    Add Field
                                                </Button>
                                            </div>

                                            <div v-if="evalForm.fields.length === 0"
                                                class="text-center py-6 text-muted-foreground border-2 border-dashed rounded-lg">
                                                <p>No fields added. Click "Add Field" to create evaluation criteria.</p>
                                            </div>

                                            <div v-else class="space-y-3">
                                                <Card v-for="(field, fieldIndex) in evalForm.fields"
                                                    :key="field.temp_id" class="border">
                                                    <CardContent class="p-4">
                                                        <div class="flex items-start gap-3">
                                                            <div class="flex-1 space-y-3">
                                                                <div class="grid gap-3 md:grid-cols-2">
                                                                    <div class="space-y-2">
                                                                        <Label class="text-sm">Field Type *</Label>
                                                                        <Select v-model="field.field_type_id">
                                                                            <SelectTrigger class="h-9">
                                                                                <SelectValue
                                                                                    placeholder="Select type" />
                                                                            </SelectTrigger>
                                                                            <SelectContent>
                                                                                <SelectItem v-for="ft in fieldTypes"
                                                                                    :key="ft.id" :value="ft.id">
                                                                                    {{ ft.name }}
                                                                                </SelectItem>
                                                                            </SelectContent>
                                                                        </Select>
                                                                    </div>

                                                                    <div class="space-y-2">
                                                                        <Label class="text-sm">Label *</Label>
                                                                        <Input v-model="field.label"
                                                                            placeholder="Field label" class="h-9" />
                                                                    </div>
                                                                </div>

                                                                <div class="space-y-2">
                                                                    <Label class="text-sm">Description</Label>
                                                                    <Input v-model="field.description"
                                                                        placeholder="Optional field description"
                                                                        class="h-9" />
                                                                </div>

                                                                <div class="flex items-center space-x-2">
                                                                    <Switch v-model="field.is_required"
                                                                        :id="`field_required_${formIndex}_${fieldIndex}`" />
                                                                    <Label
                                                                        :for="`field_required_${formIndex}_${fieldIndex}`"
                                                                        class="text-sm">
                                                                        Required field
                                                                    </Label>
                                                                </div>

                                                                <!-- Field Options -->
                                                                <div v-if="fieldRequiresOptions(field.field_type_id)"
                                                                    class="space-y-2">
                                                                    <div class="flex items-center justify-between">
                                                                        <Label class="text-sm">Options</Label>
                                                                        <Button type="button" size="sm" variant="ghost"
                                                                            @click="addFieldOption(formIndex, fieldIndex)">
                                                                            <Plus class="h-3 w-3 mr-1" />
                                                                            Add
                                                                        </Button>
                                                                    </div>
                                                                    <div class="space-y-2">
                                                                        <div v-for="(option, optionIndex) in field.options"
                                                                            :key="option.temp_id" class="flex gap-2">
                                                                            <Input v-model="option.label"
                                                                                placeholder="Option label"
                                                                                class="h-8" />
                                                                            <Input v-model="option.value"
                                                                                placeholder="Value (optional)"
                                                                                class="h-8" />
                                                                            <Button type="button" variant="ghost"
                                                                                size="sm"
                                                                                @click="removeFieldOption(formIndex, fieldIndex, optionIndex)">
                                                                                <Trash2
                                                                                    class="h-3 w-3 text-destructive" />
                                                                            </Button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <Button type="button" variant="ghost" size="sm"
                                                                @click="removeField(formIndex, fieldIndex)"
                                                                class="text-destructive">
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
