<script setup lang="ts">
import { computed, ref, onMounted, nextTick } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Separator } from "@/Components/ui/separator";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import {
    Plus,
    Trash2,
    ArrowLeft,
    Calendar,
    Settings,
    FileText,
} from "lucide-vue-next";
import Checkbox from "@/Components/Checkbox.vue";

interface FormPhase {
    id: number;
    title: string;
    description?: string;
}

interface SubmissionRule {
    id: number;
    label: string;
    value: number;
}

interface SubmissionDateLabel {
    id: number;
    name: string;
}

interface SubmissionDate {
    label: string;
    datetime: string;
    temp_id: string;
}

interface ExistingSubmissionDate {
    id: number;
    datetime: string;
    submission_date_label: {
        name: string;
    };
}

interface SubmissionPeriodPhase {
    form_phase: {
        id: number;
        title: string;
        description?: string;
    };
}

interface SubmissionPeriodDetail {
    submission_rule: {
        id: number;
        label: string;
        value: number;
    };
}

interface SubmissionPeriod {
    id: number;
    name: string;
    submission_dates: ExistingSubmissionDate[];
    submission_period_phases: SubmissionPeriodPhase[];
    submission_period_details: SubmissionPeriodDetail[];
}

interface FormData {
    name: string;
    submission_dates: SubmissionDate[];
    form_phase_ids: number[];
    submission_rule_ids: number[];
    [key: string]: any;
}

interface Props {
    submissionPeriod: SubmissionPeriod;
    formPhases: FormPhase[];
    submissionRules: SubmissionRule[];
    submissionDateLabels: SubmissionDateLabel[];
}

const props = defineProps<Props>();

const form = useForm<FormData>({
    name: props.submissionPeriod.name,
    submission_dates: [],
    form_phase_ids: [],
    submission_rule_ids: [],
});

const errors = computed(() => (form.errors as Record<string, string>) ?? {});

// State untuk dialog tambah label baru
const showAddLabelDialog = ref(false);
const newLabelForm = useForm({
    label: "",
});

// State untuk menyimpan labels yang ditambahkan secara dinamis
const dynamicLabels = ref<SubmissionDateLabel[]>([]);

// Reactive key untuk memaksa update komponen Select
const selectKey = ref(0);

// Computed untuk menggabungkan labels
const allLabels = computed(() => {
    return [
        ...props.submissionDateLabels,
        ...dynamicLabels.value,
    ];
});

const generateTempId = () => `temp_${Date.now()}_${Math.random()}`;

// Initialize form data with existing data
onMounted(async () => {
    await nextTick();

    // Load existing submission dates
    form.submission_dates = props.submissionPeriod.submission_dates.map(
        (date) => ({
            label: date.submission_date_label?.name || "",
            datetime: formatDateForInput(date.datetime),
            temp_id: generateTempId(),
        })
    );

    // Load existing form phase IDs
    form.form_phase_ids = props.submissionPeriod.submission_period_phases.map(
        (phase) => phase.form_phase.id
    );

    // Load existing submission rule IDs
    form.submission_rule_ids =
        props.submissionPeriod.submission_period_details.map(
            (detail) => detail.submission_rule.id
        );

    // If no submission dates exist, add one empty entry
    if (form.submission_dates.length === 0) {
        addSubmissionDate();
    }
});

// Format date for datetime-local input
const formatDateForInput = (dateString: string): string => {
    const date = new Date(dateString);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    const hours = String(date.getHours()).padStart(2, "0");
    const minutes = String(date.getMinutes()).padStart(2, "0");

    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const addSubmissionDate = () => {
    const newDate = {
        label: "",
        datetime: "",
        temp_id: generateTempId(),
    };

    form.submission_dates.push(newDate);
};

const addNewLabel = async () => {
    if (newLabelForm.label.trim()) {
        try {
            const response = await fetch(
                route("submission-date-labels.store"),
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN":
                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute("content") || "",
                    },
                    body: JSON.stringify({ label: newLabelForm.label.trim() }),
                }
            );

            if (response.ok) {
                const newLabel = await response.json();
                dynamicLabels.value.push(newLabel);
                newLabelForm.reset();

                // Force update Select components
                selectKey.value++;
            } else {
                console.error("Failed to add new label:", response.statusText);
            }
            showAddLabelDialog.value = false;
        } catch (error) {
            console.error("Failed to add new label:", error);
        }
    }
};

