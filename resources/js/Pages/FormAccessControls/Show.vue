<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Separator } from "@/Components/ui/separator";
import {
    ArrowLeft,
    Edit,
    FileText,
    Users,
    Building,
    Calendar,
    Settings,
    AlertTriangle,
} from "lucide-vue-next";

interface Role {
    id: number;
    name: string;
}

interface Faculty {
    id: number;
    name: string;
}

interface StudyProgram {
    id: number;
    name: string;
    faculty: Faculty;
}

interface Form {
    id: number;
    title: string;
}

interface FormPhase {
    id: number;
    title: string;
    is_active: boolean;
}

interface FormPhaseDetail {
    id: number;
    order: number;
    form_phase: FormPhase;
}

interface FormAccessControl {
    id: number;
    form: Form;
    role: Role;
    study_program: StudyProgram;
    created_at: string;
    updated_at: string;
    form_phase_details: FormPhaseDetail[];
}

interface Props {
    formAccessControl: FormAccessControl;
}

const props = defineProps<Props>();

const activePhaseDetails = props.formAccessControl.form_phase_details.filter(
    (detail) => detail.form_phase.is_active
);

const inactivePhaseDetails = props.formAccessControl.form_phase_details.filter(
    (detail) => !detail.form_phase.is_active
);
</script>

