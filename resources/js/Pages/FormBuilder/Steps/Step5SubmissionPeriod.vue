<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Badge } from '@/Components/ui/badge';
import { Plus, Trash2, Calendar, Clock } from 'lucide-vue-next';

interface SubmissionDate {
    label_id: number | null;
    label: string;
    date: string;
    temp_id: string;
}

interface SubmissionPeriodData {
    use_existing: boolean;
    existing_period_id: number | null;
    new_period_name: string;
    dates: SubmissionDate[];
}

interface Props {
    modelValue: SubmissionPeriodData;
    submissionPeriods: any[];
    errors: Record<string, string>;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:modelValue': [value: SubmissionPeriodData];
}>();

const submissionPeriodData = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

// Hardcoded submission date labels (atau bisa ambil dari backend)
const submissionDateLabels = [
    { id: 1, name: 'Start Date' },
    { id: 2, name: 'End Date' },
    { id: 3, name: 'Review Deadline' },
    { id: 4, name: 'Final Submission' },
];

const selectedPeriodInfo = computed(() => {
    if (!submissionPeriodData.value.use_existing || !submissionPeriodData.value.existing_period_id) return null;
    return props.submissionPeriods.find((p) => p.id === submissionPeriodData.value.existing_period_id);
});

const generateTempId = () => `temp_${Date.now()}_${Math.random()}`;

const addDate = () => {
    submissionPeriodData.value.dates.push({
        label_id: null,
        label: '',
        date: '',
        temp_id: generateTempId(),
    });
};

const removeDate = (index: number) => {
    submissionPeriodData.value.dates.splice(index, 1);
};

const getLabelName = (labelId: number | null) => {
    if (!labelId) return 'Not selected';
    return submissionDateLabels.find((l) => l.id === labelId)?.name || 'Unknown';
};

const formatDateForDisplay = (dateString: string) => {
    if (!dateString) return 'Not set';
    try {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return dateString;
    }
};

const useExistingString = computed({
    get: () => (submissionPeriodData.value.use_existing ? 'true' : 'false'),
    set: (val: string) => {
        submissionPeriodData.value.use_existing = val === 'true';
    },
});

watch(
    () => submissionPeriodData.value.use_existing,
    (useExisting) => {
        if (useExisting) {
            submissionPeriodData.value.new_period_name = '';
            submissionPeriodData.value.dates = [];
        } else {
            submissionPeriodData.value.existing_period_id = null;
            // Initialize with default dates
            if (submissionPeriodData.value.dates.length === 0) {
                submissionPeriodData.value.dates = [
                    { label_id: 1, label: 'Start Date', date: '', temp_id: generateTempId() },
                    { label_id: 2, label: 'End Date', date: '', temp_id: generateTempId() },
                ];
            }
        }
    }
);
</script>

