<!-- resources/js/Pages/Admin/ReviewEvaluationForms/Show.vue -->
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Separator } from '@/Components/ui/separator';
import {
    ArrowLeft,
    Edit,
    Eye,
    Copy,
    FileText,
    Users,
    ClipboardList,
    CheckCircle,
    AlertCircle
} from 'lucide-vue-next';

interface FieldType {
    id: number;
    name: string;
}

interface FieldOption {
    id: number;
    label: string;
    value?: string;
    order: number;
}

interface ReviewFormField {
    id: number;
    label: string;
    description?: string;
    is_required: boolean;
    order: number;
    field_type: FieldType;
    review_form_field_options: FieldOption[];
    validation_rules?: Record<string, any>;
}

interface FormPhase {
    id: number;
    title: string;
    description?: string;
}

interface ReviewEvaluationForm {
    id: number;
    title: string;
    description?: string;
    is_required: boolean;
    is_active: boolean;
    order: number;
    form_phase: FormPhase;
    review_form_fields: ReviewFormField[];
}

interface AssignmentStats {
    total_assignments: number;
    completed_responses: number;
    pending_responses: number;
}

interface Props {
    evaluationForm: ReviewEvaluationForm;
    assignmentStats: AssignmentStats;
}

const props = defineProps<Props>();

const getFieldTypeDisplayName = (typeName: string): string => {
    const typeMap: Record<string, string> = {
        'text': 'Text Input',
        'textarea': 'Text Area',
        'email': 'Email',
        'number': 'Number',
        'select': 'Dropdown',
        'radio': 'Radio Button',
        'checkbox': 'Checkbox',
        'date': 'Date',
        'url': 'URL'
    };
    return typeMap[typeName] || typeName;
};

const formatValidationRules = (rules?: Record<string, any>): string => {
    if (!rules || Object.keys(rules).length === 0) return 'None';

    const ruleStrings = [];
    if (rules.min_length) ruleStrings.push(`Min length: ${rules.min_length}`);
    if (rules.max_length) ruleStrings.push(`Max length: ${rules.max_length}`);
    if (rules.min_value) ruleStrings.push(`Min value: ${rules.min_value}`);
    if (rules.max_value) ruleStrings.push(`Max value: ${rules.max_value}`);

    return ruleStrings.join(', ') || 'None';
};
</script>

