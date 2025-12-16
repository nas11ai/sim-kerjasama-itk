<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Separator } from '@/Components/ui/separator';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import {
    CheckCircle2,
    FileText,
    Users,
    Layers,
    Calendar,
    ClipboardList,
    AlertCircle,
    Shield,
    Building,
} from 'lucide-vue-next';

interface Props {
    formData: any;
    formTypes: any[];
    roles: any[];
    faculties: any[];
    phaseTypes: any[];
    formPhases: any[];
    submissionPeriods: any[];
}

const props = defineProps<Props>();

const getFormTypeName = (id: number | null) => {
    if (!id) return 'Not selected';
    return props.formTypes.find((ft) => ft.id === id)?.name || 'Unknown';
};

const getRoleName = (id: number) => {
    return props.roles.find((r) => r.id === id)?.name || 'Unknown';
};

const getStudyProgramInfo = (id: number) => {
    for (const faculty of props.faculties) {
        const sp = faculty.study_programs.find((s: any) => s.id === id);
        if (sp) return { name: sp.name, faculty: faculty.name };
    }
    return { name: 'Unknown', faculty: 'Unknown' };
};

const getPhaseTypeName = (id: number | null) => {
    if (!id) return 'Not selected';
    return props.phaseTypes.find((pt) => pt.id === id)?.name || 'Unknown';
};

const getPhaseName = (id: number | null) => {
    if (!id) return null;
    return props.formPhases.find((p) => p.id === id)?.title || 'Unknown';
};

const getPeriodName = (id: number | null) => {
    if (!id) return null;
    return props.submissionPeriods.find((p) => p.id === id)?.name || 'Unknown';
};

const validationIssues = computed(() => {
    const issues: string[] = [];

    if (!props.formData.form.title) issues.push('Judul formulir wajib diisi');
    if (!props.formData.form.form_type_id) issues.push('Jenis formulir wajib diisi');
    if (props.formData.access_controls.length === 0) issues.push('Minimal satu kontrol akses ditambahkan');
    if (!props.formData.phase.phase_type_id) issues.push('Jenis fase wajib diisi');

    if (props.formData.phase.use_existing) {
        if (!props.formData.phase.existing_phase_id) issues.push('Tahap yang sudah ada harus dipilih');
    } else {
        if (!props.formData.phase.new_phase_title) issues.push('Judul tahap baru wajib diisi');
    }

    if (props.formData.phase.needs_review && props.formData.evaluation_forms.length === 0) {
        issues.push('Minimal satu formulir evaluasi wajib ditambahkan jika review diaktifkan');
    }

    if (props.formData.submission_period.use_existing) {
        if (!props.formData.submission_period.existing_period_id) issues.push('Periode yang sudah ada harus dipilih');
    } else {
        if (!props.formData.submission_period.new_period_name) issues.push('Judul periode baru wajib diisi');
        if (props.formData.submission_period.dates.length === 0) issues.push('Minimal satu tanggal pengajuan diperlukan');
    }

    return issues;
});

const isValid = computed(() => validationIssues.value.length === 0);

const totalFields = computed(() => props.formData.form.fields.length);
const totalAccessControls = computed(() => props.formData.access_controls.length);
const totalEvaluationForms = computed(() => props.formData.evaluation_forms.length);
const totalEvaluationFields = computed(() =>
    props.formData.evaluation_forms.reduce((sum: number, form: any) => sum + form.fields.length, 0)
);
</script>

