<script setup lang="ts">
import { computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Separator } from "@/Components/ui/separator";
import {
    ArrowLeft,
    Edit,
    Calendar,
    Clock,
    Settings,
    FileText,
    CheckCircle,
    XCircle,
    AlertTriangle,
    Timer,
} from "lucide-vue-next";

interface SubmissionDate {
    id: number;
    label: string;
    datetime: string;
}

interface FormPhase {
    id: number;
    title: string;
    description?: string;
    is_active: boolean;
}

interface SubmissionRule {
    id: number;
    label: string;
    value: number;
}

interface SubmissionPeriodPhase {
    id: number;
    form_phase: FormPhase;
}

interface SubmissionPeriodDetail {
    id: number;
    submission_rule: SubmissionRule;
}

interface SubmissionPeriod {
    id: number;
    name: string;
    created_at: string;
    updated_at: string;
    is_active: boolean;
    status: "upcoming" | "active" | "expired" | "no_dates";
    days_remaining?: number;
    submission_dates: SubmissionDate[];
    submission_period_phases: SubmissionPeriodPhase[];
    submission_period_details: SubmissionPeriodDetail[];
}

interface Props {
    submissionPeriod: SubmissionPeriod;
}

const props = defineProps<Props>();

const sortedDates = computed(() => {
    return [...props.submissionPeriod.submission_dates].sort(
        (a, b) =>
            new Date(a.datetime).getTime() - new Date(b.datetime).getTime()
    );
});

const activePhases = computed(() => {
    return props.submissionPeriod.submission_period_phases.filter(
        (phase) => phase.form_phase.is_active
    );
});

const inactivePhases = computed(() => {
    return props.submissionPeriod.submission_period_phases.filter(
        (phase) => !phase.form_phase.is_active
    );
});

const getStatusColor = (status: string) => {
    switch (status) {
        case "active":
            return "default";
        case "upcoming":
            return "secondary";
        case "expired":
            return "destructive";
        case "no_dates":
            return "outline";
        default:
            return "outline";
    }
};

