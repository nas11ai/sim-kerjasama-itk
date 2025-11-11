<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Separator } from '@/Components/ui/separator';
import {
    UserPlus,
    Users,
    CheckCircle,
    AlertCircle,
    Loader2
} from 'lucide-vue-next';
import { toast } from '@/Components/ui/toast';

interface Reviewer {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface AssignedReviewer {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
    reviewer_role: {
        id: number;
        name: string;
    };
}

interface Props {
    open: boolean;
    submissionId: number;
    availableReviewers: Reviewer[];
    assignedReviewers: AssignedReviewer[];
    hasReviewEvaluationForms?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    hasReviewEvaluationForms: false,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    'assigned': [];
}>();

const selectedReviewerIds = ref<number[]>([]);
const autoAssignForms = ref(true);
const isSubmitting = ref(false);

// Get already assigned reviewer IDs
const assignedReviewerIds = computed(() =>
    props.assignedReviewers.map(ar => ar.id)
);

// Filter out already assigned reviewers
const unassignedReviewers = computed(() =>
    props.availableReviewers.filter(
        reviewer => !assignedReviewerIds.value.includes(reviewer.id)
    )
);

const hasSelectedReviewers = computed(() => selectedReviewerIds.value.length > 0);

const toggleReviewer = (reviewerId: number) => {
    const index = selectedReviewerIds.value.indexOf(reviewerId);
    if (index === -1) {
        selectedReviewerIds.value.push(reviewerId);
    } else {
        selectedReviewerIds.value.splice(index, 1);
    }
};

const selectAll = () => {
    selectedReviewerIds.value = unassignedReviewers.value.map(r => r.id);
};

const clearSelection = () => {
    selectedReviewerIds.value = [];
};

const handleClose = () => {
    if (!isSubmitting.value) {
        selectedReviewerIds.value = [];
        autoAssignForms.value = true;
        emit('update:open', false);
    }
};

const assignReviewers = () => {
    if (selectedReviewerIds.value.length === 0) {
        return;
    }

    isSubmitting.value = true;

    router.post(
        `/admin/submissions/${props.submissionId}/assign-reviewers`,
        {
            reviewer_ids: selectedReviewerIds.value,
            auto_assign_forms: autoAssignForms.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedReviewerIds.value = [];
                autoAssignForms.value = true;
                emit('assigned');
                emit('update:open', false);
            },
            onError: (errors) => {
                console.error('Error assigning reviewers:', errors);
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        }
    );
};
</script>

<template>
    <Dialog :open="open" @update:open="handleClose">
        <DialogContent class="sm:max-w-[600px]">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <UserPlus class="h-5 w-5" />
                    Assign Reviewers
                </DialogTitle>
                <DialogDescription>
                    Select reviewers to assign to this submission. They will be able to review and provide feedback.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <!-- Current Assigned Reviewers -->
                <div v-if="assignedReviewers.length > 0" class="space-y-2">
                    <Label class="text-sm font-medium flex items-center gap-2">
                        <CheckCircle class="h-4 w-4 text-green-600" />
                        Currently Assigned ({{ assignedReviewers.length }})
                    </Label>
                    <div class="border rounded-lg p-3 space-y-2 bg-muted/30">
                        <div v-for="reviewer in assignedReviewers" :key="reviewer.id"
                            class="flex items-center justify-between py-1">
                            <div class="flex items-center gap-2">
                                <div class="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center">
                                    <span class="text-xs font-medium text-primary">
                                        {{ reviewer.user.name.charAt(0).toUpperCase() }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium">{{ reviewer.user.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ reviewer.user.email }}</p>
                                </div>
                            </div>
                            <Badge variant="secondary" class="text-xs">
                                {{ reviewer.reviewer_role.name }}
                            </Badge>
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- Available Reviewers -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <Label class="text-sm font-medium flex items-center gap-2">
                            <Users class="h-4 w-4" />
                            Available Reviewers ({{ unassignedReviewers.length }})
                        </Label>
                        <div class="flex gap-2">
                            <Button type="button" variant="ghost" size="sm" @click="selectAll"
                                :disabled="unassignedReviewers.length === 0">
                                Select All
                            </Button>
                            <Button type="button" variant="ghost" size="sm" @click="clearSelection"
                                :disabled="selectedReviewerIds.length === 0">
                                Clear
                            </Button>
                        </div>
                    </div>

                    <div v-if="unassignedReviewers.length === 0" class="text-center py-8">
                        <AlertCircle class="h-8 w-8 text-muted-foreground mx-auto mb-2" />
                        <p class="text-sm text-muted-foreground">
                            No available reviewers to assign
                        </p>
                    </div>

                    <div v-else class="border rounded-lg divide-y max-h-[300px] overflow-y-auto">
                        <div v-for="reviewer in unassignedReviewers" :key="reviewer.id"
                            class="flex items-center gap-3 p-3 hover:bg-muted/50 transition-colors cursor-pointer"
                            @click="toggleReviewer(reviewer.id)">
                            <Checkbox :id="`reviewer-${reviewer.id}`"
                                :model-value="selectedReviewerIds.includes(reviewer.id)"
                                @update:modelValue="() => toggleReviewer(reviewer.id)" />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <Label :for="`reviewer-${reviewer.id}`" class="text-sm font-medium cursor-pointer">
                                        {{ reviewer.name }}
                                    </Label>
                                    <Badge variant="outline" class="text-xs">
                                        {{ reviewer.role }}
                                    </Badge>
                                </div>
                                <p class="text-xs text-muted-foreground truncate">
                                    {{ reviewer.email }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Auto-assign Forms Option -->
                <div v-if="hasReviewEvaluationForms" class="border rounded-lg p-4 bg-blue-50/50 dark:bg-blue-950/20">
                    <div class="flex items-start gap-3">
                        <Checkbox id="auto-assign-forms" v-model:checked="autoAssignForms" />
                        <div class="space-y-1">
                            <Label for="auto-assign-forms" class="text-sm font-medium cursor-pointer">
                                Automatically assign evaluation forms
                            </Label>
                            <p class="text-xs text-muted-foreground">
                                When enabled, reviewers will be automatically assigned all required evaluation forms for
                                this submission.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Selection Summary -->
                <div v-if="hasSelectedReviewers" class="flex items-center gap-2 text-sm">
                    <CheckCircle class="h-4 w-4 text-green-600" />
                    <span class="font-medium">
                        {{ selectedReviewerIds.length }} reviewer(s) selected
                    </span>
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" @click="handleClose" :disabled="isSubmitting">
                    Cancel
                </Button>
                <Button type="button" @click="assignReviewers" :disabled="!hasSelectedReviewers || isSubmitting">
                    <Loader2 v-if="isSubmitting" class="h-4 w-4 mr-2 animate-spin" />
                    <UserPlus v-else class="h-4 w-4 mr-2" />
                    Assign {{ selectedReviewerIds.length > 0 ? `(${selectedReviewerIds.length})` : '' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