<template>
    <div class="space-y-6">
        <!-- Validation Status -->
        <Alert v-if="!isValid" variant="destructive">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>
                <div class="font-medium mb-2">Silakan perbaiki masalah berikut:</div>
                <ul class="list-disc list-inside space-y-1">
                    <li v-for="(issue, index) in validationIssues" :key="index" class="text-sm">
                        {{ issue }}
                    </li>
                </ul>
            </AlertDescription>
        </Alert>

        <Alert v-else class="border-green-200 bg-green-50">
            <CheckCircle2 class="h-4 w-4 text-green-600" />
            <AlertDescription class="text-green-800">
                Semua kolom wajib telah terisi. Formulir siap dibuat!
            </AlertDescription>
        </Alert>

        <!-- Summary Cards -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardContent class="p-6">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <FileText class="h-6 w-6 text-blue-600" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ totalFields }}</p>
                            <p class="text-sm text-muted-foreground">Isian Formulir</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-6">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <Shield class="h-6 w-6 text-purple-600" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ totalAccessControls }}</p>
                            <p class="text-sm text-muted-foreground">Kontrol Akses</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-6">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <ClipboardList class="h-6 w-6 text-green-600" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ totalEvaluationForms }}</p>
                            <p class="text-sm text-muted-foreground">Formulir Evaluasi</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-6">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-orange-100 rounded-lg">
                            <CheckCircle2 class="h-6 w-6 text-orange-600" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ totalEvaluationFields }}</p>
                            <p class="text-sm text-muted-foreground">Isian Evaluasi</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Step 1: Basic Form -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <FileText class="h-5 w-5" />
                    1. Informasi Dasar Formulir
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <p class="text-sm text-muted-foreground">Judul</p>
                        <p class="font-medium">{{ formData.form.title || 'Belum diisi' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Tipe</p>
                        <Badge variant="outline">{{ getFormTypeName(formData.form.form_type_id) }}</Badge>
                    </div>
                </div>
                <div v-if="formData.form.description">
                    <p class="text-sm text-muted-foreground">Deskripsi</p>
                    <p class="text-sm">{{ formData.form.description }}</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Status</p>
                    <Badge :variant="formData.form.is_active ? 'default' : 'secondary'">
                        {{ formData.form.is_active ? 'Aktif' : 'Tidak Aktif' }}
                    </Badge>
                </div>
                <Separator />
                <div>
                    <p class="text-sm text-muted-foreground mb-2">Isian Formulir ({{ formData.form.fields.length }})</p>
                    <div v-if="formData.form.fields.length > 0" class="space-y-2">
                        <div v-for="(field, index) in formData.form.fields" :key="field.temp_id"
                            class="flex items-center justify-between p-2 bg-muted rounded text-sm">
                            <span>{{ index + 1 }}. {{ field.label }}</span>
                            <Badge v-if="field.is_required" variant="destructive" class="text-xs">Wajib</Badge>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">Belum ada isian</p>
                </div>
            </CardContent>
        </Card>

        <!-- Step 2: Access Control -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="h-5 w-5" />
                    2. Kontrol Akses
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="formData.access_controls.length > 0" class="space-y-2">
                    <div v-for="(control, index) in formData.access_controls" :key="control.temp_id"
                        class="flex items-center justify-between p-3 bg-muted rounded">
                        <div class="flex items-center gap-3">
                            <Badge variant="outline">{{ index + 1 }}</Badge>
                            <div class="flex items-center gap-2 text-sm">
                                <Users class="h-4 w-4 text-muted-foreground" />
                                <span class="font-medium">{{ getRoleName(control.role_id) }}</span>
                                <span class="text-muted-foreground">→</span>
                                <Building class="h-4 w-4 text-muted-foreground" />
                                <span>{{ getStudyProgramInfo(control.study_program_id).name }}</span>
                                <span class="text-xs text-muted-foreground">
                                    ({{ getStudyProgramInfo(control.study_program_id).faculty }})
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">Belum ada kontrol akses yang diatur</p>
            </CardContent>
        </Card>

        <!-- Step 3: Form Phase -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Layers class="h-5 w-5" />
                    3. Konfigurasi Tahap Formulir
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <p class="text-sm text-muted-foreground">Mode</p>
                        <Badge variant="outline">
                            {{ formData.phase.use_existing ? 'Menggunakan Tahap yang Ada' : 'Membuat Tahap Baru' }}
                        </Badge>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Tipe Tahap</p>
                        <Badge variant="outline">{{ getPhaseTypeName(formData.phase.phase_type_id) }}</Badge>
                    </div>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">
                        {{ formData.phase.use_existing ? 'Tahap Terpilih' : 'Judul Tahap Baru' }}
                    </p>
                    <p class="font-medium">
                        {{ formData.phase.use_existing
                            ? getPhaseName(formData.phase.existing_phase_id) || 'Belum dipilih'
                            : formData.phase.new_phase_title || 'Belum diatur'
                        }}
                    </p>
                </div>
                <div v-if="!formData.phase.use_existing && formData.phase.new_phase_description">
                    <p class="text-sm text-muted-foreground">Deskripsi</p>
                    <p class="text-sm">{{ formData.phase.new_phase_description }}</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Perlu Review</p>
                    <Badge :variant="formData.phase.needs_review ? 'default' : 'secondary'">
                        {{ formData.phase.needs_review ? 'Ya' : 'Tidak' }}
                    </Badge>
                </div>
            </CardContent>
        </Card>

        <!-- Step 4: Review Settings -->
        <Card v-if="formData.phase.needs_review">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <ClipboardList class="h-5 w-5" />
                    4. Formulir Evaluasi
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="formData.evaluation_forms.length > 0" class="space-y-3">
                    <div v-for="(evalForm, index) in formData.evaluation_forms" :key="evalForm.temp_id"
                        class="p-4 border rounded-lg space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Badge variant="outline">{{ index + 1 }}</Badge>
                                <span class="font-medium">{{ evalForm.title }}</span>
                            </div>
                            <Badge :variant="evalForm.is_required ? 'destructive' : 'secondary'" class="text-xs">
                                {{ evalForm.is_required ? 'Wajib' : 'Opsional' }}
                            </Badge>
                        </div>
                        <p v-if="evalForm.description" class="text-sm text-muted-foreground">{{ evalForm.description }}
                        </p>
                        <div class="text-sm text-muted-foreground">
                            {{ evalForm.fields.length }} tahap
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">Belum ada formulir evaluasi yang diatur</p>
            </CardContent>
        </Card>

        <!-- Step 5: Submission Period -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Calendar class="h-5 w-5" />
                    5. Periode Pengajuan
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div>
                    <p class="text-sm text-muted-foreground">Mode</p>
                    <Badge variant="outline">
                        {{ formData.submission_period.use_existing ? 'Menggunakan Periode yang Ada' : 'Membuat Periode Baru' }}
                    </Badge>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">
                        {{ formData.submission_period.use_existing ? 'Periode Terpilih' : 'Nama Periode Baru' }}
                    </p>
                    <p class="font-medium">
                        {{ formData.submission_period.use_existing
                            ? getPeriodName(formData.submission_period.existing_period_id) || 'Belum dipilih'
                            : formData.submission_period.new_period_name || 'Belum diatur'
                        }}
                    </p>
                </div>
                <div v-if="!formData.submission_period.use_existing && formData.submission_period.dates.length > 0">
                    <p class="text-sm text-muted-foreground mb-2">Tanggal yang Dikonfigurasi</p>
                    <div class="space-y-1">
                        <div v-for="date in formData.submission_period.dates" :key="date.temp_id"
                            class="text-sm flex items-center justify-between p-2 bg-muted rounded">
                            <span class="font-medium">{{ date.label }}</span>
                            <span class="text-muted-foreground">{{ date.date || 'Belum diatur' }}</span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Final Note -->
        <Alert>
            <CheckCircle2 class="h-4 w-4" />
            <AlertDescription>
                <div class="font-medium mb-1">Siap untuk Membuat</div>
                <p class="text-sm">
                    Mohon tinjau semua informasi di atas. Setelah dibuat, Anda masih dapat mengedit komponen individual,
                    tetapi lebih mudah untuk melakukannya dengan benar pada percobaan pertama.
                </p>
            </AlertDescription>
        </Alert>
    </div>
</template>

