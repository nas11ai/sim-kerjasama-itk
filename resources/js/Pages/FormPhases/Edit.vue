<!-- resources/js/Pages/Admin/FormPhases/Edit.vue -->
<script setup lang="ts">
import { computed, onMounted } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Textarea } from "@/Components/ui/textarea";
import { Switch } from "@/Components/ui/switch";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import { Badge } from "@/Components/ui/badge";
import { Separator } from "@/Components/ui/separator";
import { Plus, Trash2, GripVertical, ArrowLeft } from "lucide-vue-next";
import draggable from "vuedraggable";

interface Role {
    id: number;
    name: string;
}

interface Faculty {
    id: number;
    name: string;
    study_programs: StudyProgram[];
}

interface StudyProgram {
    id: number;
    name: string;
    faculty_id: number;
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

interface ExistingPhaseDetail {
    id: number;
    form_access_control_id: number;
    phase_type_id: number;
    order: number;
    needs_review: boolean;
    phase_type: PhaseType;
    form_access_control: FormAccessControl;
}

interface PhaseDetail {
    id?: number;
    form_access_control_id: number | null;
    phase_type_id: number | null;
    order: number;
    needs_review: boolean;
    temp_id: string;
}

interface FormPhase {
    id: number;
    title: string;
    description?: string;
    is_active: boolean;
    form_phase_details: ExistingPhaseDetail[];
}

interface FormPhaseErrors {
    title?: string;
    description?: string;
    is_active?: string;
    phase_details?: string;
    [key: string]: string | undefined;
}

// Renamed from FormData to FormPhaseEditData to avoid conflict with built-in FormData
interface FormPhaseEditData {
    title: string;
    description: string;
    is_active: boolean;
    phase_details: PhaseDetail[];
    _method: string;
    [key: string]: any; // Add index signature for Inertia compatibility
}

interface Props {
    formPhase: FormPhase;
    forms: Form[];
    roles: Role[];
    faculties: Faculty[];
    phaseTypes: PhaseType[];
    formAccessControls: FormAccessControl[];
}

const props = defineProps<Props>();

const form = useForm<FormPhaseEditData>({
    title: "",
    description: "",
    is_active: true,
    phase_details: [],
    _method: "PATCH",
});

const errors = computed(() => (form.errors as FormPhaseErrors) ?? {});

const generateTempId = () => `temp_${Date.now()}_${Math.random()}`;

// Initialize form with existing data
onMounted(() => {
    form.title = props.formPhase.title;
    form.description = props.formPhase.description || "";
    form.is_active = props.formPhase.is_active;

    // Convert existing phase details to form structure
    form.phase_details = props.formPhase.form_phase_details.map((detail) => ({
        id: detail.id,
        form_access_control_id: detail.form_access_control_id,
        phase_type_id: detail.phase_type_id,
        order: detail.order,
        needs_review: detail.needs_review,
        temp_id: generateTempId(),
    }));
});

const addPhaseDetail = () => {
    form.phase_details.push({
        form_access_control_id: null,
        phase_type_id: null,
        order: form.phase_details.length + 1,
        needs_review: false,
        temp_id: generateTempId(),
    });
};

const removePhaseDetail = (index: number) => {
    form.phase_details.splice(index, 1);
    // Reorder remaining items
    form.phase_details.forEach((detail, idx) => {
        detail.order = idx + 1;
    });
};

const getFormAccessControlInfo = (id: number | null) => {
    if (!id) return null;
    return props.formAccessControls.find((fac) => fac.id === id);
};

const getPhaseTypeInfo = (id: number | null) => {
    if (!id) return null;
    return props.phaseTypes.find((pt) => pt.id === id);
};

// Calculate grouped order preview - shows what order will be displayed in detail page
const getGroupedOrderForDetail = (index: number): number | null => {
    const detail = form.phase_details[index];
    if (!detail?.form_access_control_id) return null;

    const formAccessControl = getFormAccessControlInfo(detail.form_access_control_id);
    if (!formAccessControl) return null;

    const formId = formAccessControl.form.id;

    // Find unique form_ids in order, and get the position of this form_id
    const seenFormIds: number[] = [];
    for (let i = 0; i < form.phase_details.length; i++) {
        const d = form.phase_details[i];
        if (!d.form_access_control_id) continue;

        const fac = getFormAccessControlInfo(d.form_access_control_id);
        if (!fac) continue;

        if (!seenFormIds.includes(fac.form.id)) {
            seenFormIds.push(fac.form.id);
        }

        if (i === index) {
            return seenFormIds.indexOf(formId) + 1;
        }
    }

    return null;
};

// Check if this detail shares same form with another detail
const hasSameFormAsOthers = (index: number): boolean => {
    const detail = form.phase_details[index];
    if (!detail?.form_access_control_id) return false;

    const formAccessControl = getFormAccessControlInfo(detail.form_access_control_id);
    if (!formAccessControl) return false;

    const formId = formAccessControl.form.id;

    return form.phase_details.some((d, i) => {
        if (i === index || !d.form_access_control_id) return false;
        const fac = getFormAccessControlInfo(d.form_access_control_id);
        return fac?.form.id === formId;
    });
};

const submit = () => {
    form.patch(route("admin.form-phases.update", props.formPhase.id));
};
</script>

<template>
    <Head title="Edit Tahap Formulir" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="sm"
                    @click="$inertia.visit(route('admin.form-phases.index'))"
                >
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Kembali
                </Button>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Edit Tahap Formulir: {{ props.formPhase.title }}
                </h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Phase Basic Info -->
                <Card>
                    <CardHeader>
                        <CardTitle>Informasi Tahap</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Phase Title -->
                        <div class="space-y-2">
                            <Label for="title">Judul Tahap *</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                placeholder="Masukkan judul tahap"
                                :class="
                                    errors.title ? 'border-destructive' : ''
                                "
                            />
                            <p
                                v-if="errors.title"
                                class="text-sm text-destructive"
                            >
                                {{ errors.title }}
                            </p>
                        </div>

