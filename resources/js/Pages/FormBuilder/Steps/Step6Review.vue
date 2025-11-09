<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Separator } from '@/Components/ui/separator';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import {
    CheckCircle2,
    FileText,
    Users,
    Layers,
    Calendar,
    ClipboardList,
    AlertCircle,
    Shield,
    Building,
} from 'lucide-vue-next';

interface Props {
    formData: any;
    formTypes: any[];
    roles: any[];
    faculties: any[];
    phaseTypes: any[];
    formPhases: any[];
    submissionPeriods: any[];
}

const props = defineProps<Props>();

const getFormTypeName = (id: number | null) => {
    if (!id) return 'Not selected';
    return props.formTypes.find((ft) => ft.id === id)?.name || 'Unknown';
};

const getRoleName = (id: number) => {
    return props.roles.find((r) => r.id === id)?.name || 'Unknown';
};

const getStudyProgramInfo = (id: number) => {
    for (const faculty of props.faculties) {
        const sp = faculty.study_programs.find((s: any) => s.id === id);
        if (sp) return { name: sp.name, faculty: faculty.name };
    }
    return { name: 'Unknown', faculty: 'Unknown' };
};

const getPhaseTypeName = (id: number | null) => {
    if (!id) return 'Not selected';
    return props.phaseTypes.find((pt) => pt.id === id)?.name || 'Unknown';
};

const getPhaseName = (id: number | null) => {
    if (!id) return null;
    return props.formPhases.find((p) => p.id === id)?.title || 'Unknown';
};

const getPeriodName = (id: number | null) => {
    if (!id) return null;
    return props.submissionPeriods.find((p) => p.id === id)?.name || 'Unknown';
};

const validationIssues = computed(() => {
    const issues: string[] = [];

    if (!props.formData.form.title) issues.push('Form title is required');
    if (!props.formData.form.form_type_id) issues.push('Form type is required');
    if (props.formData.access_controls.length === 0) issues.push('At least one access control is required');
    if (!props.formData.phase.phase_type_id) issues.push('Phase type is required');

    if (props.formData.phase.use_existing) {
        if (!props.formData.phase.existing_phase_id) issues.push('Existing phase must be selected');
    } else {
        if (!props.formData.phase.new_phase_title) issues.push('New phase title is required');
    }

    if (props.formData.phase.needs_review && props.formData.evaluation_forms.length === 0) {
        issues.push('At least one evaluation form is required when review is enabled');
    }

    if (props.formData.submission_period.use_existing) {
        if (!props.formData.submission_period.existing_period_id) issues.push('Existing period must be selected');
    } else {
        if (!props.formData.submission_period.new_period_name) issues.push('New period name is required');
        if (props.formData.submission_period.dates.length === 0) issues.push('At least one submission date is required');
    }

    return issues;
});

const isValid = computed(() => validationIssues.value.length === 0);

const totalFields = computed(() => props.formData.form.fields.length);
const totalAccessControls = computed(() => props.formData.access_controls.length);
const totalEvaluationForms = computed(() => props.formData.evaluation_forms.length);
const totalEvaluationFields = computed(() =>
    props.formData.evaluation_forms.reduce((sum: number, form: any) => sum + form.fields.length, 0)
);
</script>

