<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import type { InertiaForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import Checkbox from "@/Components/ui/checkbox/Checkbox.vue";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import { Badge } from "@/Components/ui/badge";
import { Separator } from "@/Components/ui/separator";
import { ArrowLeft, Users, Building, FileText, Plus } from "lucide-vue-next";

interface Role {
    id: number;
    name: string;
}

interface StudyProgram {
    id: number;
    name: string;
    faculty_id: number;
}

interface Faculty {
    id: number;
    name: string;
    study_programs: StudyProgram[];
}

interface Form {
    id: number;
    title: string;
}

interface FormData {
    form_id: number | null;
    role_id: number | null;
    study_program_id: number | null;
    [key: string]: any; // Add index signature
}

interface BulkFormData {
    form_id: number | null;
    combinations: Array<{
        role_id: number;
        study_program_id: number;
    }>;
    [key: string]: any; // Add index signature
}

// Extended error interface to include custom error fields
interface FormErrors {
    form_id?: string;
    role_id?: string;
    study_program_id?: string;
    duplicate?: string;
    [key: string]: string | undefined;
}

interface Props {
    forms: Form[];
    roles: Role[];
    faculties: Faculty[];
}

const props = defineProps<Props>();

const isBulkMode = ref(false);
const selectedFacultyId = ref<number | null>(null);
const selectedRoles = ref<number[]>([]);
const selectedStudyPrograms = ref<number[]>([]);

// Single form - explicitly type the form
const form: InertiaForm<FormData> = useForm<FormData>({
    form_id: null,
    role_id: null,
    study_program_id: null,
});

// Bulk form - explicitly type the form
const bulkForm: InertiaForm<BulkFormData> = useForm<BulkFormData>({
    form_id: null,
    combinations: [],
});

// Create a computed property for errors with proper typing
const errors = computed((): FormErrors => {
    return isBulkMode.value
        ? (bulkForm.errors as FormErrors)
        : (form.errors as FormErrors);
});

const studyPrograms = computed(() => {
    if (!selectedFacultyId.value) return [];
    const faculty = props.faculties.find(
        (f) => f.id === selectedFacultyId.value
    );
    return faculty?.study_programs || [];
});

// Watch for faculty change to reset study programs
watch(selectedFacultyId, () => {
    selectedStudyPrograms.value = [];
    if (!isBulkMode.value) {
        form.study_program_id = null;
    }
});

// Generate combinations for bulk create
const generateCombinations = () => {
    const combinations: Array<{ role_id: number; study_program_id: number }> =
        [];

    selectedRoles.value.forEach((roleId) => {
        selectedStudyPrograms.value.forEach((studyProgramId) => {
            combinations.push({
                role_id: roleId,
                study_program_id: studyProgramId,
            });
        });
    });

    return combinations;
};

const previewCombinations = computed(() => {
    if (!isBulkMode.value || !bulkForm.form_id) return [];

    const combinations = generateCombinations();
    return combinations.map((combo) => {
        const role = props.roles.find((r) => r.id === combo.role_id);
        const studyProgram = studyPrograms.value.find(
            (sp) => sp.id === combo.study_program_id
        );
        const faculty = props.faculties.find(
            (f) => f.id === selectedFacultyId.value
        );

        return {
            role: role?.name || "",
            study_program: studyProgram?.name || "",
            faculty: faculty?.name || "",
        };
    });
});

const submit = () => {
    if (isBulkMode.value) {
        bulkForm.combinations = generateCombinations();
        bulkForm.post(route("admin.form-access-controls.bulk-create"));
    } else {
        form.post(route("admin.form-access-controls.store"));
    }
};

const toggleRole: (roleId: number, checked?: boolean | 'indeterminate') => void =
    (roleId, checked) => {
    const isCurrentlySelected = selectedRoles.value.includes(roleId)

    const shouldBeChecked =
        checked === 'indeterminate'
        ? true
        : typeof checked === 'boolean'
            ? checked
            : !isCurrentlySelected

    if (shouldBeChecked) {
        if (!isCurrentlySelected) selectedRoles.value.push(roleId)
    } else {
        selectedRoles.value = selectedRoles.value.filter(id => id !== roleId)
    }
}

const toggleStudyProgram: (studyProgramId: number, checked?: boolean | 'indeterminate') => void =
    (studyProgramId, checked) => {
    const isCurrentlySelected = selectedStudyPrograms.value.includes(studyProgramId)

    const shouldBeChecked =
        checked === 'indeterminate'
        ? true
        : typeof checked === 'boolean'
            ? checked
            : !isCurrentlySelected
    if (shouldBeChecked) {
        if (!isCurrentlySelected) selectedStudyPrograms.value.push(studyProgramId)
    } else {
        selectedStudyPrograms.value = selectedStudyPrograms.value.filter(id => id !== studyProgramId)
    }
}


const selectAllRoles = () => {
    if (selectedRoles.value.length === props.roles.length) {
        selectedRoles.value = [];
    } else {
        selectedRoles.value = props.roles.map((r) => r.id);
    }
};

const selectAllStudyPrograms = () => {
    if (selectedStudyPrograms.value.length === studyPrograms.value.length) {
        selectedStudyPrograms.value = [];
    } else {
        selectedStudyPrograms.value = studyPrograms.value.map((sp) => sp.id);
    }
};

const switchMode = () => {
    isBulkMode.value = !isBulkMode.value;

    // Reset forms when switching modes
    form.reset();
    bulkForm.reset();
    selectedFacultyId.value = null;
    selectedRoles.value = [];
    selectedStudyPrograms.value = [];
};

// Helper computed properties for form values to avoid v-model issues
const currentFormId = computed({
    get: () => (isBulkMode.value ? bulkForm.form_id : form.form_id),
    set: (value: number | null) => {
        if (isBulkMode.value) {
            bulkForm.form_id = value;
        } else {
            form.form_id = value;
        }
    },
});
</script>

<template>

    <Head title="Create Form Access Control" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="$inertia.visit(route('admin.form-access-controls.index'))">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Back
                </Button>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Create Form Access Control
                </h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Mode Switcher -->
            <Card>
                <CardContent class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-medium mb-1">Creation Mode</h3>
                            <p class="text-sm text-muted-foreground">
                                Choose between single or bulk creation
                            </p>
                        </div>
                        <Button @click="switchMode" variant="outline">
                            {{
                                isBulkMode
                                    ? "Switch to Single Mode"
                                    : "Switch to Bulk Mode"
                            }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Form Selection -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <FileText class="h-5 w-5" />
                            Form Selection
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="form">Form *</Label>
                            <Select v-model="currentFormId">
                                <SelectTrigger id="form">
                                    <SelectValue placeholder="Select a form" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="formItem in props.forms" :key="formItem.id" :value="formItem.id">
                                        {{ formItem.title }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="errors.form_id" class="text-sm text-destructive">
                                {{ errors.form_id }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Single Mode -->
                <template v-if="!isBulkMode">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Users class="h-5 w-5" />
                                Access Configuration
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="grid gap-6 md:grid-cols-2">
                                <!-- Role Selection -->
                                <div class="space-y-2">
                                    <Label for="role">Role *</Label>
                                    <Select v-model="form.role_id">
                                        <SelectTrigger id="role">
                                            <SelectValue placeholder="Select a role" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="role in props.roles" :key="role.id" :value="role.id">
                                                {{ role.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p v-if="errors.role_id" class="text-sm text-destructive">
                                        {{ errors.role_id }}
                                    </p>
                                </div>

                                <!-- Faculty Selection -->
                                <div class="space-y-2">
                                    <Label for="faculty">Faculty *</Label>
                                    <Select v-model="selectedFacultyId">
                                        <SelectTrigger id="faculty">
                                            <SelectValue placeholder="Select a faculty" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="faculty in props.faculties" :key="faculty.id"
                                                :value="faculty.id">
                                                {{ faculty.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <!-- Study Program Selection -->
                            <div class="space-y-2">
                                <Label for="study_program">Study Program *</Label>
                                <Select v-model="form.study_program_id" :disabled="!selectedFacultyId">
                                    <SelectTrigger id="study_program">
                                        <SelectValue placeholder="Select a study program" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="studyProgram in studyPrograms" :key="studyProgram.id"
                                            :value="studyProgram.id">
                                            {{ studyProgram.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="errors.study_program_id" class="text-sm text-destructive">
                                    {{ errors.study_program_id }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </template>

                <!-- Bulk Mode -->
                <template v-else>
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Building class="h-5 w-5" />
                                Faculty & Study Programs
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Faculty Selection -->
                            <div class="space-y-2">
                                <Label for="faculty_bulk">Faculty *</Label>
                                <Select v-model="selectedFacultyId">
                                    <SelectTrigger id="faculty_bulk">
                                        <SelectValue placeholder="Select a faculty" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="faculty in props.faculties" :key="faculty.id"
                                            :value="faculty.id">
                                            {{ faculty.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <!-- Study Programs Multi-select -->
                            <div v-if="selectedFacultyId" class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <Label>Study Programs *</Label>
                                    <Button type="button" variant="outline" size="sm" @click="selectAllStudyPrograms">
                                        {{
                                            selectedStudyPrograms.length ===
                                                studyPrograms.length
                                                ? "Deselect All"
                                                : "Select All"
                                        }}
                                    </Button>
                                </div>
                                <div class="grid gap-2 md:grid-cols-2 max-h-48 overflow-y-auto p-2 border rounded">
                                    <div v-for="studyProgram in studyPrograms" :key="studyProgram.id"
                                        class="flex items-center space-x-2">
                                        <Checkbox
                                            :model-value="selectedStudyPrograms.includes(studyProgram.id)"
                                            @update:modelValue="(val) => toggleStudyProgram(studyProgram.id, val)"
                                        />
                                        <Label class="text-sm cursor-pointer" @click="toggleStudyProgram(studyProgram.id)">
                                            {{ studyProgram.name }}
                                        </Label>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Users class="h-5 w-5" />
                                Roles Selection
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center justify-between">
                                <Label>Roles *</Label>
                                <Button type="button" variant="outline" size="sm" @click="selectAllRoles">
                                    {{
                                        selectedRoles.length ===
                                            props.roles.length
                                            ? "Deselect All"
                                            : "Select All"
                                    }}
                                </Button>
                            </div>
                            <div class="grid gap-2 md:grid-cols-2">
                                <div v-for="role in props.roles" :key="role.id" class="flex items-center space-x-2">
                                    <Checkbox
                                        :model-value="selectedRoles.includes(role.id)"
                                        @update:modelValue="(val) => toggleRole(role.id, val)"
                                    />
                                    <Label class="cursor-pointer" @click="toggleRole(role.id)">
                                        {{ role.name }}
                                    </Label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Preview -->
                    <Card v-if="previewCombinations.length > 0">
                        <CardHeader>
                            <CardTitle>Preview Combinations ({{
                                previewCombinations.length
                            }})</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="max-h-64 overflow-y-auto space-y-2">
                                <div v-for="(
combo, index
                                    ) in previewCombinations" :key="index"
                                    class="flex items-center gap-2 p-2 bg-muted rounded text-sm">
                                    <Badge variant="outline">{{
                                        combo.role
                                        }}</Badge>
                                    <span>×</span>
                                    <Badge variant="secondary">{{
                                        combo.study_program
                                        }}</Badge>
                                    <span class="text-muted-foreground">in {{ combo.faculty }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </template>

                <!-- Error Messages -->
                <Card v-if="errors.duplicate" class="border-destructive">
                    <CardContent class="p-4">
                        <p class="text-destructive text-sm">
                            {{ errors.duplicate }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-2">
                    <Button type="button" variant="outline" @click="
                        $inertia.visit(route('admin.form-access-controls.index'))
                        ">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isBulkMode ? bulkForm.processing : form.processing
                        ">
                        {{
                            (isBulkMode ? bulkForm.processing : form.processing)
                                ? "Creating..."
                                : isBulkMode
                                    ? `Create ${previewCombinations.length} Access Controls`
                                    : "Create Access Control"
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
