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
    CheckCircle,
    XCircle,
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

interface FormPhaseDetail {
    id: number;
    order: number;
    needs_review: boolean;
    phase_type: PhaseType;
    form_access_control: FormAccessControl;
}

interface FormPhase {
    id: number;
    title: string;
    description?: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    form_phase_details: FormPhaseDetail[];
}

interface Props {
    formPhase: FormPhase;
}

const props = defineProps<Props>();

const sortedPhaseDetails = props.formPhase.form_phase_details.sort(
    (a, b) => a.order - b.order
);
</script>

<template>

    <Head title="Form Phase Details" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('form-phases.index')">
                    <Button variant="ghost" size="sm">
                        <ArrowLeft class="h-4 w-4 mr-2" />
                        Back to Form Phases
                    </Button>
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Form Phase Details
                    </h2>
                </div>
                <Link :href="route('form-phases.edit', props.formPhase.id)">
                <Button>
                    <Edit class="h-4 w-4 mr-2" />
                    Edit Phase
                </Button>
                </Link>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Phase Information -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        Phase Information
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-1">
                                Title
                            </h3>
                            <p class="text-lg font-medium">
                                {{ props.formPhase.title }}
                            </p>
                        </div>
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-1">
                                Status
                            </h3>
                            <Badge :variant="props.formPhase.is_active
                                ? 'default'
                                : 'secondary'
                                " class="flex items-center gap-1 w-fit">
                                <CheckCircle v-if="props.formPhase.is_active" class="h-3 w-3" />
                                <XCircle v-else class="h-3 w-3" />
                                {{
                                    props.formPhase.is_active
                                        ? "Active"
                                        : "Inactive"
                                }}
                            </Badge>
                        </div>
                    </div>

                    <div v-if="props.formPhase.description">
                        <h3 class="font-medium text-sm text-muted-foreground mb-1">
                            Description
                        </h3>
                        <p class="text-gray-700">
                            {{ props.formPhase.description }}
                        </p>
                    </div>

                    <Separator />

                    <div class="grid gap-6 md:grid-cols-2 text-sm">
                        <div class="flex items-center gap-2">
                            <Calendar class="h-4 w-4 text-muted-foreground" />
                            <span class="text-muted-foreground">Created:</span>
                            <span>{{
                                new Date(
                                    props.formPhase.created_at
                                ).toLocaleDateString()
                            }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Calendar class="h-4 w-4 text-muted-foreground" />
                            <span class="text-muted-foreground">Updated:</span>
                            <span>{{
                                new Date(
                                    props.formPhase.updated_at
                                ).toLocaleDateString()
                            }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Phase Details -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-5 w-5" />
                        Phase Details ({{ sortedPhaseDetails.length }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="sortedPhaseDetails.length === 0" class="text-center py-8 text-muted-foreground">
                        <Users class="h-12 w-12 mx-auto mb-4 opacity-50" />
                        <p>No phase details configured yet.</p>
                    </div>

                    <div v-else class="space-y-4">
                        <div v-for="(detail, index) in sortedPhaseDetails" :key="detail.id"
                            class="border rounded-lg p-4 bg-card">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <Badge variant="outline" class="text-xs">
                                        Step {{ detail.order }}
                                    </Badge>
                                    <Badge v-if="detail.needs_review" class="text-xs">
                                        Needs Review
                                    </Badge>
                                    <Badge class="text-xs">
                                        {{ detail.phase_type.name }}
                                    </Badge>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-3">
                                <!-- Form Information -->
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2 text-sm font-medium">
                                        <FileText class="h-4 w-4 text-muted-foreground" />
                                        Form
                                    </div>
                                    <p class="text-sm text-muted-foreground pl-6">
                                        {{
                                            detail.form_access_control.form
                                                .title
                                        }}
                                    </p>
                                </div>

                                <!-- Role Information -->
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2 text-sm font-medium">
                                        <Users class="h-4 w-4 text-muted-foreground" />
                                        Role
                                    </div>
                                    <p class="text-sm text-muted-foreground pl-6">
                                        {{
                                            detail.form_access_control.role.name
                                        }}
                                    </p>
                                </div>

                                <!-- Study Program Information -->
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2 text-sm font-medium">
                                        <Building class="h-4 w-4 text-muted-foreground" />
                                        Study Program
                                    </div>
                                    <div class="text-sm text-muted-foreground pl-6">
                                        <p>
                                            {{
                                                detail.form_access_control
                                                    .study_program.name
                                            }}
                                        </p>
                                        <p class="text-xs opacity-75">
                                            {{
                                                detail.form_access_control
                                                    .study_program.faculty.name
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress indicator for steps -->
                            <div v-if="index < sortedPhaseDetails.length - 1" class="flex justify-center mt-4">
                                <div class="w-px h-6 bg-border"></div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Summary Statistics -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <Users class="h-8 w-8 text-blue-500" />
                            <div>
                                <p class="text-2xl font-bold">
                                    {{ sortedPhaseDetails.length }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Total Steps
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <FileText class="h-8 w-8 text-green-500" />
                            <div>
                                <p class="text-2xl font-bold">
                                    {{
                                        new Set(
                                            sortedPhaseDetails.map(
                                                (d) =>
                                                    d.form_access_control.form
                                                        .id
                                            )
                                        ).size
                                    }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Unique Forms
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <Building class="h-8 w-8 text-purple-500" />
                            <div>
                                <p class="text-2xl font-bold">
                                    {{
                                        new Set(
                                            sortedPhaseDetails.map(
                                                (d) =>
                                                    d.form_access_control.role
                                                        .id
                                            )
                                        ).size
                                    }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Unique Roles
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
