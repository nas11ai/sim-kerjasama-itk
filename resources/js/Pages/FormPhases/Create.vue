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
import { Plus, Trash2, GripVertical, ArrowLeft } from "lucide-vue-next";
import draggable from "vuedraggable";

interface Role {
    id: number;
    name: string;
}

interface Faculty {
    id: number;
    name: string;
    study_programs: StudyProgram[];
}

interface StudyProgram {
    id: number;
    name: string;
    faculty_id: number;
}

interface Form {
    id: number;
    title: string;
}

interface PhaseType {
    id: number;
    name: string;
}

interface FormAccessControl {
    id: number;
    form: Form;
    role: Role;
    study_program: StudyProgram;
}

interface PhaseDetail {
    form_access_control_id: number | null;
    phase_type_id: number | null;
    order: number;
    needs_review: boolean;
    temp_id: string;
}

// Renamed from FormData to FormPhaseData to avoid conflict with built-in FormData
interface FormPhaseData {
    title: string;
    description: string;
    is_active: boolean;
    phase_details: PhaseDetail[];
    [key: string]: any; // Add index signature for Inertia compatibility
}

interface Props {
    forms: Form[];
    roles: Role[];
    faculties: Faculty[];
    phaseTypes: PhaseType[];
    formAccessControls: FormAccessControl[];
}

const props = defineProps<Props>();

// Updated to use FormPhaseData instead of FormData
const form = useForm<FormPhaseData>({
    title: "",
    description: "",
    is_active: true,
    phase_details: [],
});

const errors = computed(() => (form.errors as Record<string, string>) ?? {});

const generateTempId = () => `temp_${Date.now()}_${Math.random()}`;

const addPhaseDetail = () => {
    form.phase_details.push({
        form_access_control_id: null,
        phase_type_id: null,
        order: form.phase_details.length + 1,
        needs_review: false,
        temp_id: generateTempId(),
    });
};

const removePhaseDetail = (index: number) => {
    form.phase_details.splice(index, 1);
    // Reorder remaining items
    form.phase_details.forEach((detail, idx) => {
        detail.order = idx + 1;
    });
};

const getFormAccessControlInfo = (id: number | null) => {
    if (!id) return null;
    return props.formAccessControls.find((fac) => fac.id === id);
};

const submit = () => {
    form.post(route("form-phases.store"));
};
</script>