<template>
    <div class="space-y-6">
        <!-- Validation Status -->
        <Alert v-if="!isValid" variant="destructive">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>
                <div class="font-medium mb-2">Please fix the following issues:</div>
                <ul class="list-disc list-inside space-y-1">
                    <li v-for="(issue, index) in validationIssues" :key="index" class="text-sm">
                        {{ issue }}
                    </li>
                </ul>
            </AlertDescription>
        </Alert>

        <Alert v-else class="border-green-200 bg-green-50">
            <CheckCircle2 class="h-4 w-4 text-green-600" />
            <AlertDescription class="text-green-800">
                All required fields are filled. You're ready to create the form!
            </AlertDescription>
        </Alert>

        <!-- Summary Cards -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardContent class="p-6">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <FileText class="h-6 w-6 text-blue-600" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ totalFields }}</p>
                            <p class="text-sm text-muted-foreground">Form Fields</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-6">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <Shield class="h-6 w-6 text-purple-600" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ totalAccessControls }}</p>
                            <p class="text-sm text-muted-foreground">Access Controls</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-6">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <ClipboardList class="h-6 w-6 text-green-600" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ totalEvaluationForms }}</p>
                            <p class="text-sm text-muted-foreground">Evaluation Forms</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-6">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-orange-100 rounded-lg">
                            <CheckCircle2 class="h-6 w-6 text-orange-600" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ totalEvaluationFields }}</p>
                            <p class="text-sm text-muted-foreground">Evaluation Fields</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Step 1: Basic Form -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <FileText class="h-5 w-5" />
                    1. Basic Form Information
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <p class="text-sm text-muted-foreground">Title</p>
                        <p class="font-medium">{{ formData.form.title || 'Not set' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Type</p>
                        <Badge variant="outline">{{ getFormTypeName(formData.form.form_type_id) }}</Badge>
                    </div>
                </div>
                <div v-if="formData.form.description">
                    <p class="text-sm text-muted-foreground">Description</p>
                    <p class="text-sm">{{ formData.form.description }}</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Status</p>
                    <Badge :variant="formData.form.is_active ? 'default' : 'secondary'">
                        {{ formData.form.is_active ? 'Active' : 'Inactive' }}
                    </Badge>
                </div>
                <Separator />
                <div>
                    <p class="text-sm text-muted-foreground mb-2">Form Fields ({{ formData.form.fields.length }})</p>
                    <div v-if="formData.form.fields.length > 0" class="space-y-2">
                        <div v-for="(field, index) in formData.form.fields" :key="field.temp_id"
                            class="flex items-center justify-between p-2 bg-muted rounded text-sm">
                            <span>{{ index + 1 }}. {{ field.label }}</span>
                            <Badge v-if="field.is_required" variant="destructive" class="text-xs">Required</Badge>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">No fields added</p>
                </div>
            </CardContent>
        </Card>

        <!-- Step 2: Access Control -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="h-5 w-5" />
                    2. Access Controls
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="formData.access_controls.length > 0" class="space-y-2">
                    <div v-for="(control, index) in formData.access_controls" :key="control.temp_id"
                        class="flex items-center justify-between p-3 bg-muted rounded">
                        <div class="flex items-center gap-3">
                            <Badge variant="outline">{{ index + 1 }}</Badge>
                            <div class="flex items-center gap-2 text-sm">
                                <Users class="h-4 w-4 text-muted-foreground" />
                                <span class="font-medium">{{ getRoleName(control.role_id) }}</span>
                                <span class="text-muted-foreground">→</span>
                                <Building class="h-4 w-4 text-muted-foreground" />
                                <span>{{ getStudyProgramInfo(control.study_program_id).name }}</span>
                                <span class="text-xs text-muted-foreground">
                                    ({{ getStudyProgramInfo(control.study_program_id).faculty }})
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">No access controls configured</p>
            </CardContent>
        </Card>

        <!-- Step 3: Form Phase -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Layers class="h-5 w-5" />
                    3. Form Phase Configuration
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <p class="text-sm text-muted-foreground">Mode</p>
                        <Badge variant="outline">
                            {{ formData.phase.use_existing ? 'Using Existing Phase' : 'Creating New Phase' }}
                        </Badge>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Phase Type</p>
                        <Badge variant="outline">{{ getPhaseTypeName(formData.phase.phase_type_id) }}</Badge>
                    </div>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">
                        {{ formData.phase.use_existing ? 'Selected Phase' : 'New Phase Title' }}
                    </p>
                    <p class="font-medium">
                        {{ formData.phase.use_existing
                            ? getPhaseName(formData.phase.existing_phase_id) || 'Not selected'
                            : formData.phase.new_phase_title || 'Not set'
                        }}
                    </p>
                </div>
                <div v-if="!formData.phase.use_existing && formData.phase.new_phase_description">
                    <p class="text-sm text-muted-foreground">Description</p>
                    <p class="text-sm">{{ formData.phase.new_phase_description }}</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Needs Review</p>
                    <Badge :variant="formData.phase.needs_review ? 'default' : 'secondary'">
                        {{ formData.phase.needs_review ? 'Yes' : 'No' }}
                    </Badge>
                </div>
            </CardContent>
        </Card>

        <!-- Step 4: Review Settings -->
        <Card v-if="formData.phase.needs_review">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <ClipboardList class="h-5 w-5" />
                    4. Evaluation Forms
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="formData.evaluation_forms.length > 0" class="space-y-3">
                    <div v-for="(evalForm, index) in formData.evaluation_forms" :key="evalForm.temp_id"
                        class="p-4 border rounded-lg space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Badge variant="outline">{{ index + 1 }}</Badge>
                                <span class="font-medium">{{ evalForm.title }}</span>
                            </div>
                            <Badge :variant="evalForm.is_required ? 'destructive' : 'secondary'" class="text-xs">
                                {{ evalForm.is_required ? 'Required' : 'Optional' }}
                            </Badge>
                        </div>
                        <p v-if="evalForm.description" class="text-sm text-muted-foreground">{{ evalForm.description }}
                        </p>
                        <div class="text-sm text-muted-foreground">
                            {{ evalForm.fields.length }} field(s)
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">No evaluation forms configured</p>
            </CardContent>
        </Card>

        <!-- Step 5: Submission Period -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Calendar class="h-5 w-5" />
                    5. Submission Period
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div>
                    <p class="text-sm text-muted-foreground">Mode</p>
                    <Badge variant="outline">
                        {{ formData.submission_period.use_existing ? 'Using Existing Period' : 'Creating New Period' }}
                    </Badge>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">
                        {{ formData.submission_period.use_existing ? 'Selected Period' : 'New Period Name' }}
                    </p>
                    <p class="font-medium">
                        {{ formData.submission_period.use_existing
                            ? getPeriodName(formData.submission_period.existing_period_id) || 'Not selected'
                            : formData.submission_period.new_period_name || 'Not set'
                        }}
                    </p>
                </div>
                <div v-if="!formData.submission_period.use_existing && formData.submission_period.dates.length > 0">
                    <p class="text-sm text-muted-foreground mb-2">Configured Dates</p>
                    <div class="space-y-1">
                        <div v-for="date in formData.submission_period.dates" :key="date.temp_id"
                            class="text-sm flex items-center justify-between p-2 bg-muted rounded">
                            <span class="font-medium">{{ date.label }}</span>
                            <span class="text-muted-foreground">{{ date.date || 'Not set' }}</span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Final Note -->
        <Alert>
            <CheckCircle2 class="h-4 w-4" />
            <AlertDescription>
                <div class="font-medium mb-1">Ready to Create</div>
                <p class="text-sm">
                    Please review all information above. Once created, you can still edit individual components,
                    but it's easier to get it right the first time.
                </p>
            </AlertDescription>
        </Alert>
    </div>
</template>