<template>

    <Head title="Form Access Control Details" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.form-access-controls.index')">
                    <Button variant="ghost" size="sm">
                        <ArrowLeft class="h-4 w-4 mr-2" />
                        Back to Access Controls
                    </Button>
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Form Access Control Details
                    </h2>
                </div>
                <Link :href="route(
                    'admin.form-access-controls.edit',
                    props.formAccessControl.id
                )
                    ">
                <Button>
                    <Edit class="h-4 w-4 mr-2" />
                    Edit Access Control
                </Button>
                </Link>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Access Control Information -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Settings class="h-5 w-5" />
                        Access Control Information
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Form Information -->
                    <div>
                        <h3 class="font-medium text-sm text-muted-foreground mb-2 flex items-center gap-2">
                            <FileText class="h-4 w-4" />
                            Form
                        </h3>
                        <div class="p-4 bg-muted rounded-lg">
                            <h4 class="font-semibold text-lg">
                                {{ props.formAccessControl.form.title }}
                            </h4>
                            <p class="text-sm text-muted-foreground mt-1">
                                Form ID: {{ props.formAccessControl.form.id }}
                            </p>
                        </div>
                    </div>

                    <Separator />

                    <!-- Role and Study Program -->
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-2 flex items-center gap-2">
                                <Users class="h-4 w-4" />
                                Role
                            </h3>
                            <div class="p-4 bg-muted rounded-lg">
                                <Badge variant="default" class="text-base px-3 py-1">
                                    {{ props.formAccessControl.role.name }}
                                </Badge>
                            </div>
                        </div>

                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-2 flex items-center gap-2">
                                <Building class="h-4 w-4" />
                                Study Program
                            </h3>
                            <div class="p-4 bg-muted rounded-lg">
                                <h4 class="font-medium">
                                    {{
                                        props.formAccessControl.study_program
                                            .name
                                    }}
                                </h4>
                                <p class="text-sm text-muted-foreground mt-1">
                                    {{
                                        props.formAccessControl.study_program
                                            .faculty.name
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <!-- Timestamps -->
                    <div class="grid gap-6 md:grid-cols-2 text-sm">
                        <div class="flex items-center gap-2">
                            <Calendar class="h-4 w-4 text-muted-foreground" />
                            <span class="text-muted-foreground">Created:</span>
                            <span>{{
                                new Date(
                                    props.formAccessControl.created_at
                                ).toLocaleString()
                            }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Calendar class="h-4 w-4 text-muted-foreground" />
                            <span class="text-muted-foreground">Updated:</span>
                            <span>{{
                                new Date(
                                    props.formAccessControl.updated_at
                                ).toLocaleString()
                            }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Usage in Form Phases -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Settings class="h-5 w-5" />
                        Usage in Form Phases
                        <Badge variant="secondary" class="ml-2">
                            {{
                                props.formAccessControl.form_phase_details
                                    .length
                            }}
                            phases
                        </Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="
                        props.formAccessControl.form_phase_details
                            .length === 0
                    " class="text-center py-8 text-muted-foreground">
                        <Settings class="h-12 w-12 mx-auto mb-4 opacity-50" />
                        <h3 class="font-medium mb-2">Not Used in Any Phase</h3>
                        <p>
                            This access control is not currently being used in
                            any form phases.
                        </p>
                    </div>

                    <div v-else class="space-y-6">
                        <!-- Active Phase Details -->
                        <div v-if="activePhaseDetails.length > 0">
                            <h3 class="font-medium text-green-700 mb-3 flex items-center gap-2">
                                <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                                Active Phases ({{ activePhaseDetails.length }})
                            </h3>
                            <div class="space-y-3">
                                <div v-for="detail in activePhaseDetails" :key="detail.id"
                                    class="flex items-center justify-between p-3 border rounded-lg bg-green-50 border-green-200">
                                    <div class="flex items-center gap-3">
                                        <Badge variant="outline" class="text-xs">
                                            Step {{ detail.order }}
                                        </Badge>
                                        <span class="font-medium">{{
                                            detail.form_phase.title
                                            }}</span>
                                        <Badge variant="default" class="text-xs bg-green-600">
                                            Active
                                        </Badge>
                                    </div>
                                    <Link :href="route(
                                        'form-phases.show',
                                        detail.form_phase.id
                                    )
                                        ">
                                    <Button variant="outline" size="sm">
                                        View Phase
                                    </Button>
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Inactive Phase Details -->
                        <div v-if="inactivePhaseDetails.length > 0">
                            <h3 class="font-medium text-gray-600 mb-3 flex items-center gap-2">
                                <div class="w-2 h-2 bg-gray-400 rounded-full"></div>
                                Inactive Phases ({{
                                    inactivePhaseDetails.length
                                }})
                            </h3>
                            <div class="space-y-3">
                                <div v-for="detail in inactivePhaseDetails" :key="detail.id"
                                    class="flex items-center justify-between p-3 border rounded-lg bg-gray-50 border-gray-200">
                                    <div class="flex items-center gap-3">
                                        <Badge variant="outline" class="text-xs">
                                            Step {{ detail.order }}
                                        </Badge>
                                        <span class="font-medium text-gray-600">{{ detail.form_phase.title }}</span>
                                        <Badge variant="secondary" class="text-xs">
                                            Inactive
                                        </Badge>
                                    </div>
                                    <Link :href="route(
                                        'form-phases.show',
                                        detail.form_phase.id
                                    )
                                        ">
                                    <Button variant="outline" size="sm">
                                        View Phase
                                    </Button>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Warning Card -->
            <Card v-if="activePhaseDetails.length > 0" class="border-amber-200 bg-amber-50">
                <CardContent class="p-4">
                    <div class="flex items-start gap-3">
                        <AlertTriangle class="h-5 w-5 text-amber-600 mt-0.5" />
                        <div>
                            <h4 class="text-amber-800 font-medium text-sm mb-1">
                                Usage Warning
                            </h4>
                            <p class="text-amber-700 text-sm">
                                This access control is currently being used in
                                {{ activePhaseDetails.length }} active form
                                phase(s). Modifying or deleting it may affect
                                users' access to forms and disrupt ongoing
                                processes.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Summary Statistics -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <Settings class="h-8 w-8 text-blue-500" />
                            <div>
                                <p class="text-2xl font-bold">
                                    {{
                                        props.formAccessControl
                                            .form_phase_details.length
                                    }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Total Usage
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                <div class="w-4 h-4 bg-green-500 rounded-full"></div>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-green-600">
                                    {{ activePhaseDetails.length }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Active Phases
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                                <div class="w-4 h-4 bg-gray-400 rounded-full"></div>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-600">
                                    {{ inactivePhaseDetails.length }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Inactive Phases
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
