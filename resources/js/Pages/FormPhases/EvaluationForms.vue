<!-- resources/js/Pages/Admin/FormPhases/EvaluationForms.vue -->
<script setup lang="ts">
import { computed, ref } from "vue";
import { Head, useForm, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Switch } from "@/Components/ui/switch";
import { Textarea } from "@/Components/ui/textarea";
import {
    Plus,
    Trash2,
    Edit,
    ArrowLeft,
    Settings,
    Eye,
    Copy,
    GripVertical,
    FileText,
    Building,
    Users as UsersIcon
} from "lucide-vue-next";
import draggable from "vuedraggable";

// Types
type BadgeVariant = "default" | "destructive" | "outline" | "secondary" | null | undefined;

interface FieldType {
    id: number;
    name: string;
}

interface ReviewEvaluationForm {
    id: number;
    title: string;
    description?: string;
    is_required: boolean;
    is_active: boolean;
    order: number;
    fields_count: number;
    required_fields_count: number;
}

interface FormAccessControl {
    form: {
        id: number;
        title: string;
    };
    role: {
        id: number;
        name: string;
    };
    study_program: {
        id: number;
        name: string;
        faculty: {
            name: string;
        };
    };
}

interface FormPhaseDetail {
    id: number;
    order: number;
    form_access_control: FormAccessControl;
    phase_type: {
        id: number;
        name: string;
    };
    review_evaluation_forms: ReviewEvaluationForm[];
}

interface FormPhase {
    id: number;
    title: string;
    description?: string;
    is_active: boolean;
}

interface Props {
    formPhase: FormPhase;
    formPhaseDetail: FormPhaseDetail;
    fieldTypes: FieldType[];
}

const props = defineProps<Props>();

// State
const createFormDialog = ref(false);
const editFormDialog = ref(false);
const deleteFormDialog = ref(false);
const selectedForm = ref<ReviewEvaluationForm | null>(null);

// Forms
const createFormData = useForm({
    title: '',
    description: '',
    is_required: true as boolean,
    is_active: true as boolean,
    form_phase_detail_id: props.formPhaseDetail.id
});

const editFormData = useForm({
    title: '',
    description: '',
    is_required: true as boolean,
    is_active: true as boolean
});

const deleteFormData = useForm({});

const updateOrderData = useForm({
    items: [] as Array<{ id: number, order: number }>
});

// Computed
const sortedEvaluationForms = computed(() =>
    [...props.formPhaseDetail.review_evaluation_forms].sort((a, b) => a.order - b.order)
);

// Methods
const openCreateDialog = () => {
    createFormData.reset();
    createFormData.form_phase_detail_id = props.formPhaseDetail.id;
    createFormDialog.value = true;
};

const openEditDialog = (form: ReviewEvaluationForm) => {
    selectedForm.value = form;
    editFormData.title = form.title;
    editFormData.description = form.description || '';
    editFormData.is_required = form.is_required;
    editFormData.is_active = form.is_active;
    editFormDialog.value = true;
};

const openDeleteDialog = (form: ReviewEvaluationForm) => {
    selectedForm.value = form;
    deleteFormDialog.value = true;
};

const createForm = () => {
    createFormData.post(route('admin.review-evaluation-forms.store'), {
        onSuccess: () => {
            createFormDialog.value = false;
        }
    });
};

const updateForm = () => {
    if (!selectedForm.value) return;

    editFormData.patch(route('admin.review-evaluation-forms.update', selectedForm.value.id), {
        onSuccess: () => {
            editFormDialog.value = false;
            selectedForm.value = null;
        }
    });
};

const deleteForm = () => {
    if (!selectedForm.value) return;

    deleteFormData.delete(route('admin.review-evaluation-forms.destroy', selectedForm.value.id), {
        onSuccess: () => {
            deleteFormDialog.value = false;
            selectedForm.value = null;
        }
    });
};

const duplicateForm = (form: ReviewEvaluationForm) => {
    router.post(route('admin.review-evaluation-forms.duplicate', form.id));
};

const updateOrder = () => {
    updateOrderData.items = sortedEvaluationForms.value.map((form, index) => ({
        id: form.id,
        order: index + 1
    }));

    updateOrderData.post(route('admin.review-evaluation-forms.update-order'));
};