                        <!-- Description -->
                        <div class="space-y-2">
                            <Label for="description">Deskripsi</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                placeholder="Masukkan deskripsi tahap (opsional)"
                                rows="3"
                            />
                        </div>

                        <!-- Active switch -->
                        <div class="flex items-center space-x-2">
                            <Switch v-model="form.is_active" id="is_active" />
                            <Label for="is_active">Aktif</Label>
                        </div>
                    </CardContent>
                </Card>

                <!-- Phase Details -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle>Detail Tahap</CardTitle>
                            <Button
                                v-if="form.phase_details.length === 0"
                                type="button"
                                @click="addPhaseDetail"
                                size="sm"
                            >
                                <Plus class="h-4 w-4 mr-2" />
                                Tambah Detail Tahap
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="form.phase_details.length === 0"
                            class="text-center py-8 text-muted-foreground"
                        >
                            <p>
                                Belum ada detail tahap yang ditambahkan. Klik
                                "Tambah Detail Tahap" untuk memulai.
                            </p>
                        </div>

                        <div v-else class="space-y-4">
                            <draggable
                                v-model="form.phase_details"
                                item-key="temp_id"
                                handle=".drag-handle"
                                class="space-y-4"
                                :animation="200"
                                @end="
                                    form.phase_details.forEach(
                                        (detail, idx) =>
                                            (detail.order = idx + 1)
                                    )
                                "
                            >
                                <template #item="{ element: detail, index }">
                                    <Card class="border-2 border-dashed">
                                        <CardContent class="pt-6">
                                            <div class="flex items-start gap-4">
                                                <div
                                                    class="drag-handle cursor-move p-1 hover:bg-muted rounded"
                                                >
                                                    <GripVertical
                                                        class="h-4 w-4 text-muted-foreground"
                                                    />
                                                </div>

                                                <div class="flex-1 space-y-4">
                                                    <div
                                                        class="grid gap-4 md:grid-cols-2"
                                                    >
                                                        <!-- Form Access Control -->
                                                        <div class="space-y-2">
                                                            <Label
                                                                >Akses Kontrol
                                                                Formulir
                                                                *</Label
                                                            >
                                                            <Select
                                                                v-model="
                                                                    detail.form_access_control_id
                                                                "
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue
                                                                        placeholder="Pilih akses kontrol formulir"
                                                                    />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem
                                                                        v-for="fac in props.formAccessControls"
                                                                        :key="
                                                                            fac.id
                                                                        "
                                                                        :value="
                                                                            fac.id
                                                                        "
                                                                    >
                                                                        {{
                                                                            fac
                                                                                .form
                                                                                .title
                                                                        }}
                                                                        -
                                                                        {{
                                                                            fac
                                                                                .role
                                                                                .name
                                                                        }}
                                                                        -
                                                                        {{
                                                                            fac
                                                                                .study_program
                                                                                .name
                                                                        }}
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                            <p
                                                                v-if="
                                                                    errors[
                                                                        `phase_details.${index}.form_access_control_id`
                                                                    ]
                                                                "
                                                                class="text-sm text-destructive"
                                                            >
                                                                {{
                                                                    errors[
                                                                        `phase_details.${index}.form_access_control_id`
                                                                    ]
                                                                }}
                                                            </p>
                                                        </div>

                                                        <!-- Phase Type -->
                                                        <div class="space-y-2">
                                                            <Label
                                                                >Tipe Tahap
                                                                *</Label
                                                            >
                                                            <Select
                                                                v-model="
                                                                    detail.phase_type_id
                                                                "
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue
                                                                        placeholder="Pilih tipe tahap"
                                                                    />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem
                                                                        v-for="phaseType in props.phaseTypes"
                                                                        :key="
                                                                            phaseType.id
                                                                        "
                                                                        :value="
                                                                            phaseType.id
                                                                        "
                                                                    >
                                                                        {{
                                                                            phaseType.name
                                                                        }}
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                            <p
                                                                v-if="
                                                                    errors[
                                                                        `phase_details.${index}.phase_type_id`
                                                                    ]
                                                                "
                                                                class="text-sm text-destructive"
                                                            >
                                                                {{
                                                                    errors[
                                                                        `phase_details.${index}.phase_type_id`
                                                                    ]
                                                                }}
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <!-- Needs Review switch -->
                                                    <div
                                                        class="flex items-center space-x-2"
                                                    >
                                                        <Switch
                                                            v-model="
                                                                detail.needs_review
                                                            "
                                                        />
                                                        <Label
                                                            >Perlu Review</Label
                                                        >
                                                    </div>

