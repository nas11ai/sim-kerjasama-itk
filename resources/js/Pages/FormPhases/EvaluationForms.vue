<!-- resources/js/Pages/Admin/FormPhases/EvaluationForms.vue -->
<script setup lang="ts">
import { route } from 'ziggy-js'
import { computed, ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { Textarea } from '@/Components/ui/textarea'
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
    Users as UsersIcon,
} from 'lucide-vue-next'
import draggable from 'vuedraggable'

// Types
type BadgeVariant = 'default' | 'destructive' | 'outline' | 'secondary' | null | undefined

interface FieldType {
    id: number
    name: string
}

interface ReviewEvaluationForm {
    id: number
    title: string
    description?: string
    is_required: boolean
    is_active: boolean
    order: number
    fields_count: number
    required_fields_count: number
}

interface FormAccessControl {
    form: {
        id: number
        title: string
    }
    role: {
        id: number
        name: string
    }
    study_program: {
        id: number
        name: string
        faculty: {
            name: string
        }
    }
}

interface FormPhaseDetail {
    id: number
    order: number
    form_access_control: FormAccessControl
    phase_type: {
        id: number
        name: string
    }
    review_evaluation_forms: ReviewEvaluationForm[]
}

interface FormPhase {
    id: number
    title: string
    description?: string
    is_active: boolean
}

interface Props {
    formPhase: FormPhase
    formPhaseDetail: FormPhaseDetail
    fieldTypes: FieldType[]
}

const props = defineProps<Props>()

// State
const createFormDialog = ref(false)
const editFormDialog = ref(false)
const deleteFormDialog = ref(false)
const selectedForm = ref<ReviewEvaluationForm | null>(null)

// Forms
const createFormData = useForm({
    title: '',
    description: '',
    is_required: true as boolean,
    is_active: true as boolean,
    form_phase_detail_id: props.formPhaseDetail.id,
})

const editFormData = useForm({
    title: '',
    description: '',
    is_required: true as boolean,
    is_active: true as boolean,
})

const deleteFormData = useForm({})

const updateOrderData = useForm({
    items: [] as Array<{ id: number; order: number }>,
})

// Computed
const sortedEvaluationForms = computed(() =>
    [...props.formPhaseDetail.review_evaluation_forms].sort((a, b) => a.order - b.order)
)

// Methods
const openCreateDialog = () => {
    createFormData.reset()
    createFormData.form_phase_detail_id = props.formPhaseDetail.id
    createFormDialog.value = true
}

const openEditDialog = (form: ReviewEvaluationForm) => {
    selectedForm.value = form
    editFormData.title = form.title
    editFormData.description = form.description || ''
    editFormData.is_required = form.is_required
    editFormData.is_active = form.is_active
    editFormDialog.value = true
}

const openDeleteDialog = (form: ReviewEvaluationForm) => {
    selectedForm.value = form
    deleteFormDialog.value = true
}

const createForm = () => {
    createFormData.post(route('admin.review-evaluation-forms.store'), {
        onSuccess: () => {
            createFormDialog.value = false
        },
    })
}

const updateForm = () => {
    if (!selectedForm.value) return

    editFormData.patch(route('admin.review-evaluation-forms.update', selectedForm.value.id), {
        onSuccess: () => {
            editFormDialog.value = false
            selectedForm.value = null
        },
    })
}

const deleteForm = () => {
    if (!selectedForm.value) return

    deleteFormData.delete(route('admin.review-evaluation-forms.destroy', selectedForm.value.id), {
        onSuccess: () => {
            deleteFormDialog.value = false
            selectedForm.value = null
        },
    })
}

const duplicateForm = (form: ReviewEvaluationForm) => {
    router.post(route('admin.review-evaluation-forms.duplicate', form.id))
}

const updateOrder = () => {
    updateOrderData.items = sortedEvaluationForms.value.map((form, index) => ({
        id: form.id,
        order: index + 1,
    }))

    updateOrderData.post(route('admin.review-evaluation-forms.update-order'))
}

const getStatusBadge = (form: ReviewEvaluationForm): { variant: BadgeVariant; text: string } => {
    if (!form.is_active) {
        return { variant: 'secondary' as BadgeVariant, text: 'Nonaktif' }
    }
    if (form.is_required) {
        return { variant: 'default' as BadgeVariant, text: 'Wajib' }
    }
    return { variant: 'outline' as BadgeVariant, text: 'Opsional' }
}
</script>