<template>

    <Head title="Create Form Phase" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="$inertia.visit(route('form-phases.index'))">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Back
                </Button>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Create New Form Phase
                </h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Phase Basic Info -->
                <Card>
                    <CardHeader>
                        <CardTitle>Phase Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Phase Title -->
                        <div class="space-y-2">
                            <Label for="title">Phase Title *</Label>
                            <Input id="title" v-model="form.title" placeholder="Enter phase title" :class="errors.title ? 'border-destructive' : ''
                                " />
                            <p v-if="errors.title" class="text-sm text-destructive">
                                {{ errors.title }}
                            </p>
                        </div>

                        <!-- Description -->
                        <div class="space-y-2">
                            <Label for="description">Description</Label>
                            <Textarea id="description" v-model="form.description"
                                placeholder="Enter phase description (optional)" rows="3" />
                        </div>

                        <!-- Active switch -->
                        <div class="flex items-center space-x-2">
                            <Switch v-model="form.is_active" id="is_active" />
                            <Label for="is_active">Active</Label>
                        </div>
                    </CardContent>
                </Card>

                <!-- Phase Details -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle>Phase Details</CardTitle>
                            <Button v-if="form.phase_details.length === 0" type="button" @click="addPhaseDetail"
                                size="sm">
                                <Plus class="h-4 w-4 mr-2" />
                                Add Phase Detail
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="form.phase_details.length === 0" class="text-center py-8 text-muted-foreground">
                            <p>
                                No phase details added yet. Click "Add Phase
                                Detail" to get started.
                            </p>
                        </div>

                        <div v-else class="space-y-4">
                            <draggable v-model="form.phase_details" item-key="temp_id" handle=".drag-handle"
                                class="space-y-4" :animation="200" @end="
                                    form.phase_details.forEach(
                                        (detail, idx) =>
                                            (detail.order = idx + 1)
                                    )
                                    ">
                                <template #item="{ element: detail, index }">
                                    <Card class="border-2 border-dashed">
                                        <CardContent class="pt-6">
                                            <div class="flex items-start gap-4">
                                                <div class="drag-handle cursor-move p-1 hover:bg-muted rounded">
                                                    <GripVertical class="h-4 w-4 text-muted-foreground" />
                                                </div>

                                                <div class="flex-1 space-y-4">
                                                    <div class="grid gap-4 md:grid-cols-2">
                                                        <!-- Form Access Control -->
                                                        <div class="space-y-2">
                                                            <Label>Form Access
                                                                Control *</Label>
                                                            <Select v-model="detail.form_access_control_id
                                                                ">
                                                                <SelectTrigger>
                                                                    <SelectValue
                                                                        placeholder="Select form access control" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem v-for="fac in props.formAccessControls"
                                                                        :key="fac.id
                                                                            " :value="fac.id
                                                                                ">
                                                                        {{
                                                                            fac
                                                                                .form
                                                                                .title
                                                                        }}
                                                                        -
                                                                        {{
                                                                            fac
                                                                                .role
                                                                                .name
                                                                        }}
                                                                        -
                                                                        {{
                                                                            fac
                                                                                .study_program
                                                                                .name
                                                                        }}
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>

                                                        <!-- Phase Type -->
                                                        <div class="space-y-2">
                                                            <Label>Phase Type
                                                                *</Label>
                                                            <Select v-model="detail.phase_type_id
                                                                ">
                                                                <SelectTrigger>
                                                                    <SelectValue placeholder="Select phase type" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem v-for="phaseType in props.phaseTypes"
                                                                        :key="phaseType.id
                                                                            " :value="phaseType.id
                                                                                ">
                                                                        {{
                                                                            phaseType.name
                                                                        }}
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                    </div>

                                                    <!-- Needs Review switch -->
                                                    <div class="flex items-center space-x-2">
                                                        <Switch v-model="detail.needs_review" />
                                                        <Label>Needs Review</Label>
                                                    </div>

                                                    <!-- Order Display -->
                                                    <div class="flex items-center gap-2">
                                                        <Badge variant="outline">
                                                            Order:
                                                            {{ detail.order }}
                                                        </Badge>
                                                    </div>

                                                    <!-- Preview Info -->
                                                    <div v-if="
                                                        detail.form_access_control_id &&
                                                        detail.phase_type_id
                                                    " class="mt-4 p-3 bg-muted rounded-lg">
                                                        <Label class="text-sm text-muted-foreground mb-2 block">
                                                            Preview:
                                                        </Label>
                                                        <div v-if="
                                                            getFormAccessControlInfo(
                                                                detail.form_access_control_id
                                                            )
                                                        " class="space-y-1 text-sm">
                                                            <div>
                                                                <strong>Form:</strong>
                                                                {{
                                                                    getFormAccessControlInfo(
                                                                        detail.form_access_control_id
                                                                    )?.form
                                                                        .title
                                                                }}
                                                            </div>
                                                            <div>
                                                                <strong>Role:</strong>
                                                                {{
                                                                    getFormAccessControlInfo(
                                                                        detail.form_access_control_id
                                                                    )?.role.name
                                                                }}
                                                            </div>
                                                            <div>
                                                                <strong>Study
                                                                    Program:</strong>
                                                                {{
                                                                    getFormAccessControlInfo(
                                                                        detail.form_access_control_id
                                                                    )
                                                                        ?.study_program
                                                                        .name
                                                                }}
                                                            </div>
                                                            <div>
                                                                <strong>Phase
                                                                    Type:</strong>
                                                                {{
                                                                    props.phaseTypes.find(
                                                                        (pt) =>
                                                                            pt.id ===
                                                                            detail.phase_type_id
                                                                    )?.name
                                                                }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <Button type="button" variant="ghost" size="sm" @click="
                                                    removePhaseDetail(index)
                                                    " class="text-destructive hover:text-destructive">
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </template>
                            </draggable>

                            <!-- Add Another Detail Button -->
                            <div class="flex justify-center pt-4">
                                <Button type="button" @click="addPhaseDetail" variant="outline" size="sm"
                                    class="w-full max-w-xs">
                                    <Plus class="h-4 w-4 mr-2" />
                                    Add Another Phase Detail
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-2">
                    <Button type="button" variant="outline" @click="$inertia.visit(route('form-phases.index'))">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing
                                ? "Creating..."
                                : "Create Form Phase"
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