                                                    <!-- Order Display -->
                                                    <div
                                                        class="flex items-center gap-2 flex-wrap"
                                                    >
                                                        <Badge
                                                            v-if="getGroupedOrderForDetail(index)"
                                                            variant="default"
                                                        >
                                                            Urutan:
                                                            {{ getGroupedOrderForDetail(index) }}
                                                        </Badge>
                                                        <Badge
                                                            v-else
                                                            variant="outline"
                                                        >
                                                            Urutan: -
                                                        </Badge>
                                                        <Badge
                                                            v-if="hasSameFormAsOthers(index)"
                                                            variant="secondary"
                                                            class="text-xs"
                                                        >
                                                            ↔ Digabung (form sama)
                                                        </Badge>
                                                        <Badge
                                                            v-if="detail.id"
                                                            variant="outline"
                                                            class="text-xs"
                                                        >
                                                            Telah Ada
                                                        </Badge>
                                                        <Badge
                                                            v-else
                                                            variant="default"
                                                            class="text-xs"
                                                        >
                                                            Baru
                                                        </Badge>
                                                    </div>

                                                    <!-- Preview Info -->
                                                    <div
                                                        v-if="
                                                            detail.form_access_control_id &&
                                                            detail.phase_type_id
                                                        "
                                                        class="mt-4 p-3 bg-muted rounded-lg"
                                                    >
                                                        <Label
                                                            class="text-sm text-muted-foreground mb-2 block"
                                                        >
                                                            Pratinjau:
                                                        </Label>
                                                        <div
                                                            v-if="
                                                                getFormAccessControlInfo(
                                                                    detail.form_access_control_id
                                                                )
                                                            "
                                                            class="space-y-1 text-sm"
                                                        >
                                                            <div>
                                                                <strong
                                                                    >Formulir:</strong
                                                                >
                                                                {{
                                                                    getFormAccessControlInfo(
                                                                        detail.form_access_control_id
                                                                    )?.form
                                                                        .title
                                                                }}
                                                            </div>
                                                            <div>
                                                                <strong
                                                                    >Role:</strong
                                                                >
                                                                {{
                                                                    getFormAccessControlInfo(
                                                                        detail.form_access_control_id
                                                                    )?.role.name
                                                                }}
                                                            </div>
                                                            <div>
                                                                <strong
                                                                    >Program
                                                                    Studi:</strong
                                                                >
                                                                {{
                                                                    getFormAccessControlInfo(
                                                                        detail.form_access_control_id
                                                                    )
                                                                        ?.study_program
                                                                        .name
                                                                }}
                                                            </div>
                                                            <div>
                                                                <strong
                                                                    >Tipe
                                                                    Tahap:</strong
                                                                >
                                                                {{
                                                                    getPhaseTypeInfo(
                                                                        detail.phase_type_id
                                                                    )?.name
                                                                }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    @click="
                                                        removePhaseDetail(index)
                                                    "
                                                    class="text-destructive hover:text-destructive"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </template>
                            </draggable>

                            <!-- Add Another Detail Button -->
                            <div class="flex justify-center pt-4">
                                <Button
                                    type="button"
                                    @click="addPhaseDetail"
                                    variant="outline"
                                    size="sm"
                                    class="w-full max-w-xs"
                                >
                                    <Plus class="h-4 w-4 mr-2" />
                                    Tambah Detail Tahap Lain
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="
                            $inertia.visit(route('admin.form-phases.index'))
                        "
                    >
                        Batal
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing
                                ? "Memperbarui..."
                                : "Perbarui Tahap Formulir"
                        }}
                    </Button>
                </div>

                <!-- Warning for existing data -->
                <Card class="border-amber-200 bg-amber-50">
                    <CardContent class="p-4">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex-shrink-0 w-5 h-5 rounded-full bg-amber-400 flex items-center justify-center mt-0.5"
                            >
                                <span class="text-amber-800 text-xs font-bold"
                                    >!</span
                                >
                            </div>
                            <div>
                                <h4
                                    class="text-amber-800 font-medium text-sm mb-1"
                                >
                                    Peringatan Pembaruan
                                </h4>
                                <p class="text-amber-700 text-sm">
                                    Mengubah detail fase akan memengaruhi semua
                                    pengguna yang memiliki akses ke fase ini.
                                    Pastikan untuk berkoordinasi dengan pemangku
                                    kepentingan terkait sebelum melakukan
                                    perubahan.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