const getStatusText = (status: string) => {
    switch (status) {
        case "active":
            return "Active";
        case "upcoming":
            return "Upcoming";
        case "expired":
            return "Expired";
        case "no_dates":
            return "No Dates";
        default:
            return "Unknown";
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case "active":
            return CheckCircle;
        case "upcoming":
            return Timer;
        case "expired":
            return XCircle;
        case "no_dates":
            return AlertTriangle;
        default:
            return AlertTriangle;
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString("en-US", {
        weekday: "long",
        year: "numeric",
        month: "long",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const formatDateShort = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};

const isDatePassed = (dateString: string) => {
    return new Date(dateString) < new Date();
};

const isDateToday = (dateString: string) => {
    const date = new Date(dateString);
    const today = new Date();
    return date.toDateString() === today.toDateString();
};
</script>

<template>

    <Head title="Submission Period Details" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.submission-periods.index')">
                    <Button variant="ghost" size="sm">
                        <ArrowLeft class="h-4 w-4 mr-2" />
                        Back to Submission Periods
                    </Button>
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Submission Period Details
                    </h2>
                </div>
                <Link :href="route(
                    'admin.submission-periods.edit',
                    props.submissionPeriod.id
                )
                    ">
                <Button>
                    <Edit class="h-4 w-4 mr-2" />
                    Edit Period
                </Button>
                </Link>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Period Overview -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Calendar class="h-5 w-5" />
                        Period Overview
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-1">
                                Period Name
                            </h3>
                            <p class="text-lg font-semibold">
                                {{ props.submissionPeriod.name }}
                            </p>
                        </div>
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-1">
                                Status
                            </h3>
                            <Badge :variant="getStatusColor(
                                props.submissionPeriod.status
                            )
                                " class="flex items-center gap-1 w-fit">
                                <component :is="getStatusIcon(
                                    props.submissionPeriod.status
                                )
                                    " class="h-3 w-3" />
                                {{
                                    getStatusText(props.submissionPeriod.status)
                                }}
                            </Badge>
                        </div>
                    </div>

                    <div v-if="
                        props.submissionPeriod.days_remaining !== null &&
                        props.submissionPeriod.status === 'active'
                    ">
                        <h3 class="font-medium text-sm text-muted-foreground mb-1">
                            Days Remaining
                        </h3>
                        <div class="flex items-center gap-2">
                            <Clock class="h-4 w-4 text-orange-500" />
                            <span class="font-medium text-orange-600">
                                {{ props.submissionPeriod.days_remaining }}
                                day(s) remaining
                            </span>
                        </div>
                    </div>

                    <Separator />

                    <div class="grid gap-6 md:grid-cols-2 text-sm">
                        <div class="flex items-center gap-2">
                            <Calendar class="h-4 w-4 text-muted-foreground" />
                            <span class="text-muted-foreground">Created:</span>
                            <span>{{
                                formatDateShort(
                                    props.submissionPeriod.created_at
                                )
                            }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Calendar class="h-4 w-4 text-muted-foreground" />
                            <span class="text-muted-foreground">Updated:</span>
                            <span>{{
                                formatDateShort(
                                    props.submissionPeriod.updated_at
                                )
                            }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Submission Dates -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Calendar class="h-5 w-5" />
                        Submission Dates
                        <Badge variant="secondary" class="ml-2">
                            {{ sortedDates.length }} dates
                        </Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="sortedDates.length === 0" class="text-center py-8 text-muted-foreground">
                        <Calendar class="h-12 w-12 mx-auto mb-4 opacity-50" />
                        <p>No submission dates configured.</p>
                    </div>
                    <div v-else class="space-y-4">
                        <div v-for="(date, index) in sortedDates" :key="date.id"
                            class="flex items-center justify-between p-4 border rounded-lg" :class="{
                                'bg-green-50 border-green-200': isDateToday(
                                    date.datetime
                                ),
                                'bg-gray-50 border-gray-200':
                                    isDatePassed(date.datetime) &&
                                    !isDateToday(date.datetime),
                                'bg-blue-50 border-blue-200':
                                    !isDatePassed(date.datetime) &&
                                    !isDateToday(date.datetime),
                            }">
                            <div>
                                <h4 class="font-medium">{{ date.label }}</h4>
                                <p class="text-sm text-muted-foreground">
                                    {{ formatDate(date.datetime) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge v-if="isDateToday(date.datetime)" variant="default" class="bg-green-600">
                                    Today
                                </Badge>
                                <Badge v-else-if="isDatePassed(date.datetime)" variant="secondary">
                                    Passed
                                </Badge>
                                <Badge v-else variant="outline">
                                    Upcoming
                                </Badge>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Form Phases -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Settings class="h-5 w-5" />
                        Associated Form Phases
                        <Badge variant="secondary" class="ml-2">
                            {{
                                props.submissionPeriod.submission_period_phases
                                    .length
                            }}
                            phases
                        </Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="
                        props.submissionPeriod.submission_period_phases
                            .length === 0
                    " class="text-center py-8 text-muted-foreground">
                        <Settings class="h-12 w-12 mx-auto mb-4 opacity-50" />
                        <p>No form phases associated with this period.</p>
                    </div>
                    <div v-else class="space-y-6">
                        <!-- Active Phases -->
                        <div v-if="activePhases.length > 0">
                            <h3 class="font-medium text-green-700 mb-3 flex items-center gap-2">
                                <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                                Active Phases ({{ activePhases.length }})
                            </h3>
                            <div class="space-y-3">
                                <div v-for="phase in activePhases" :key="phase.id"
                                    class="flex items-center justify-between p-3 border rounded-lg bg-green-50 border-green-200">
                                    <div>
                                        <h4 class="font-medium">
                                            {{ phase.form_phase.title }}
                                        </h4>
                                        <p v-if="phase.form_phase.description" class="text-sm text-muted-foreground">
                                            {{ phase.form_phase.description }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Badge variant="default" class="bg-green-600">
                                            Active
                                        </Badge>
                                        <Link :href="route(
                                            'admin.form-phases.show',
                                            phase.form_phase.id
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

                        <!-- Inactive Phases -->
                        <div v-if="inactivePhases.length > 0">
                            <h3 class="font-medium text-gray-600 mb-3 flex items-center gap-2">
                                <div class="w-2 h-2 bg-gray-400 rounded-full"></div>
                                Inactive Phases ({{ inactivePhases.length }})
                            </h3>
                            <div class="space-y-3">
                                <div v-for="phase in inactivePhases" :key="phase.id"
                                    class="flex items-center justify-between p-3 border rounded-lg bg-gray-50 border-gray-200">
                                    <div>
                                        <h4 class="font-medium text-gray-600">
                                            {{ phase.form_phase.title }}
                                        </h4>
                                        <p v-if="phase.form_phase.description" class="text-sm text-muted-foreground">
                                            {{ phase.form_phase.description }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Badge variant="secondary">
                                            Inactive
                                        </Badge>
                                        <Link :href="route(
                                            'admin.form-phases.show',
                                            phase.form_phase.id
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
                    </div>
                </CardContent>
            </Card>

            <!-- Submission Rules -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        Submission Rules
                        <Badge variant="secondary" class="ml-2">
                            {{
                                props.submissionPeriod.submission_period_details
                                    .length
                            }}
                            rules
                        </Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="
                        props.submissionPeriod.submission_period_details
                            .length === 0
                    " class="text-center py-8 text-muted-foreground">
                        <FileText class="h-12 w-12 mx-auto mb-4 opacity-50" />
                        <p>No submission rules applied to this period.</p>
                    </div>
                    <div v-else class="grid gap-3 md:grid-cols-2">
                        <div v-for="detail in props.submissionPeriod
                            .submission_period_details" :key="detail.id" class="p-3 border rounded-lg">
                            <h4 class="font-medium">
                                {{ detail.submission_rule.label }}
                            </h4>
                            <p class="text-sm text-muted-foreground">
                                Value: {{ detail.submission_rule.value }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Summary Statistics -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <Calendar class="h-8 w-8 text-blue-500" />
                            <div>
                                <p class="text-2xl font-bold">
                                    {{ sortedDates.length }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Total Dates
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <Settings class="h-8 w-8 text-green-500" />
                            <div>
                                <p class="text-2xl font-bold">
                                    {{ activePhases.length }}
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
                            <FileText class="h-8 w-8 text-purple-500" />
                            <div>
                                <p class="text-2xl font-bold">
                                    {{
                                        props.submissionPeriod
                                            .submission_period_details.length
                                    }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Applied Rules
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <Timer class="h-8 w-8 text-orange-500" />
                            <div>
                                <p class="text-2xl font-bold">
                                    {{
                                        props.submissionPeriod.days_remaining ??
                                        "-"
                                    }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Days Left
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
