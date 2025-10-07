<!-- resources/js/Pages/Admin/FormPhases/EvaluationForms.vue -->
<script setup lang="ts">
import { computed, ref } from "vue";
import { Head, useForm, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/Components/ui/dialog";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Switch } from "@/Components/ui/switch";
import { Textarea } from "@/Components/ui/textarea";
import {
    Plus,
    Trash2,
    Edit,
    FileText,
    ArrowLeft,
    Settings,
    Eye,
    Copy,
    GripVertical
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

interface FormPhase {
    id: number;
    title: string;
    description?: string;
    is_active: boolean;
    review_evaluation_forms: ReviewEvaluationForm[];
    review_evaluation_forms_count: number;
    required_review_evaluation_forms_count: number;
}

interface Props {
    formPhase: FormPhase;
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
    form_phase_id: props.formPhase.id
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

// Methods
const openCreateDialog = () => {
    createFormData.reset();
    createFormData.form_phase_id = props.formPhase.id;
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
    updateOrderData.items = props.formPhase.review_evaluation_forms.map((form, index) => ({
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

    <Head :title="`Evaluation Forms - ${formPhase.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="$inertia.visit(route('admin.form-phases.index'))">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Back to Form Phases
                </Button>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ formPhase.title }} - Evaluation Forms
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Manage review evaluation forms for this form phase
                    </p>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Phase Info Card -->
            <Card>
                <CardContent class="p-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <h3 class="text-lg font-medium mb-2">{{ formPhase.title }}</h3>
                            <p v-if="formPhase.description" class="text-muted-foreground mb-4">
                                {{ formPhase.description }}
                            </p>
                            <Badge :variant="formPhase.is_active ? 'default' : 'secondary'">
                                {{ formPhase.is_active ? 'Active' : 'Inactive' }}
                            </Badge>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="text-center p-4 bg-blue-50 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600">
                                    {{ formPhase.review_evaluation_forms_count }}
                                </div>
                                <div class="text-sm text-blue-600">Total Forms</div>
                            </div>
                            <div class="text-center p-4 bg-orange-50 rounded-lg">
                                <div class="text-2xl font-bold text-orange-600">
                                    {{ formPhase.required_review_evaluation_forms_count }}
                                </div>
                                <div class="text-sm text-orange-600">Required Forms</div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Evaluation Forms Management -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle>Review Evaluation Forms</CardTitle>
                        <div class="flex space-x-2">
                            <Dialog v-model:open="createFormDialog">
                                <DialogTrigger as-child>
                                    <Button @click="openCreateDialog">
                                        <Plus class="h-4 w-4 mr-2" />
                                        Add Evaluation Form
                                    </Button>
                                </DialogTrigger>
                            </Dialog>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Empty State -->
                    <div v-if="formPhase.review_evaluation_forms.length === 0" class="text-center py-8">
                        <FileText class="h-16 w-16 mx-auto text-gray-400 mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 mb-2">
                            No evaluation forms created
                        </h3>
                        <p class="text-gray-500 mb-4">
                            Create evaluation forms that reviewers will fill out for submissions in this form phase.
                        </p>
                        <Button @click="openCreateDialog">
                            <Plus class="h-4 w-4 mr-2" />
                            Create First Form
                        </Button>
                    </div>

                    <!-- Forms List -->
                    <div v-else>
                        <!-- Update Order Button -->
                        <div class="mb-4 flex justify-end">
                            <Button @click="updateOrder" variant="outline" size="sm">
                                <Settings class="h-4 w-4 mr-2" />
                                Update Order
                            </Button>
                        </div>

                        <!-- Draggable Forms -->
                        <draggable v-model="formPhase.review_evaluation_forms" item-key="id" handle=".drag-handle"
                            :animation="200" @end="updateOrder">
                            <template #item="{ element: form }">
                                <Card class="mb-4 border-2 border-dashed hover:border-blue-300 transition-colors">
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
                        Create a new evaluation form for the {{ formPhase.title }} form phase.
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