<template>

    <Head :title="`${evaluationForm.title} - Formulir Evaluasi Review`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="$inertia.visit(route('admin.review-evaluation-forms.index'))">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Kembali ke Daftar
                </Button>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ evaluationForm.title }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Formulir Evaluasi Review
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Form Information -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle>Informasi Formulir</CardTitle>
                        <div class="flex gap-2">
                            <Link :href="route('admin.review-evaluation-forms.preview', evaluationForm.id)">
                            <Button variant="outline" size="sm">
                                <Eye class="h-4 w-4 mr-2" />
                                Pratinjau
                            </Button>
                            </Link>
                            <Link :href="route('admin.review-evaluation-forms.edit', evaluationForm.id)">
                            <Button size="sm">
                                <Edit class="h-4 w-4 mr-2" />
                                Edit Formulir
                            </Button>
                            </Link>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-4">
                            <div>
                                <h3 class="font-medium text-sm text-muted-foreground">Judul</h3>
                                <p class="text-lg font-medium">{{ evaluationForm.title }}</p>
                            </div>

                            <div v-if="evaluationForm.description">
                                <h3 class="font-medium text-sm text-muted-foreground">Deskripsi</h3>
                                <p class="text-sm">{{ evaluationForm.description }}</p>
                            </div>

                            <div>
                                <h3 class="font-medium text-sm text-muted-foreground">Tahap Formulir</h3>
                                <div class="flex items-center gap-2">
                                    <FileText class="h-4 w-4" />
                                    <span>{{ evaluationForm.form_phase.title }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <h3 class="font-medium text-sm text-muted-foreground">Status & Pengaturan</h3>
                                <div class="flex flex-wrap gap-2 mt-2">
                                    <Badge :variant="evaluationForm.is_active ? 'default' : 'secondary'">
                                        {{ evaluationForm.is_active ? 'Aktif' : 'Tidak Aktif' }}
                                    </Badge>
                                    <Badge :variant="evaluationForm.is_required ? 'destructive' : 'outline'">
                                        {{ evaluationForm.is_required ? 'Wajib' : 'Opsional' }}
                                    </Badge>
                                    <Badge variant="outline">
                                        Urutan: {{ evaluationForm.order }}
                                    </Badge>
                                </div>
                            </div>

                            <div>
                                <h3 class="font-medium text-sm text-muted-foreground">Statistik Formulir</h3>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span>Total Isian:</span>
                                        <span class="font-medium">{{ evaluationForm.review_form_fields.length }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Isian Wajib:</span>
                                        <span class="font-medium">{{evaluationForm.review_form_fields.filter(f =>
                                            f.is_required).length}}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Usage Statistics -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-5 w-5" />
                        Statistik Penggunaan
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="text-center p-4 bg-blue-50 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ assignmentStats.total_assignments }}</div>
                            <div class="text-sm text-blue-700">Total Penugasan</div>
                        </div>
                        <div class="text-center p-4 bg-green-50 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">{{ assignmentStats.completed_responses }}
                            </div>
                            <div class="text-sm text-green-700">Respon Selesai</div>
                        </div>
                        <div class="text-center p-4 bg-orange-50 rounded-lg">
                            <div class="text-2xl font-bold text-orange-600">{{ assignmentStats.pending_responses }}
                            </div>
                            <div class="text-sm text-orange-700">Respon Tertunda</div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Form Fields -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <ClipboardList class="h-5 w-5" />
                        Isian Formulir ({{ evaluationForm.review_form_fields.length }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="evaluationForm.review_form_fields.length === 0"
                        class="text-center py-8 text-muted-foreground">
                        <ClipboardList class="h-12 w-12 mx-auto mb-4 opacity-50" />
                        <p>Tidak ada isian yang dikonfigurasi untuk formulir evaluasi ini.</p>
                    </div>

                    <div v-else class="space-y-6">
                        <div v-for="(field, index) in evaluationForm.review_form_fields" :key="field.id"
                            class="border rounded-lg p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <Badge variant="outline" class="text-xs">
                                        {{ field.order }}
                                    </Badge>
                                    <h3 class="font-medium">{{ field.label }}</h3>
                                    <Badge v-if="field.is_required" variant="destructive" class="text-xs">
                                        Wajib
                                    </Badge>
                                </div>
                                <Badge variant="secondary" class="text-xs">
                                    {{ getFieldTypeDisplayName(field.field_type.name) }}
                                </Badge>
                            </div>

                            <div v-if="field.description" class="text-sm text-muted-foreground mb-3">
                                {{ field.description }}
                            </div>

                            <div class="grid gap-4 md:grid-cols-2 text-sm">
                                <div>
                                    <span class="font-medium text-muted-foreground">Tipe Isian:</span>
                                    <span class="ml-2">{{ getFieldTypeDisplayName(field.field_type.name) }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-muted-foreground">Validasi:</span>
                                    <span class="ml-2">{{ formatValidationRules(field.validation_rules) }}</span>
                                </div>
                            </div>

                            <!-- Field Options -->
                            <div v-if="field.review_form_field_options.length > 0" class="mt-4 pt-4 border-t">
                                <h4 class="font-medium text-sm text-muted-foreground mb-2">Opsi:</h4>
                                <div class="grid gap-2 md:grid-cols-2">
                                    <div v-for="option in field.review_form_field_options" :key="option.id"
                                        class="flex items-center gap-2 text-sm">
                                        <Badge variant="outline" class="text-xs">
                                            {{ option.order }}
                                        </Badge>
                                        <span>{{ option.label }}</span>
                                        <span v-if="option.value && option.value !== option.label"
                                            class="text-muted-foreground">
                                            ({{ option.value }})
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Field Preview -->
                            <div class="mt-4 pt-4 border-t bg-muted/30 p-3 rounded">
                                <h4 class="font-medium text-sm text-muted-foreground mb-2">Pratinjau:</h4>
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{ field.label }}</span>
                                        <Badge v-if="field.is_required" variant="destructive" class="text-xs">
                                            Wajib
                                        </Badge>
                                    </div>

                                    <p v-if="field.description" class="text-sm text-muted-foreground">
                                        {{ field.description }}
                                    </p>

                                    <!-- Preview Input -->
                                    <div class="mt-2">
                                        <div v-if="field.field_type.name === 'textarea'"
                                            class="w-full p-2 border rounded bg-background text-muted-foreground text-sm">
                                            [Pratinjau textarea - {{ field.label }}]
                                        </div>
                                        <div v-else-if="['select', 'radio', 'checkbox'].includes(field.field_type.name) &&
                                            field.review_form_field_options.length > 0">
                                            <div class="space-y-1">
                                                <div v-for="option in field.review_form_field_options.slice(0, 3)"
                                                    :key="option.id" class="flex items-center space-x-2">
                                                    <input :type="field.field_type.name === 'checkbox' ? 'checkbox' :
                                                        field.field_type.name === 'radio' ? 'radio' : 'text'" disabled
                                                        class="h-3 w-3" />
                                                    <span class="text-sm">{{ option.label }}</span>
                                                </div>
                                                <div v-if="field.review_form_field_options.length > 3"
                                                    class="text-xs text-muted-foreground">
                                                    ... dan {{ field.review_form_field_options.length - 3 }} opsi lagi
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else
                                            class="w-full p-2 border rounded bg-background text-muted-foreground text-sm">
                                            [Pratinjau input {{ getFieldTypeDisplayName(field.field_type.name) }}]
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <Separator v-if="index < evaluationForm.review_form_fields.length - 1" class="mt-6" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Actions -->
            <Card>
                <CardContent class="p-6">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Terakhir diperbarui: {{ new Date().toLocaleDateString('id-ID') }}
                        </div>
                        <div class="flex gap-2">
                            <Button variant="outline"
                                @click="$inertia.post(route('admin.review-evaluation-forms.duplicate', evaluationForm.id))">
                                <Copy class="h-4 w-4 mr-2" />
                                Duplikat Formulir
                            </Button>
                            <Link :href="route('admin.review-evaluation-forms.edit', evaluationForm.id)">
                            <Button>
                                <Edit class="h-4 w-4 mr-2" />
                                Edit Formulir
                            </Button>
                            </Link>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