<template>
    <Head :title="`Evaluation Forms - ${formPhaseDetail.form_access_control.form.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="space-y-2 flex gap-4">
                    <div class="flex items-center gap-3">
                        <a :href="route('admin.form-phases.show', formPhase.id)">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft class="h-4 w-4 mr-2" />
                                Kembali
                            </Button>
                        </a>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800">
                            Kelola Formulir Evaluasi
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Untuk: {{ formPhaseDetail.form_access_control.form.title }} ({{
                                formPhase.title
                            }})
                        </p>
                    </div>
                </div>
                <Button @click="openCreateDialog">
                    <Plus class="h-4 w-4 mr-2" />
                    Tambah Formulir Evaluasi
                </Button>
            </div>
        </template>

        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Form Phase Detail Info -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base"> Informasi Detail Tahap Formulir </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-sm font-medium">
                                <FileText class="h-4 w-4 text-muted-foreground" />
                                Formulir
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
                                Program Studi
                            </div>
                            <div class="text-sm text-muted-foreground pl-6">
                                <p>{{ formPhaseDetail.form_access_control.study_program.name }}</p>
                                <p class="text-xs opacity-75">
                                    {{
                                        formPhaseDetail.form_access_control.study_program.faculty
                                            .name
                                    }}
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
                            <CardTitle
                                >Formulir Evaluasi ({{ sortedEvaluationForms.length }})</CardTitle
                            >
                            <p class="text-sm text-muted-foreground mt-1">
                                Seret untuk mengurutkan ulang. Perubahan disimpan secara otomatis.
                            </p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Empty State -->
                    <div
                        v-if="sortedEvaluationForms.length === 0"
                        class="text-center py-12 border-2 border-dashed rounded-lg"
                    >
                        <FileText class="h-12 w-12 mx-auto text-muted-foreground mb-4 opacity-50" />
                        <h3 class="text-lg font-medium mb-2">Tidak Ada Formulir Evaluasi</h3>
                        <p class="text-muted-foreground mb-4">
                            Buat formulir evaluasi pertama Anda untuk detail tahap formulir ini.
                        </p>
                        <Button @click="openCreateDialog">
                            <Plus class="h-4 w-4 mr-2" />
                            Tambah Formulir Pertama
                        </Button>
                    </div>

                    <!-- Forms List -->
                    <div v-else>
                        <draggable
                            v-model="sortedEvaluationForms"
                            item-key="id"
                            handle=".drag-handle"
                            class="space-y-3"
                            :animation="200"
                            @end="updateOrder"
                        >
                            <template #item="{ element: form }">
                                <Card class="border">
                                    <CardContent class="p-4">
                                        <div class="flex items-center space-x-4">
                                            <!-- Drag Handle -->
                                            <div
                                                class="drag-handle cursor-move p-1 hover:bg-muted rounded"
                                            >
                                                <GripVertical
                                                    class="h-4 w-4 text-muted-foreground"
                                                />
                                            </div>

                                            <!-- Form Content -->
                                            <div class="flex-1">
                                                <div class="flex items-start justify-between">
                                                    <div>
                                                        <h4 class="font-medium">
                                                            {{ form.title }}
                                                        </h4>
                                                        <p
                                                            v-if="form.description"
                                                            class="text-sm text-muted-foreground"
                                                        >
                                                            {{ form.description }}
                                                        </p>
                                                        <div
                                                            class="flex items-center space-x-2 mt-2"
                                                        >
                                                            <Badge
                                                                :variant="
                                                                    getStatusBadge(form).variant
                                                                "
                                                            >
                                                                {{ getStatusBadge(form).text }}
                                                            </Badge>
                                                            <Badge variant="outline">
                                                                Urutan: {{ form.order }}
                                                            </Badge>
                                                            <Badge variant="outline">
                                                                {{ form.fields_count }} Isian
                                                            </Badge>
                                                        </div>
                                                    </div>

                                                    <!-- Action Buttons -->
                                                    <div class="flex items-center space-x-1">
                                                        <Button size="sm" variant="ghost" as-child>
                                                            <a
                                                                :href="
                                                                    route(
                                                                        'admin.review-evaluation-forms.preview',
                                                                        form.id
                                                                    )
                                                                "
                                                            >
                                                                <Eye class="h-4 w-4" />
                                                            </a>
                                                        </Button>

                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            @click="openEditDialog(form)"
                                                        >
                                                            <Edit class="h-4 w-4" />
                                                        </Button>

                                                        <Button size="sm" variant="ghost" as-child>
                                                            <a
                                                                :href="
                                                                    route(
                                                                        'admin.review-evaluation-forms.edit',
                                                                        form.id
                                                                    )
                                                                "
                                                            >
                                                                <Settings class="h-4 w-4" />
                                                            </a>
                                                        </Button>

                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            @click="duplicateForm(form)"
                                                        >
                                                            <Copy class="h-4 w-4" />
                                                        </Button>

                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            class="text-destructive"
                                                            @click="openDeleteDialog(form)"
                                                        >
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
                    <DialogTitle>Tambah Formulir Evaluasi Baru</DialogTitle>
                    <DialogDescription>
                        Buat formulir evaluasi baru untuk
                        {{ formPhaseDetail.form_access_control.form.title }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="create_title">Judul *</Label>
                        <Input
                            id="create_title"
                            v-model="createFormData.title"
                            placeholder="Masukkan judul formulir"
                            :class="createFormData.errors.title ? 'border-destructive' : ''"
                        />
                        <p v-if="createFormData.errors.title" class="text-sm text-destructive">
                            {{ createFormData.errors.title }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="create_description">Deskripsi</Label>
                        <Textarea
                            id="create_description"
                            v-model="createFormData.description"
                            placeholder="Masukkan deskripsi formulir (opsional)"
                            rows="3"
                        />
                    </div>

                    <div class="flex items-center space-x-6">
                        <div class="flex items-center space-x-2">
                            <Switch id="create_is_required" v-model="createFormData.is_required" />
                            <Label for="create_is_required">Wajib untuk semua reviewer</Label>
                        </div>

                        <div class="flex items-center space-x-2">
                            <Switch id="create_is_active" v-model="createFormData.is_active" />
                            <Label for="create_is_active">Aktif</Label>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="createFormDialog = false"> Batal </Button>
                    <Button :disabled="createFormData.processing" @click="createForm">
                        {{ createFormData.processing ? 'Membuat...' : 'Buat & Tambah Isian' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Form Dialog -->
        <Dialog v-model:open="editFormDialog">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Edit Formulir Evaluasi</DialogTitle>
                    <DialogDescription> Perbarui pengaturan formulir evaluasi. </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="edit_title">Judul *</Label>
                        <Input
                            id="edit_title"
                            v-model="editFormData.title"
                            placeholder="Masukkan judul formulir"
                            :class="editFormData.errors.title ? 'border-destructive' : ''"
                        />
                        <p v-if="editFormData.errors.title" class="text-sm text-destructive">
                            {{ editFormData.errors.title }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit_description">Deskripsi</Label>
                        <Textarea
                            id="edit_description"
                            v-model="editFormData.description"
                            placeholder="Masukkan deskripsi formulir (opsional)"
                            rows="3"
                        />
                    </div>

                    <div class="flex items-center space-x-6">
                        <div class="flex items-center space-x-2">
                            <Switch id="edit_is_required" v-model="editFormData.is_required" />
                            <Label for="edit_is_required">Wajib untuk semua reviewer</Label>
                        </div>

                        <div class="flex items-center space-x-2">
                            <Switch id="edit_is_active" v-model="editFormData.is_active" />
                            <Label for="edit_is_active">Aktif</Label>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="editFormDialog = false"> Batal </Button>
                    <Button :disabled="editFormData.processing" @click="updateForm">
                        {{ editFormData.processing ? 'Memperbarui...' : 'Perbarui Formulir' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="deleteFormDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Hapus Formulir Evaluasi</DialogTitle>
                    <DialogDescription>
                        Apakah anda yakin ingin menghapus "{{ selectedForm?.title }}"? Ini akan
                        menghapus formulir dan semua isiannya secara permanen.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleteFormDialog = false"> Batal </Button>
                    <Button
                        variant="destructive"
                        :disabled="deleteFormData.processing"
                        @click="deleteForm"
                    >
                        {{ deleteFormData.processing ? 'Menghapus...' : 'Hapus' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
