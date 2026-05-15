<!-- resources/js/Pages/Admin/ReviewEvaluationForms/Create.vue -->
<script setup lang="ts">
import { computed, ref } from "vue";
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
import { Separator } from "@/Components/ui/separator";
import { Plus, Trash2, GripVertical, ArrowLeft, Eye } from "lucide-vue-next";
import draggable from "vuedraggable";

interface FormPhase {
    id: number;
    title: string;
    form_phase_details: FormPhaseDetail[];
}

interface FormPhaseDetail {
    id: number;
    order: number;
    form_access_control: {
        form: {
            id: number;
            title: string;
        };
        role: {
            id: number;
            name: string;
        };
        study_program: {
            id: number;
            name: string;
            faculty: {
                name: string;
            };
        };
    };
}

interface FieldType {
    id: number;
    name: string;
}

interface FieldOption {
    label: string;
    value?: string;
    temp_id?: string;
}

type ValidationRuleKey = "min_length" | "max_length" | "min_value" | "max_value";

interface ValidationRule {
    min_length?: number;
    max_length?: number;
    min_value?: number;
    max_value?: number;
}

interface EvaluationFormField {
    field_type_id: number | null;
    label: string;
    description: string;
    is_required: boolean;
    validation_rules: ValidationRule;
    options: FieldOption[];
    temp_id: string;
}

interface FormData {
    title: string;
    description: string;
    form_phase_detail_id: number | null;
    is_required: boolean;
    is_active: boolean;
    fields: EvaluationFormField[];
    [key: string]: any;
}

interface Props {
    formPhases: FormPhase[];
    fieldTypes: FieldType[];
}

const props = defineProps<Props>();

const form = useForm<FormData>({
    title: "",
    description: "",
    form_phase_detail_id: null,  // ✅ New
    is_required: true,
    is_active: true,
    fields: [],
});

const errors = computed(() => form.errors as Record<string, string> ?? {});

const fieldTypesWithOptions = ["select", "radio", "checkbox"];

const generateTempId = () => `temp_${Date.now()}_${Math.random()}`;

const addField = () => {
    form.fields.push({
        field_type_id: null,
        label: "",
        description: "",
        is_required: false,
        validation_rules: {},
        options: [],
        temp_id: generateTempId(),
    });
};

const removeField = (index: number) => {
    form.fields.splice(index, 1);
};

const addOption = (fieldIndex: number) => {
    form.fields[fieldIndex].options.push({
        label: "",
        value: "",
        temp_id: generateTempId(),
    });
};

const removeOption = (fieldIndex: number, optionIndex: number) => {
    form.fields[fieldIndex].options.splice(optionIndex, 1);
};

const getFieldTypeName = (fieldTypeId: number | null): string => {
    if (!fieldTypeId) return "";
    const fieldType = props.fieldTypes.find((ft) => ft.id === fieldTypeId);
    return fieldType?.name || "";
};

const fieldTypeRequiresOptions = (fieldTypeId: number | null): boolean => {
    if (!fieldTypeId) return false;
    const fieldTypeName = getFieldTypeName(fieldTypeId);
    return fieldTypesWithOptions.includes(fieldTypeName);
};

const isNumericField = (fieldTypeId: number | null): boolean => {
    const fieldTypeName = getFieldTypeName(fieldTypeId);
    return fieldTypeName === "number";
};

const isTextualField = (fieldTypeId: number | null): boolean => {
    const fieldTypeName = getFieldTypeName(fieldTypeId);
    return ["text", "textarea", "email", "url"].includes(fieldTypeName);
};

const addValidationRule = (fieldIndex: number, rule: ValidationRuleKey, value: number) => {
    if (!form.fields[fieldIndex].validation_rules) {
        form.fields[fieldIndex].validation_rules = {};
    }
    form.fields[fieldIndex].validation_rules[rule] = value;
};

const removeValidationRule = (fieldIndex: number, rule: ValidationRuleKey) => {
    if (form.fields[fieldIndex].validation_rules) {
        delete form.fields[fieldIndex].validation_rules[rule];
    }
};

const submit = () => {
    form.post(route("admin.review-evaluation-forms.store"));
};

const previewForm = () => {
    // This would open a preview modal or navigate to preview page
};
</script>

