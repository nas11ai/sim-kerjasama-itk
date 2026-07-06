<!-- resources/js/Pages/Admin/FormPhases/Show.vue -->
<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Separator } from '@/Components/ui/separator'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/Components/ui/collapsible'
import {
    ArrowLeft,
    Edit,
    FileText,
    Users,
    Building,
    CheckCircle,
    XCircle,
    ClipboardList,
    Plus,
    Settings,
    Star,
    ChevronDown,
    ChevronRight,
} from 'lucide-vue-next'

interface Role {
    id: number
    name: string
}

interface Faculty {
    id: number
    name: string
}

interface StudyProgram {
    id: number
    name: string
    faculty: Faculty
}

interface Form {
    id: number
    title: string
}

interface PhaseType {
    id: number
    name: string
}

interface FormAccessControl {
    id: number
    form: Form
    role: Role
    study_program: StudyProgram
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

interface FormPhaseDetail {
    id: number
    order: number
    needs_review: boolean
    phase_type: PhaseType
    form_access_control: FormAccessControl
    review_evaluation_forms?: ReviewEvaluationForm[]
}

interface FormPhase {
    id: number
    title: string
    description?: string
    is_active: boolean
    created_at: string
    updated_at: string
    form_phase_details: FormPhaseDetail[]
    review_evaluation_forms_count?: number
    required_review_evaluation_forms_count?: number
}

interface Props {
    formPhase: FormPhase
}

const props = defineProps<Props>()

const sortedPhaseDetails = computed(() =>
    [...props.formPhase.form_phase_details].sort((a, b) => a.order - b.order)
)

const uniqueFormsCount = computed(
    () => new Set(sortedPhaseDetails.value.map((d) => d.form_access_control.form.id)).size
)

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    })
}

const getEvaluationFormsForDetail = (detail: FormPhaseDetail) => {
    if (!detail.review_evaluation_forms) return []
    return [...detail.review_evaluation_forms].sort((a, b) => a.order - b.order)
}

const getTotalEvaluationFormsCount = computed(() => {
    return sortedPhaseDetails.value.reduce((total, detail) => {
        return total + (detail.review_evaluation_forms?.length || 0)
    }, 0)
})

const getRequiredEvaluationFormsCount = computed(() => {
    return sortedPhaseDetails.value.reduce((total, detail) => {
        return (
            total +
            (detail.review_evaluation_forms?.filter((f) => f.is_required && f.is_active).length ||
                0)
        )
    }, 0)
})

// Group phase details by form_id - same form with different access controls get same order
interface GroupedPhaseDetail {
    formId: number
    formTitle: string
    order: number
    details: FormPhaseDetail[]
}

const groupedPhaseDetails = computed((): GroupedPhaseDetail[] => {
    const groups: Map<number, GroupedPhaseDetail> = new Map()
    let currentOrder = 0

    // Sort by minimum order within same form_id
    const detailsSorted = [...props.formPhase.form_phase_details].sort((a, b) => a.order - b.order)

    detailsSorted.forEach((detail) => {
        const formId = detail.form_access_control.form.id

        if (!groups.has(formId)) {
            currentOrder++
            groups.set(formId, {
                formId,
                formTitle: detail.form_access_control.form.title,
                order: currentOrder,
                details: [],
            })
        }

        groups.get(formId)!.details.push(detail)
    })

    return Array.from(groups.values()).sort((a, b) => a.order - b.order)
})

// Track open state for each collapsible group
const openGroups = ref<Set<number>>(new Set())

const toggleGroup = (formId: number) => {
    if (openGroups.value.has(formId)) {
        openGroups.value.delete(formId)
    } else {
        openGroups.value.add(formId)
    }
}

const isGroupOpen = (formId: number) => openGroups.value.has(formId)

// Get all evaluation forms for a group of details
// const getEvaluationFormsForGroup = (details: FormPhaseDetail[]) => {
//     const forms: ReviewEvaluationForm[] = []
//     details.forEach((detail) => {
//         if (detail.review_evaluation_forms) {
//             forms.push(...detail.review_evaluation_forms)
//         }
//     })
//     // Remove duplicates by id
//     const uniqueForms = Array.from(new Map(forms.map((f) => [f.id, f])).values())
//     return uniqueForms.sort((a, b) => a.order - b.order)
// }
</script>

