<script setup lang="ts">
import { computed, ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Badge } from "@/Components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import {
    Plus,
    Search,
    Eye,
    Edit,
    Trash2,
    MoreHorizontal,
    Calendar,
    Clock,
    FileText,
    Settings,
    AlertCircle,
} from "lucide-vue-next";

interface SubmissionDate {
    id: number;
    label: string;
    datetime: string;
}

interface FormPhase {
    id: number;
    title: string;
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
    start_date?: string;
    end_date?: string;
    is_active: boolean;
    status: "upcoming" | "active" | "expired" | "no_dates";
    submission_dates: SubmissionDate[];
    submission_period_phases: SubmissionPeriodPhase[];
    submission_period_details: SubmissionPeriodDetail[];
}

interface PaginatedData {
    data: SubmissionPeriod[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Filters {
    search?: string;
}

interface Props {
    submissionPeriods: PaginatedData;
    filters: Filters;
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || "");

const filteredSubmissionPeriods = computed(() => {
    if (!searchQuery.value) return props.submissionPeriods.data;

    return props.submissionPeriods.data.filter((period) =>
        period.name.toLowerCase().includes(searchQuery.value.toLowerCase())
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
            return "Aktif";
        case "upcoming":
            return "Akan Datang";
        case "expired":
            return "Kedaluwarsa";
        case "no_dates":
            return "Tidak ada tanggal";
        default:
            return "Tidak Diketahui";
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case "active":
            return Clock;
        case "upcoming":
            return Calendar;
        case "expired":
            return AlertCircle;
        case "no_dates":
            return AlertCircle;
        default:
            return AlertCircle;
    }
};

const searchPeriods = () => {
    router.get(
        route("admin.submission-periods.index"),
        { search: searchQuery.value },
        {
            preserveState: true,
            replace: true,
        }
    );
};

const clearSearch = () => {
    searchQuery.value = "";
    router.get(
        route("admin.submission-periods.index"),
        {},
        {
            preserveState: true,
            replace: true,
        }
    );
};

const deleteSubmissionPeriod = (id: number) => {
    if (
        confirm(
            "Apakah anda yakin untuk menghapus periode pengajuan ini? Tindakan ini tidak dapat dibatalkan."
        )
    ) {
        router.delete(route("admin.submission-periods.destroy", id));
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};
</script>

<template>

    <Head title="Periode Pengajuan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Manajemen Periode Pengajuan
                </h2>
                <Link :href="route('admin.submission-periods.create')">
                <Button>
                    <Plus class="h-4 w-4 mr-2" />
                    Buat Periode Pengajuan
                </Button>
                </Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Search -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Search class="h-5 w-5" />
                        Cari Periode Pengajuan
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-4">
                        <div class="flex w-full">
                            <Input v-model="searchQuery" placeholder="Cari periode pengajuan berdasarkan nama..."
                                class="max-w-full" @keyup.enter="searchPeriods" />
                        </div>
                        <Button @click="searchPeriods">
                            <Search class="h-4 w-4 mr-2" />
                            Cari
                        </Button>
                        <Button v-if="searchQuery" @click="clearSearch" variant="outline">
                            Bersihkan Semua Filter
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Submission Periods Table -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Calendar class="h-5 w-5" />
                        Periode Pengajuan ({{ props.submissionPeriods.total }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nama</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Periode</TableHead>
                                    <TableHead>Tahap Formulir</TableHead>
                                    <TableHead>Ketentuan</TableHead>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead class="text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="period in filteredSubmissionPeriods" :key="period.id">
                                    <TableCell class="font-medium">
                                        <div class="flex items-center gap-2">
                                            <FileText class="h-4 w-4 text-muted-foreground" />
                                            {{ period.name }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getStatusColor(period.status)
                                            " class="flex items-center gap-1 w-fit">
                                            <component :is="getStatusIcon(period.status)
                                                " class="h-3 w-3" />
                                            {{ getStatusText(period.status) }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div v-if="
                                            period.start_date &&
                                            period.end_date
                                        " class="text-sm">
                                            <div>
                                                {{
                                                    formatDate(
                                                        period.start_date
                                                    )
                                                }}
                                            </div>
                                            <div class="text-muted-foreground">
                                                to
                                                {{
                                                    formatDate(period.end_date)
                                                }}
                                            </div>
                                        </div>
                                        <div v-else class="text-sm text-muted-foreground">
                                            Tidak ada tanggal dikonfigurasi
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <Settings class="h-4 w-4 text-muted-foreground" />
                                            <span class="text-sm">
                                                {{
                                                    period
                                                        .submission_period_phases
                                                        .length
                                                }}
                                                tahap
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <FileText class="h-4 w-4 text-muted-foreground" />
                                            <span class="text-sm">
                                                {{
                                                    period
                                                        .submission_period_details
                                                        .length
                                                }}
                                                ketentuan
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <Calendar class="h-4 w-4 text-muted-foreground" />
                                            <span class="text-sm">
                                                {{
                                                    period.submission_dates
                                                        .length
                                                }}
                                                tanggal
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="sm">
                                                    <MoreHorizontal class="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <Link :href="route(
                                                    'admin.submission-periods.show',
                                                    period.id
                                                )
                                                    ">
                                                <DropdownMenuItem>
                                                    <Eye class="h-4 w-4 mr-2" />
                                                    Lihat Detail
                                                </DropdownMenuItem>
                                                </Link>
                                                <Link :href="route(
                                                    'admin.submission-periods.edit',
                                                    period.id
                                                )
                                                    ">
                                                <DropdownMenuItem>
                                                    <Edit class="h-4 w-4 mr-2" />
                                                    Edit
                                                </DropdownMenuItem>
                                                </Link>
                                                <DropdownMenuItem @click="
                                                    deleteSubmissionPeriod(
                                                        period.id
                                                    )
                                                    " class="text-destructive cursor-pointer">
                                                    <Trash2 class="h-4 w-4 mr-2" />
                                                    Hapus
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Empty State -->
                    <div v-if="filteredSubmissionPeriods.length === 0" class="text-center py-12">
                        <Calendar class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <h3 class="text-lg font-medium mb-2">
                            Tidak ada periode pengajuan ditemukan
                        </h3>
                        <p class="text-muted-foreground mb-4">
                            {{
                                searchQuery
                                    ? "Coba sesuaikan kriteria pencarian Anda."
                                    : "Mulailah dengan membuat periode pengajuan pertama Anda."
                            }}
                        </p>
                        <Link :href="route('admin.submission-periods.create')" v-if="!searchQuery">
                        <Button>
                            <Plus class="h-4 w-4 mr-2" />
                            Buat Periode Pengajuan
                        </Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div v-if="props.submissionPeriods.last_page > 1" class="flex justify-center">
                <div class="flex items-center gap-2">
                    <template v-for="link in props.submissionPeriods.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url" :class="[
                            'px-3 py-2 text-sm rounded-md',
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-background border hover:bg-muted',
                        ]" v-html="link.label" />
                        <span v-else :class="[
                            'px-3 py-2 text-sm rounded-md text-muted-foreground',
                            'bg-muted cursor-not-allowed',
                        ]" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