<template>
    <div class="space-y-6">
        <!-- Option Selection -->
        <Card>
            <CardHeader>
                <CardTitle>Submission Period Configuration</CardTitle>
                <CardDescription>Choose to use an existing period or create a new one</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <RadioGroup v-model="useExistingString" class="space-y-3">
                    <div class="flex items-center space-x-3 p-4 border rounded-lg cursor-pointer hover:bg-muted/50"
                        :class="submissionPeriodData.use_existing ? 'border-primary bg-primary/5' : ''"
                        @click="submissionPeriodData.use_existing = true">
                        <RadioGroupItem value="true" id="use_existing_period" />
                        <Label for="use_existing_period" class="flex-1 cursor-pointer">
                            <div class="flex items-center gap-2 font-medium">
                                <Calendar class="h-4 w-4" />
                                Use Existing Submission Period
                            </div>
                            <p class="text-sm text-muted-foreground mt-1">
                                Add this form to an already active submission period
                            </p>
                        </Label>
                    </div>

                    <div class="flex items-center space-x-3 p-4 border rounded-lg cursor-pointer hover:bg-muted/50"
                        :class="!submissionPeriodData.use_existing ? 'border-primary bg-primary/5' : ''"
                        @click="submissionPeriodData.use_existing = false">
                        <RadioGroupItem value="false" id="create_new_period" />
                        <Label for="create_new_period" class="flex-1 cursor-pointer">
                            <div class="flex items-center gap-2 font-medium">
                                <Plus class="h-4 w-4" />
                                Create New Submission Period
                            </div>
                            <p class="text-sm text-muted-foreground mt-1">
                                Set up a new period with custom dates and deadlines
                            </p>
                        </Label>
                    </div>
                </RadioGroup>
            </CardContent>
        </Card>

        <!-- Existing Period Selection -->
        <Card v-if="submissionPeriodData.use_existing">
            <CardHeader>
                <CardTitle>Select Existing Period</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label>Submission Period *</Label>
                    <Select v-model="submissionPeriodData.existing_period_id">
                        <SelectTrigger>
                            <SelectValue placeholder="Select a submission period" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="period in submissionPeriods" :key="period.id" :value="period.id">
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ period.name }}</span>
                                    <span v-if="period.submission_dates?.length" class="text-xs text-muted-foreground">
                                        {{ period.submission_dates.length }} dates configured
                                    </span>
                                </div>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="errors['submission_period.existing_period_id']" class="text-sm text-destructive">
                        {{ errors['submission_period.existing_period_id'] }}
                    </p>
                </div>

                <!-- Selected Period Info -->
                <Card v-if="selectedPeriodInfo" class="border-blue-200 bg-blue-50">
                    <CardContent class="p-4">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-blue-900">Selected Period</span>
                                <Badge variant="outline" class="text-blue-700">
                                    {{ selectedPeriodInfo.submission_dates?.length || 0 }} dates
                                </Badge>
                            </div>
                            <h4 class="font-semibold text-blue-900">{{ selectedPeriodInfo.name }}</h4>

                            <div v-if="selectedPeriodInfo.submission_dates?.length" class="space-y-2">
                                <div v-for="date in selectedPeriodInfo.submission_dates" :key="date.id"
                                    class="flex items-center justify-between text-sm">
                                    <span class="text-blue-700 font-medium">{{ date.submission_date_label?.name ||
                                        'Date' }}:</span>
                                    <span class="text-blue-600">
                                        {{ formatDateForDisplay(date.datetime) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </CardContent>
        </Card>

        <!-- New Period Creation -->
        <template v-else>
            <Card>
                <CardHeader>
                    <CardTitle>Create New Period</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label for="new_period_name">Period Name *</Label>
                        <Input id="new_period_name" v-model="submissionPeriodData.new_period_name"
                            placeholder="Enter submission period name"
                            :class="errors['submission_period.new_period_name'] ? 'border-destructive' : ''" />
                        <p v-if="errors['submission_period.new_period_name']" class="text-sm text-destructive">
                            {{ errors['submission_period.new_period_name'] }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Submission Dates -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            Submission Dates *
                            <Badge variant="secondary">{{ submissionPeriodData.dates.length }}</Badge>
                        </CardTitle>
                        <Button type="button" @click="addDate" size="sm" variant="outline">
                            <Plus class="h-4 w-4 mr-2" />
                            Add Date
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="submissionPeriodData.dates.length === 0" class="text-center py-8 text-muted-foreground">
                        <Clock class="h-12 w-12 mx-auto mb-4 opacity-50" />
                        <p>No dates added yet. Click "Add Date" to set submission timeline.</p>
                    </div>

                    <div v-else class="space-y-3">
                        <Card v-for="(date, index) in submissionPeriodData.dates" :key="date.temp_id" class="border">
                            <CardContent class="p-4">
                                <div class="flex items-start gap-4">
                                    <div class="flex-1 grid gap-4 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <Label>Date Label *</Label>
                                            <Select v-model="date.label_id">
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select date label" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="label in submissionDateLabels" :key="label.id"
                                                        :value="label.id">
                                                        {{ label.name }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div class="space-y-2">
                                            <Label>Date & Time *</Label>
                                            <Input type="datetime-local" v-model="date.date" />
                                        </div>
                                    </div>

                                    <Button type="button" variant="ghost" size="sm" @click="removeDate(index)"
                                        class="text-destructive" :disabled="submissionPeriodData.dates.length === 1">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>

                                <!-- Date Preview -->
                                <div v-if="date.label_id && date.date" class="mt-3 pt-3 border-t">
                                    <div class="flex items-center gap-2 text-sm">
                                        <Calendar class="h-4 w-4 text-muted-foreground" />
                                        <span class="font-medium">{{ getLabelName(date.label_id) }}:</span>
                                        <span class="text-muted-foreground">{{ formatDateForDisplay(date.date) }}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <p v-if="errors['submission_period.dates']" class="text-sm text-destructive mt-2">
                        {{ errors['submission_period.dates'] }}
                    </p>
                </CardContent>
            </Card>
        </template>

        <!-- Period Preview -->
        <Card class="border-green-200 bg-green-50">
            <CardContent class="p-4">
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <Calendar class="h-5 w-5 text-green-600" />
                        <h3 class="font-medium text-green-900">Period Configuration Preview</h3>
                    </div>
                    <div class="grid gap-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-green-700">Mode:</span>
                            <Badge variant="outline" class="text-green-700">
                                {{ submissionPeriodData.use_existing ? 'Using Existing Period' : 'Creating New Period'
                                }}
                            </Badge>
                        </div>
                        <div v-if="submissionPeriodData.use_existing && selectedPeriodInfo"
                            class="flex items-center justify-between">
                            <span class="text-green-700">Period:</span>
                            <span class="font-medium text-green-900">{{ selectedPeriodInfo.name }}</span>
                        </div>
                        <div v-if="!submissionPeriodData.use_existing && submissionPeriodData.new_period_name"
                            class="flex items-center justify-between">
                            <span class="text-green-700">New Period:</span>
                            <span class="font-medium text-green-900">{{ submissionPeriodData.new_period_name }}</span>
                        </div>
                        <div v-if="!submissionPeriodData.use_existing" class="flex items-center justify-between">
                            <span class="text-green-700">Dates Configured:</span>
                            <Badge variant="outline">{{ submissionPeriodData.dates.length }}</Badge>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