<template>
    <Head title="Detail Tahap Formulir" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.form-phases.index')">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft class="h-4 w-4 mr-2" />
                            Kembali
                        </Button>
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Detail Tahap Formulir
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('admin.form-phases.edit', formPhase.id)">
                        <Button>
                            <Edit class="h-4 w-4 mr-2" />
                            Edit Tahap
                        </Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Phase Information -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        Informasi Tahap
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-1">Judul</h3>
                            <p class="text-lg font-medium">
                                {{ formPhase.title }}
                            </p>
                        </div>
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-1">Status</h3>
                            <Badge
                                :variant="formPhase.is_active ? 'default' : 'secondary'"
                                class="flex items-center gap-1 w-fit"
                            >
                                <CheckCircle v-if="formPhase.is_active" class="h-3 w-3" />
                                <XCircle v-else class="h-3 w-3" />
                                {{ formPhase.is_active ? 'Aktif' : 'Tidak Aktif' }}
                            </Badge>
                        </div>
                    </div>

                    <div v-if="formPhase.description">
                        <h3 class="font-medium text-sm text-muted-foreground mb-1">Deskripsi</h3>
                        <p class="text-gray-700">
                            {{ formPhase.description }}
                        </p>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-1">
                                Dibuat Pada
                            </h3>
                            <p class="text-sm">
                                {{ formatDate(formPhase.created_at) }}
                            </p>
                        </div>
                        <div>
                            <h3 class="font-medium text-sm text-muted-foreground mb-1">
                                Terakhir Diperbarui
                            </h3>
                            <p class="text-sm">
                                {{ formatDate(formPhase.updated_at) }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Phase Details (Grouped by Form) -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-5 w-5" />
                        Detail Tahap ({{ groupedPhaseDetails.length }} Formulir,
                        {{ sortedPhaseDetails.length }} Akses Kontrol)
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <!-- Empty State -->
                    <div
                        v-if="sortedPhaseDetails.length === 0"
                        class="text-center py-8 text-muted-foreground"
                    >
                        <Users class="h-12 w-12 mx-auto mb-4 opacity-50" />
                        <p>Belum ada detail tahap yang dikonfigurasi.</p>
                    </div>

                    <!-- Phase Details List (Grouped by Form) -->
                    <div v-else class="space-y-4">
                        <Collapsible
                            v-for="group in groupedPhaseDetails"
                            :key="group.formId"
                            :open="isGroupOpen(group.formId)"
                            class="border rounded-lg bg-card"
                        >
                            <!-- Collapsible Trigger - Form Header -->
                            <CollapsibleTrigger
                                class="w-full p-4 flex items-center justify-between hover:bg-muted/50 transition-colors rounded-t-lg"
                                @click="toggleGroup(group.formId)"
                            >
                                <div class="flex items-center gap-3">
                                    <Badge variant="default" class="text-sm font-semibold">
                                        Urutan {{ group.order }}
                                    </Badge>
                                    <div class="flex items-center gap-2">
                                        <FileText class="h-5 w-5 text-muted-foreground" />
                                        <span class="font-semibold text-lg">{{
                                            group.formTitle
                                        }}</span>
                                    </div>
                                    <Badge variant="secondary" class="text-xs">
                                        {{ group.details.length }} Akses Kontrol
                                    </Badge>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge
                                        v-if="group.details.some((d) => d.needs_review)"
                                        class="text-xs"
                                    >
                                        Perlu Review
                                    </Badge>
                                    <ChevronDown
                                        v-if="isGroupOpen(group.formId)"
                                        class="h-5 w-5 text-muted-foreground transition-transform"
                                    />
                                    <ChevronRight
                                        v-else
                                        class="h-5 w-5 text-muted-foreground transition-transform"
                                    />
                                </div>
                            </CollapsibleTrigger>

                            <!-- Collapsible Content - Access Control Details -->
                            <CollapsibleContent>
                                <div class="px-4 pb-4 space-y-3">
                                    <Separator />

                                    <!-- Access Control List -->
                                    <div class="space-y-2">
                                        <h4
                                            class="text-sm font-medium text-muted-foreground flex items-center gap-2"
                                        >
                                            <Users class="h-4 w-4" />
                                            Akses Kontrol (Role & Program Studi)
                                        </h4>
                                        <div class="grid gap-2 md:grid-cols-2">
                                            <div
                                                v-for="detail in group.details"
                                                :key="detail.id"
                                                class="p-3 border rounded bg-muted/30"
                                            >
                                                <div class="flex items-center justify-between mb-2">
                                                    <div class="flex items-center gap-2">
                                                        <Badge variant="outline" class="text-xs">
                                                            {{ detail.phase_type.name }}
                                                        </Badge>
                                                        <Badge
                                                            v-if="detail.needs_review"
                                                            variant="destructive"
                                                            class="text-xs"
                                                        >
                                                            Perlu Review
                                                        </Badge>
                                                    </div>
                                                </div>
                                                <div class="space-y-1 text-sm">
                                                    <div class="flex items-center gap-2">
                                                        <Users
                                                            class="h-3 w-3 text-muted-foreground"
                                                        />
                                                        <span class="font-medium">{{
                                                            detail.form_access_control.role.name
                                                        }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <Building
                                                            class="h-3 w-3 text-muted-foreground"
                                                        />
                                                        <span class="text-muted-foreground">
                                                            {{
                                                                detail.form_access_control
                                                                    .study_program.name
                                                            }}
                                                            <span class="text-xs opacity-75">
                                                                ({{
                                                                    detail.form_access_control
                                                                        .study_program.faculty.name
                                                                }})
                                                            </span>
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- Evaluation Forms for this detail -->
                                                <div
                                                    v-if="
                                                        getEvaluationFormsForDetail(detail).length >
                                                        0
                                                    "
                                                    class="mt-2 pt-2 border-t"
                                                >
                                                    <div class="flex items-center justify-between">
                                                        <span
                                                            class="text-xs text-muted-foreground flex items-center gap-1"
                                                        >
                                                            <ClipboardList class="h-3 w-3" />
                                                            {{
                                                                getEvaluationFormsForDetail(detail)
                                                                    .length
                                                            }}
                                                            Formulir Evaluasi
                                                        </span>
                                                        <Link
                                                            :href="
                                                                route(
                                                                    'admin.form-phases.evaluation-forms',
                                                                    {
                                                                        formPhase: formPhase.id,
                                                                        detail_id: detail.id,
                                                                    }
                                                                )
                                                            "
                                                        >
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                class="h-6 text-xs"
                                                            >
                                                                <Settings class="h-3 w-3 mr-1" />
                                                                Kelola
                                                            </Button>
                                                        </Link>
                                                    </div>
                                                </div>
                                                <div v-else class="mt-2 pt-2 border-t">
                                                    <Link
                                                        :href="
                                                            route(
                                                                'admin.form-phases.evaluation-forms',
                                                                {
                                                                    formPhase: formPhase.id,
                                                                    detail_id: detail.id,
                                                                }
                                                            )
                                                        "
                                                    >
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            class="h-6 text-xs w-full"
                                                        >
                                                            <Plus class="h-3 w-3 mr-1" />
                                                            Tambah Formulir Evaluasi
                                                        </Button>
                                                    </Link>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
                    </div>
                </CardContent>
            </Card>

            <!-- Summary Statistics -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <Users class="h-8 w-8 text-blue-500" />
                            <div>
                                <p class="text-2xl font-bold">
                                    {{ sortedPhaseDetails.length }}
                                </p>
                                <p class="text-sm text-muted-foreground">Total Urutan</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <ClipboardList class="h-8 w-8 text-orange-500" />
                            <div>
                                <p class="text-2xl font-bold">
                                    {{ getTotalEvaluationFormsCount }}
                                </p>
                                <p class="text-sm text-muted-foreground">Formulir Evaluasi</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <Star class="h-8 w-8 text-red-500" />
                            <div>
                                <p class="text-2xl font-bold">
                                    {{ getRequiredEvaluationFormsCount }}
                                </p>
                                <p class="text-sm text-muted-foreground">Formulir Wajib</p>
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
                                    {{ uniqueFormsCount }}
                                </p>
                                <p class="text-sm text-muted-foreground">Formulir Unik</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
