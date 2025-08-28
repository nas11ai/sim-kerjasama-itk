<script setup lang="ts">
import { computed, ref, watch, onMounted } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import {
    ArrowLeft,
    Users,
    Building,
    FileText,
    AlertTriangle,
} from "lucide-vue-next";

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

interface FormAccessControl {
    id: number;
    form: Form;
    role: Role;
    study_program: StudyProgram & {
        faculty: Faculty;
    };
}

// Rename FormData to avoid conflict with browser's FormData
interface FormFields {
    form_id: number | null;
    role_id: number | null;
    study_program_id: number | null;
    _method: string;
    [key: string]: any;
}

interface Props {
    formAccessControl: FormAccessControl;
    forms: Form[];
    roles: Role[];
    faculties: Faculty[];
}

const props = defineProps<Props>();

const selectedFacultyId = ref<number | null>(null);

const form = useForm<FormFields>({
    form_id: null,
    role_id: null,
    study_program_id: null,
    _method: "PATCH",
});

const errors = computed(() => {
    const formErrors = (form.errors as any) ?? {};
    return {
        form_id: formErrors.form_id,
        role_id: formErrors.role_id,
        study_program_id: formErrors.study_program_id,
        duplicate: formErrors.duplicate,
    };
});

const studyPrograms = computed(() => {
    if (!selectedFacultyId.value) return [];
    const faculty = props.faculties.find(
        (f) => f.id === selectedFacultyId.value
    );
    return faculty?.study_programs || [];
});

// Watch for faculty change to reset study program selection
watch(selectedFacultyId, () => {
    // Only reset if the new faculty doesn't contain the current study program
    const currentStudyProgram = studyPrograms.value.find(
        (sp) => sp.id === form.study_program_id
    );
    if (!currentStudyProgram) {
        form.study_program_id = null;
    }
});

// Initialize form with existing data
onMounted(() => {
    form.form_id = props.formAccessControl.form.id;
    form.role_id = props.formAccessControl.role.id;
    form.study_program_id = props.formAccessControl.study_program.id;
    selectedFacultyId.value = props.formAccessControl.study_program.faculty.id;
});

const submit = () => {
    form.patch(
        route("admin.form-access-controls.update", props.formAccessControl.id)
    );
};
</script>

<template>

    <Head title="Edit Form Access Control" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="
                    $inertia.visit(
                        route(
                            'admin.form-access-controls.show',
                            props.formAccessControl.id
                        )
                    )
                    ">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Back
                </Button>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Edit Form Access Control
                </h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Current Configuration Display -->
            <Card class="border-blue-200 bg-blue-50">
                <CardHeader>
                    <CardTitle class="text-blue-900">Current Configuration</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-3 text-sm">
                        <div>
                            <p class="text-blue-700 font-medium">Form</p>
                            <p class="text-blue-600">
                                {{ props.formAccessControl.form.title }}
                            </p>
                        </div>
                        <div>
                            <p class="text-blue-700 font-medium">Role</p>
                            <p class="text-blue-600">
                                {{ props.formAccessControl.role.name }}
                            </p>
                        </div>
                        <div>
                            <p class="text-blue-700 font-medium">
                                Study Program
                            </p>
                            <p class="text-blue-600">
                                {{ props.formAccessControl.study_program.name }}
                            </p>
                            <p class="text-blue-500 text-xs">
                                {{
                                    props.formAccessControl.study_program
                                        .faculty.name
                                }}
                            </p>
                        </div>
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
                            <Select v-model="form.form_id">
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

                <!-- Access Configuration -->
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

                <!-- Error Messages -->
                <Card v-if="errors.duplicate" class="border-destructive">
                    <CardContent class="p-4">
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="h-4 w-4 text-destructive" />
                            <p class="text-destructive text-sm">
                                {{ errors.duplicate }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Warning about changes -->
                <Card class="border-amber-200 bg-amber-50">
                    <CardContent class="p-4">
                        <div class="flex items-start gap-3">
                            <AlertTriangle class="h-5 w-5 text-amber-600 mt-0.5" />
                            <div>
                                <h4 class="text-amber-800 font-medium text-sm mb-1">
                                    Update Warning
                                </h4>
                                <p class="text-amber-700 text-sm">
                                    Modifying this access control may affect
                                    users who currently have access to the
                                    associated form. Make sure to coordinate
                                    with relevant stakeholders before making
                                    changes.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-2">
                    <Button type="button" variant="outline" @click="
                        $inertia.visit(
                            route(
                                'admin.form-access-controls.show',
                                props.formAccessControl.id
                            )
                        )
                        ">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing
                                ? "Updating..."
                                : "Update Access Control"
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