<template>

    <Head title="Buat Formulir Evaluasi Review" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="$inertia.visit(route('admin.review-evaluation-forms.index'))">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Kembali
                </Button>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Buat Formulir Evaluasi Review
                </h2>
            </div>
        </template>

        <div class="max-w-6xl mx-auto space-y-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Form Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Informasi Formulir</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid gap-6 md:grid-cols-2">
                            <!-- Form Title -->
                            <div class="space-y-2">
                                <Label for="title">Judul Form *</Label>
                                <Input id="title" v-model="form.title" placeholder="Masukkan judul formulir evaluasi"
                                    :class="errors.title ? 'border-destructive' : ''" />
                                <p v-if="errors.title" class="text-sm text-destructive">
                                    {{ errors.title }}
                                </p>
                            </div>

                            <!-- Form Phase -->
                            <div class="space-y-2">
                                <Label for="form_phase_detail_id">Pilih Tahap Form *</Label>
                                <Select v-model="form.form_phase_detail_id">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih detail tahap formulir" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <template v-for="phase in formPhases" :key="phase.id">
                                            <div
                                                class="px-2 py-1.5 text-sm font-semibold text-muted-foreground bg-muted">
                                                {{ phase.title }}
                                            </div>
                                            <SelectItem v-for="detail in phase.form_phase_details" :key="detail.id"
                                                :value="detail.id" class="pl-6">
                                                <div class="flex flex-col">
                                                    <span class="font-medium">{{ detail.form_access_control.form.title
                                                    }}</span>
                                                    <span class="text-xs text-muted-foreground">
                                                        {{ detail.form_access_control.role.name }} -
                                                        {{ detail.form_access_control.study_program.name }}
                                                    </span>
                                                </div>
                                            </SelectItem>
                                        </template>
                                    </SelectContent>
                                </Select>
                                <p v-if="errors.form_phase_detail_id" class="text-sm text-destructive">
                                    {{ errors.form_phase_detail_id }}
                                </p>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="space-y-2">
                            <Label for="description">Deskripsi</Label>
                            <Textarea id="description" v-model="form.description"
                                placeholder="Masukkan deskripsi formulir (opsional)" rows="3" />
                        </div>

                        <!-- Form Settings -->
                        <div class="flex items-center gap-6">
                            <div class="flex items-center space-x-2">
                                <Switch v-model="form.is_required" id="is_required" />
                                <Label for="is_required">Formulir Wajib</Label>
                            </div>
                            <div class="flex items-center space-x-2">
                                <Switch v-model="form.is_active" id="is_active" />
                                <Label for="is_active">Aktif</Label>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Form Fields -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle>Isian Evaluasi</CardTitle>
                            <div class="flex gap-2">
                                <Button v-if="form.fields.length === 0" type="button" @click="addField" size="sm">
                                    <Plus class="h-4 w-4 mr-2" />
                                    Tambah Isian
                                </Button>
                                <Button type="button" @click="previewForm" variant="outline" size="sm"
                                    :disabled="form.fields.length === 0">
                                    <Eye class="h-4 w-4 mr-2" />
                                    Pratinjau
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="form.fields.length === 0" class="text-center py-8 text-muted-foreground">
                            <p>Belum ada isian evaluasi yang ditambahkan. Klik "Tambah Isian" untuk memulai.</p>
                        </div>

                        <div v-else class="space-y-6">
                            <draggable v-model="form.fields" item-key="temp_id" handle=".drag-handle" class="space-y-6"
                                :animation="200">
                                <template #item="{ element: field, index }">
                                    <Card class="border-2 border-dashed">
                                        <CardContent class="pt-6">
                                            <div class="flex items-start gap-4">
                                                <div class="drag-handle cursor-move p-1 hover:bg-muted rounded">
                                                    <GripVertical class="h-4 w-4 text-muted-foreground" />
                                                </div>

                                                <div class="flex-1 space-y-4">
                                                    <!-- Field Basic Info -->
                                                    <div class="grid gap-4 md:grid-cols-2">
                                                        <div class="space-y-2">
                                                            <Label>Tipe Isian *</Label>
                                                            <Select v-model="field.field_type_id">
                                                                <SelectTrigger>
                                                                    <SelectValue placeholder="Pilih tipe isian" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem v-for="fieldType in props.fieldTypes"
                                                                        :key="fieldType.id" :value="fieldType.id">
                                                                        {{ fieldType.name }}
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>

                                                        <div class="space-y-2">
                                                            <Label>Label Isian *</Label>
                                                            <Input v-model="field.label"
                                                                placeholder="Masukkan label isian" />
                                                        </div>
                                                    </div>

                                                    <!-- Field Description -->
                                                    <div class="space-y-2">
                                                        <Label>Deskripsi Isian</Label>
                                                        <Textarea v-model="field.description"
                                                            placeholder="Deskripsi opsional untuk membantu penilai memahami isian ini"
                                                            rows="2" />
                                                    </div>

                                                    <!-- Field Settings -->
                                                    <div class="flex items-center space-x-2">
                                                        <Switch v-model="field.is_required" :id="`required_${index}`" />
                                                        <Label :for="`required_${index}`">Isian Wajib</Label>
                                                    </div>

                                                    <!-- Validation Rules -->
                                                    <div v-if="isTextualField(field.field_type_id) || isNumericField(field.field_type_id)"
                                                        class="space-y-3">
                                                        <Label class="text-sm font-medium">Aturan Validasi</Label>

                                                        <!-- Text field validation -->
                                                        <div v-if="isTextualField(field.field_type_id)"
                                                            class="grid gap-3 md:grid-cols-2">
                                                            <div class="space-y-2">
                                                                <Label class="text-xs">Panjang Minimum</Label>
                                                                <Input type="number"
                                                                    :value="field.validation_rules.min_length || ''"
                                                                    @input="addValidationRule(index, 'min_length' as const,
                                                                        $event.target.value ? parseInt(($event.target as HTMLInputElement).value) : 0)"
                                                                    placeholder="0" min="0" />
                                                            </div>
                                                            <div class="space-y-2">
                                                                <Label class="text-xs">Panjang Maksimum</Label>
                                                                <Input type="number"
                                                                    :value="field.validation_rules.max_length || ''"
                                                                    @input="addValidationRule(index, 'max_length' as const,
                                                                        $event.target.value ? parseInt(($event.target as HTMLInputElement).value) : 0)"
                                                                    placeholder="0" min="0" />
                                                            </div>
                                                        </div>

                                                        <!-- Numeric field validation -->
                                                        <div v-if="isNumericField(field.field_type_id)"
                                                            class="grid gap-3 md:grid-cols-2">
                                                            <div class="space-y-2">
                                                                <Label class="text-xs">Nilai Minimum</Label>
                                                                <Input type="number"
                                                                    :value="field.validation_rules.min_value || ''"
                                                                    @input="addValidationRule(index, 'min_value' as const,
                                                                        $event.target.value ? parseInt(($event.target as HTMLInputElement).value) : 0)"
                                                                    placeholder="0" min="0" />
                                                            </div>
                                                            <div class="space-y-2">
                                                                <Label class="text-xs">Nilai Maksimum</Label>
                                                                <Input type="number"
                                                                    :value="field.validation_rules.max_value || ''"
                                                                    @input="addValidationRule(index, 'max_value' as const,
                                                                        $event.target.value ? parseInt(($event.target as HTMLInputElement).value) : 0)"
                                                                    placeholder="0" min="0" />
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Field Options -->
                                                    <div v-if="fieldTypeRequiresOptions(field.field_type_id)"
                                                        class="space-y-3">
                                                        <div class="flex items-center justify-between">
                                                            <Label class="text-sm font-medium">Options</Label>
                                                            <Button type="button" size="sm" variant="outline"
                                                                @click="addOption(index)">
                                                                <Plus class="h-3 w-3 mr-1" />
                                                                Tambah Opsi
                                                            </Button>
                                                        </div>

                                                        <div v-if="field.options.length === 0"
                                                            class="text-sm text-muted-foreground">
                                                            Belum ada opsi yang ditambahkan.
                                                        </div>

                                                        <div v-else class="space-y-2">
                                                            <div v-for="(option, optionIndex) in field.options"
                                                                :key="option.temp_id || optionIndex"
                                                                class="flex items-center gap-2">
                                                                <Input v-model="option.label" placeholder="Option label"
                                                                    class="flex-1" />
                                                                <Input v-model="option.value"
                                                                    placeholder="Custom value (optional)"
                                                                    class="flex-1" />
                                                                <Button type="button" variant="ghost" size="sm"
                                                                    @click="removeOption(index, optionIndex as number)">
                                                                    <Trash2 class="h-4 w-4 text-destructive" />
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Field Preview -->
                                                    <div v-if="field.field_type_id && field.label"
                                                        class="mt-4 p-4 bg-muted/50 rounded-lg">
                                                        <Label
                                                            class="text-sm text-muted-foreground mb-2 block">Pratinjau:</Label>
                                                        <div class="space-y-2">
                                                            <div class="flex items-center gap-2">
                                                                <Label class="font-medium">{{ field.label }}</Label>
                                                                <Badge v-if="field.is_required" variant="destructive"
                                                                    class="text-xs">Wajib</Badge>
                                                            </div>

                                                            <p v-if="field.description"
                                                                class="text-sm text-muted-foreground">
                                                                {{ field.description }}
                                                            </p>

                                                            <!-- Preview based on field type -->
                                                            <div class="mt-2">
                                                                <div
                                                                    v-if="getFieldTypeName(field.field_type_id) === 'textarea'">
                                                                    <Textarea placeholder="Ini adalah pratinjau" disabled
                                                                        rows="3" />
                                                                </div>
                                                                <div v-else-if="fieldTypeRequiresOptions(field.field_type_id) &&
                                                                    field.options.length > 0">
                                                                    <div class="space-y-2">
                                                                        <div v-for="option in field.options"
                                                                            :key="option.temp_id"
                                                                            class="flex items-center space-x-2">
                                                                            <input :type="getFieldTypeName(field.field_type_id) === 'checkbox'
                                                                                ? 'checkbox' : 'radio'" disabled
                                                                                class="h-4 w-4" />
                                                                            <span class="text-sm">{{ option.label
                                                                            }}</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div v-else>
                                                                    <Input :type="getFieldTypeName(field.field_type_id)"
                                                                        placeholder="Ini adalah pratinjau" disabled />
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

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

                            <!-- Add Another Field Button -->
                            <div class="flex justify-center pt-4">
                                <Button type="button" @click="addField" size="sm" class="w-full max-w-xs">
                                    <Plus class="h-4 w-4 mr-2" />
                                    Tambah Isian Lain
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-2">
                    <Button type="button" variant="outline"
                        @click="$inertia.visit(route('admin.review-evaluation-forms.index'))">
                        Batal
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? "Membuat..." : "Buat Formulir Evaluasi" }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