const getStatusBadge = (form: ReviewEvaluationForm): { variant: BadgeVariant; text: string } => {
    if (!form.is_active) {
        return { variant: 'secondary' as BadgeVariant, text: 'Inactive' };
    }
    if (form.is_required) {
        return { variant: 'default' as BadgeVariant, text: 'Required' };
    }
    return { variant: 'outline' as BadgeVariant, text: 'Optional' };
};
</script>

<template>

    <Head :title="`Evaluation Forms - ${formPhaseDetail.form_access_control.form.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <a :href="route('admin.form-phases.show', formPhase.id)">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft class="h-4 w-4 mr-2" />
                                Back to Phase Details
                            </Button>
                        </a>
                    </div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Manage Evaluation Forms
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        For: {{ formPhaseDetail.form_access_control.form.title }} ({{ formPhase.title }})
                    </p>
                </div>
                <Button @click="openCreateDialog">
                    <Plus class="h-4 w-4 mr-2" />
                    Create Evaluation Form
                </Button>
            </div>
        </template>

        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Form Phase Detail Info -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Form Phase Detail Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-sm font-medium">
                                <FileText class="h-4 w-4 text-muted-foreground" />
                                Form
                            </div>
                            <p class="text-sm text-muted-foreground pl-6">
                                {{ formPhaseDetail.form_access_control.form.title }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-sm font-medium">
                                <UsersIcon class="h-4 w-4 text-muted-foreground" />
                                Role
                            </div>
                            <p class="text-sm text-muted-foreground pl-6">
                                {{ formPhaseDetail.form_access_control.role.name }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-sm font-medium">
                                <Building class="h-4 w-4 text-muted-foreground" />
                                Study Program
                            </div>
                            <div class="text-sm text-muted-foreground pl-6">
                                <p>{{ formPhaseDetail.form_access_control.study_program.name }}</p>
                                <p class="text-xs opacity-75">
                                    {{ formPhaseDetail.form_access_control.study_program.faculty.name }}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Evaluation Forms List -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Evaluation Forms ({{ sortedEvaluationForms.length }})</CardTitle>
                            <p class="text-sm text-muted-foreground mt-1">
                                Drag to reorder. Changes are saved automatically.
                            </p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Empty State -->
                    <div v-if="sortedEvaluationForms.length === 0"
                        class="text-center py-12 border-2 border-dashed rounded-lg">
                        <FileText class="h-12 w-12 mx-auto text-muted-foreground mb-4 opacity-50" />
                        <h3 class="text-lg font-medium mb-2">No Evaluation Forms</h3>
                        <p class="text-muted-foreground mb-4">
                            Create your first evaluation form for this form phase detail.
                        </p>
                        <Button @click="openCreateDialog">
                            <Plus class="h-4 w-4 mr-2" />
                            Create First Form
                        </Button>
                    </div>

                    <!-- Forms List -->
                    <div v-else>
                        <draggable v-model="sortedEvaluationForms" item-key="id" handle=".drag-handle" class="space-y-3"
                            :animation="200" @end="updateOrder">
                            <template #item="{ element: form }">
                                <Card class="border">
                                    <CardContent class="p-4">
                                        <div class="flex items-center space-x-4">
                                            <!-- Drag Handle -->
                                            <div class="drag-handle cursor-move p-1 hover:bg-muted rounded">
                                                <GripVertical class="h-4 w-4 text-muted-foreground" />
                                            </div>

                                            <!-- Form Content -->
                                            <div class="flex-1">
                                                <div class="flex items-start justify-between">
                                                    <div>
                                                        <h4 class="font-medium">{{ form.title }}</h4>
                                                        <p v-if="form.description"
                                                            class="text-sm text-muted-foreground">
                                                            {{ form.description }}
                                                        </p>
                                                        <div class="flex items-center space-x-2 mt-2">
                                                            <Badge :variant="getStatusBadge(form).variant">
                                                                {{ getStatusBadge(form).text }}
                                                            </Badge>
                                                            <Badge variant="outline">
                                                                Order: {{ form.order }}
                                                            </Badge>
                                                            <Badge variant="outline">
                                                                {{ form.fields_count }} fields
                                                            </Badge>
                                                        </div>
                                                    </div>

                                                    <!-- Action Buttons -->
                                                    <div class="flex items-center space-x-1">
                                                        <Button size="sm" variant="ghost" as-child>
                                                            <a
                                                                :href="route('admin.review-evaluation-forms.preview', form.id)">
                                                                <Eye class="h-4 w-4" />
                                                            </a>
                                                        </Button>

                                                        <Button size="sm" variant="ghost" @click="openEditDialog(form)">
                                                            <Edit class="h-4 w-4" />
                                                        </Button>

                                                        <Button size="sm" variant="ghost" as-child>
                                                            <a
                                                                :href="route('admin.review-evaluation-forms.edit', form.id)">
                                                                <Settings class="h-4 w-4" />
                                                            </a>
                                                        </Button>

                                                        <Button size="sm" variant="ghost" @click="duplicateForm(form)">
                                                            <Copy class="h-4 w-4" />
                                                        </Button>

                                                        <Button size="sm" variant="ghost" class="text-destructive"
                                                            @click="openDeleteDialog(form)">
                                                            <Trash2 class="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </template>
                        </draggable>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Create Form Dialog -->
        <Dialog v-model:open="createFormDialog">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Create New Evaluation Form</DialogTitle>
                    <DialogDescription>
                        Create a new evaluation form for {{ formPhaseDetail.form_access_control.form.title }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="create_title">Title *</Label>
                        <Input id="create_title" v-model="createFormData.title" placeholder="Enter form title"
                            :class="createFormData.errors.title ? 'border-destructive' : ''" />
                        <p v-if="createFormData.errors.title" class="text-sm text-destructive">
                            {{ createFormData.errors.title }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="create_description">Description</Label>
                        <Textarea id="create_description" v-model="createFormData.description"
                            placeholder="Enter form description (optional)" rows="3" />
                    </div>

                    <div class="flex items-center space-x-6">
                        <div class="flex items-center space-x-2">
                            <Switch v-model="createFormData.is_required" id="create_is_required" />
                            <Label for="create_is_required">Required for all reviewers</Label>
                        </div>

                        <div class="flex items-center space-x-2">
                            <Switch v-model="createFormData.is_active" id="create_is_active" />
                            <Label for="create_is_active">Active</Label>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="createFormDialog = false">
                        Cancel
                    </Button>
                    <Button @click="createForm" :disabled="createFormData.processing">
                        {{ createFormData.processing ? 'Creating...' : 'Create & Add Fields' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Form Dialog -->
        <Dialog v-model:open="editFormDialog">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Edit Evaluation Form</DialogTitle>
                    <DialogDescription>
                        Update the evaluation form settings.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="edit_title">Title *</Label>
                        <Input id="edit_title" v-model="editFormData.title" placeholder="Enter form title"
                            :class="editFormData.errors.title ? 'border-destructive' : ''" />
                        <p v-if="editFormData.errors.title" class="text-sm text-destructive">
                            {{ editFormData.errors.title }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit_description">Description</Label>
                        <Textarea id="edit_description" v-model="editFormData.description"
                            placeholder="Enter form description (optional)" rows="3" />
                    </div>

                    <div class="flex items-center space-x-6">
                        <div class="flex items-center space-x-2">
                            <Switch v-model="editFormData.is_required" id="edit_is_required" />
                            <Label for="edit_is_required">Required for all reviewers</Label>
                        </div>

                        <div class="flex items-center space-x-2">
                            <Switch v-model="editFormData.is_active" id="edit_is_active" />
                            <Label for="edit_is_active">Active</Label>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="editFormDialog = false">
                        Cancel
                    </Button>
                    <Button @click="updateForm" :disabled="editFormData.processing">
                        {{ editFormData.processing ? 'Updating...' : 'Update Form' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="deleteFormDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Evaluation Form</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete "{{ selectedForm?.title }}"?
                        This will remove the form and all its fields permanently.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleteFormDialog = false">
                        Cancel
                    </Button>
                    <Button variant="destructive" @click="deleteForm" :disabled="deleteFormData.processing">
                        {{ deleteFormData.processing ? 'Deleting...' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