const removeSubmissionDate = (index: number) => {
    form.submission_dates.splice(index, 1);
};

const toggleFormPhase = (phaseId: number) => {
    const index = form.form_phase_ids.indexOf(phaseId);
    if (index > -1) {
        form.form_phase_ids.splice(index, 1);
    } else {
        form.form_phase_ids.push(phaseId);
    }
};

const toggleSubmissionRule = (ruleId: number) => {
    const index = form.submission_rule_ids.indexOf(ruleId);
    if (index > -1) {
        form.submission_rule_ids.splice(index, 1);
    } else {
        form.submission_rule_ids.push(ruleId);
    }
};

const selectAllFormPhases = () => {
    if (form.form_phase_ids.length === props.formPhases.length) {
        form.form_phase_ids = [];
    } else {
        form.form_phase_ids = props.formPhases.map((phase) => phase.id);
    }
};

const selectAllSubmissionRules = () => {
    if (form.submission_rule_ids.length === props.submissionRules.length) {
        form.submission_rule_ids = [];
    } else {
        form.submission_rule_ids = props.submissionRules.map((rule) => rule.id);
    }
};

const submit = () => {
    form.put(route("admin.submission-periods.update", props.submissionPeriod.id));
};
</script>

<template>

    <Head title="Edit Submission Period" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="$inertia.visit(route('admin.submission-periods.index'))">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Back
                </Button>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Edit Submission Period: {{ submissionPeriod.name }}
                </h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Period Basic Info -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Calendar class="h-5 w-5" />
                            Period Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Period Name *</Label>
                            <Input id="name" v-model="form.name" placeholder="Enter submission period name"
                                :class="errors.name ? 'border-destructive' : ''" />
                            <p v-if="errors.name" class="text-sm text-destructive">
                                {{ errors.name }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Submission Dates -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2">
                                <Calendar class="h-5 w-5" />
                                Submission Dates *
                            </CardTitle>
                            <Button type="button" @click="addSubmissionDate" size="sm" variant="outline">
                                <Plus class="h-4 w-4 mr-2" />
                                Add Date
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div v-for="(date, index) in form.submission_dates" :key="date.temp_id"
                                class="flex items-end gap-4 p-4 border rounded-lg">
                                <div class="flex-1 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <Label :for="`date_label_${index}`">Date Label</Label>
                                        <!-- Custom Modal instead of Dialog -->
                                        <div>
                                            <Button type="button" variant="ghost" size="sm" class="text-xs h-6 px-2"
                                                @click="
                                                    showAddLabelDialog = true
                                                    ">
                                                <Plus class="h-3 w-3 mr-1" />
                                                Add New
                                            </Button>

                                            <!-- Modal Overlay -->
                                            <div v-if="showAddLabelDialog" class="fixed inset-0 z-50">
                                                <!-- Background hitam -->
                                                <div class="absolute inset-0 bg-black/80" @click="
                                                    showAddLabelDialog = false
                                                    "></div>

                                                <!-- Modal content -->
                                                <div
                                                    class="relative z-10 flex items-center justify-center min-h-screen p-4">
                                                    <div
                                                        class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 max-h-[calc(100vh-2rem)] overflow-y-auto">
                                                        <h3 class="text-lg font-semibold mb-4">
                                                            Add New Date Label
                                                        </h3>
                                                        <div class="space-y-4">
                                                            <div class="space-y-2">
                                                                <Label for="new-label">Label
                                                                    Name</Label>
                                                                <Input id="new-label" v-model="newLabelForm.label
                                                                    " placeholder="Enter label name" @keyup.enter="
                                                                        addNewLabel
                                                                    " autofocus />
                                                            </div>
                                                            <div class="flex justify-end gap-2 pt-4">
                                                                <Button type="button" variant="outline" @click="
                                                                    showAddLabelDialog = false
                                                                    ">
                                                                    Cancel
                                                                </Button>
                                                                <Button type="button" @click="
                                                                    addNewLabel
                                                                " :disabled="!newLabelForm.label.trim()
                                                                    ">
                                                                    Add Label
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <Select v-model="date.label" :id="`date_label_${index}`">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select date label" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="labelOption in allLabels" :key="labelOption.id"
                                                :value="labelOption.name">
                                                {{ labelOption.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="flex-1 space-y-2">
                                    <Label :for="`date_${index}`">Date</Label>
                                    <Input :id="`date_${index}`" v-model="date.datetime" type="datetime-local" />
                                </div>
                                <Button type="button" variant="ghost" size="sm" @click="removeSubmissionDate(index)"
                                    class="text-destructive hover:text-destructive" :disabled="form.submission_dates.length === 1
                                        ">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                        <p v-if="errors.submission_dates" class="text-sm text-destructive mt-2">
                            {{ errors.submission_dates }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Modal for Add New Label -->
                <div v-if="showAddLabelDialog">
                    <div
                        class="absolute inset-0 bg-black/80"
                        @click="showAddLabelDialog = false"
                    ></div>
                    <div class="relative z-10 flex items-center justify-center min-h-screen p-4">
                        <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 max-h-[calc(100vh-2rem)] overflow-y-auto">
                            <h3 class="text-lg font-semibold mb-4">
                                Add New Date Label
                            </h3>
                            <div class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="new-label">Label Name</Label>
                                    <Input
                                        id="new-label"
                                        v-model="newLabelForm.label"
                                        placeholder="Enter label name"
                                        @keyup.enter="addNewLabel"
                                        autofocus
                                    />
                                </div>
                                <div class="flex justify-end gap-2 pt-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        @click="showAddLabelDialog = false"
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="button"
                                        @click="addNewLabel"
                                        :disabled="!newLabelForm.label.trim()"
                                    >
                                        Add Label
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Phases -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2">
                                <Settings class="h-5 w-5" />
                                Form Phases *
                                <Badge variant="secondary" class="ml-2">
                                    {{ form.form_phase_ids.length }} selected
                                </Badge>
                            </CardTitle>
                            <Button type="button" variant="outline" size="sm" @click="selectAllFormPhases">
                                {{
                                    form.form_phase_ids.length ===
                                        props.formPhases.length
                                        ? "Deselect All"
                                        : "Select All"
                                }}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="props.formPhases.length === 0" class="text-center py-8 text-muted-foreground">
                            <Settings class="h-12 w-12 mx-auto mb-4 opacity-50" />
                            <p>No active form phases available.</p>
                        </div>
                        <div v-else class="grid gap-3 md:grid-cols-2">
                            <div v-for="phase in props.formPhases" :key="phase.id"
                                class="flex items-center space-x-3 p-3 border rounded-lg hover:bg-muted/50 cursor-pointer"
                                @click="toggleFormPhase(phase.id)">
                                <Checkbox :checked="form.form_phase_ids.includes(phase.id)
                                    "/>
                                <div class="flex-1 min-w-0">
                                    <Label class="cursor-pointer font-medium">
                                        {{ phase.title }}
                                    </Label>
                                    <p v-if="phase.description" class="text-sm text-muted-foreground mt-1">
                                        {{ phase.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <p v-if="errors.form_phase_ids" class="text-sm text-destructive mt-2">
                            {{ errors.form_phase_ids }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Submission Rules (Optional) -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2">
                                <FileText class="h-5 w-5" />
                                Submission Rules (Optional)
                                <Badge variant="outline" class="ml-2">
                                    {{ form.submission_rule_ids.length }}
                                    selected
                                </Badge>
                            </CardTitle>
                            <Button type="button" variant="outline" size="sm" @click="selectAllSubmissionRules"
                                v-if="props.submissionRules.length > 0">
                                {{
                                    form.submission_rule_ids.length ===
                                        props.submissionRules.length
                                        ? "Deselect All"
                                        : "Select All"
                                }}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="props.submissionRules.length === 0" class="text-center py-8 text-muted-foreground">
                            <FileText class="h-12 w-12 mx-auto mb-4 opacity-50" />
                            <p>No submission rules available.</p>
                        </div>
                        <div v-else class="grid gap-3 md:grid-cols-2">
                            <div v-for="rule in props.submissionRules" :key="rule.id"
                                class="flex items-center space-x-3 p-3 border rounded-lg hover:bg-muted/50 cursor-pointer"
                                @click="toggleSubmissionRule(rule.id)">
                                <Checkbox :checked="form.submission_rule_ids.includes(
                                    rule.id
                                )
                                    " @update:checked="
                                        toggleSubmissionRule(rule.id)
                                        " />
                                <div class="flex-1">
                                    <Label class="cursor-pointer font-medium">
                                        {{ rule.label }}
                                    </Label>
                                    <p class="text-sm text-muted-foreground">
                                        Value: {{ rule.value }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Preview Summary -->
                <Card v-if="
                    form.name ||
                    form.submission_dates.some((d) => d.label || d.datetime)
                " class="border-blue-200 bg-blue-50">
                    <CardHeader>
                        <CardTitle class="text-blue-900">Preview Summary</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="form.name">
                            <h4 class="font-medium text-blue-800 mb-1">Period Name</h4>
                            <p class="text-blue-700">{{ form.name }}</p>
                        </div>

                        <div v-if="
                            form.submission_dates.some(
                                (d) => d.label || d.datetime
                            )
                        ">
                            <h4 class="font-medium text-blue-800 mb-2">
                                Dates
                            </h4>
                            <div class="space-y-1">
                                <template v-for="(
submissionDate, index
                                    ) in form.submission_dates" :key="index">
                                    <div v-if="
                                        submissionDate.label ||
                                        submissionDate.datetime
                                    " class="text-sm text-blue-600">
                                        <strong>{{
                                            submissionDate.label ||
                                            "Unnamed Date"
                                        }}:</strong>
                                        {{
                                            submissionDate.datetime
                                                ? new Date(
                                                    submissionDate.datetime
                                                ).toLocaleString()
                                                : "No date set"
                                        }}
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div v-if="form.form_phase_ids.length > 0">
                            <h4 class="font-medium text-blue-800 mb-2">Selected Form Phases</h4>
                            <div class="flex flex-wrap gap-1">
                                <Badge v-for="phaseId in form.form_phase_ids" :key="phaseId" variant="outline"
                                    class="text-blue-700 border-blue-300">
                                    {{
                                        props.formPhases.find((p) => p.id === phaseId)?.title
                                    }}
                                </Badge>
                            </div>
                        </div>

                        <div v-if="form.submission_rule_ids.length > 0">
                            <h4 class="font-medium text-blue-800 mb-2">Selected Rules</h4>
                            <div class="flex flex-wrap gap-1">
                                <Badge v-for="ruleId in form.submission_rule_ids" :key="ruleId" variant="outline"
                                    class="text-blue-700 border-blue-300">
                                    {{
                                        props.submissionRules.find((r) => r.id === ruleId)?.label
                                    }}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-2">
                    <Button type="button" variant="outline" @click="
                        $inertia.visit(route('admin.submission-periods.index'))
                        ">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? "Updating..." : "Update Submission Period" }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
