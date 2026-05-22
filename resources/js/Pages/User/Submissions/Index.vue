<!-- resources/js/Pages/Submissions/UserIndex.vue -->
<script setup lang="ts">
import { computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import {
    FileText,
    Calendar,
    CheckCircle,
    Clock,
    AlertCircle,
    Eye
} from "lucide-vue-next";

interface SubmissionDate {
    id: number;
    datetime: string;
    submission_date_label: {
        name: string;
    };
}

interface SubmissionPeriod {
    id: number;
    name: string;
    created_at: string;
    submission_dates: SubmissionDate[];
    user_submissions_count: number;
    user_draft_count: number;
    user_submitted_count: number;
}

interface StudyProgram {
    id: number;
    name: string;
    faculty: {
        name: string;
    };
}

interface Props {
    submissionPeriods: SubmissionPeriod[];
    userRole: string;
    studyProgram: StudyProgram | null;
}

const props = defineProps<Props>();

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("id-ID", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });
};

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString("id-ID", {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusBadgeVariant = (submitted: number, draft: number) => {
    if (submitted > 0 && draft === 0) return 'default'; // All submitted
    if (submitted > 0 && draft > 0) return 'secondary'; // Mixed
    if (draft > 0) return 'destructive'; // Only drafts
    return 'outline'; // No submissions
};

const getStatusText = (submitted: number, draft: number, total: number) => {
    if (total === 0) return 'Tidak Ada Pengajuan';
    if (submitted === total) return 'Semua Pengajuan Dikirim';
    if (submitted > 0) return 'Sebagian Pengajuan Dikirim';
    return 'Hanya Draft';
};

const hasSubmissions = computed(() => {
    return props.submissionPeriods.some(period => period.user_submissions_count > 0);
});
</script>

<template>
  <Head title="Pengajuan Saya" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Pengajuan Formulir Saya
          </h2>
          <p
            v-if="studyProgram"
            class="text-sm text-muted-foreground mt-1"
          >
            {{ studyProgram.faculty.name }} - {{ studyProgram.name }}
          </p>
        </div>
        <Badge
          variant="outline"
          class="capitalize"
        >
          {{ userRole }}
        </Badge>
      </div>
    </template>

    <div class="space-y-6">
      <!-- Welcome Section -->
      <Card v-if="!hasSubmissions">
        <CardContent class="text-center py-12">
          <FileText class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
          <h3 class="text-lg font-medium text-gray-900 mb-2">
            Belum Ada Pengajuan
          </h3>
          <p class="text-sm text-muted-foreground mb-4">
            Anda belum mengajukan formulir apapun. Kunjungi dashboard untuk mulai mengisi formulir.
          </p>
          <Link :href="route('user.dashboard')">
            <Button>
              <FileText class="h-4 w-4 mr-2" />
              Pergi ke Dashboard
            </Button>
          </Link>
        </CardContent>
      </Card>

      <!-- Submission Periods -->
      <div
        v-else
        class="space-y-6"
      >
        <div
          v-for="period in submissionPeriods.filter(p => p.user_submissions_count > 0)"
          :key="period.id"
          class="space-y-4"
        >
          <Card class="hover:shadow-lg transition-shadow">
            <CardHeader>
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <CardTitle class="text-lg flex items-center gap-2">
                    <Calendar class="h-5 w-5" />
                    {{ period.name }}
                  </CardTitle>
                  <CardDescription class="mt-1">
                    Dibuat Pada {{ formatDate(period.created_at) }}
                  </CardDescription>
                </div>
                <div class="flex items-center gap-2">
                  <Badge
                    :variant="getStatusBadgeVariant(period.user_submitted_count, period.user_draft_count)"
                  >
                    {{ getStatusText(period.user_submitted_count, period.user_draft_count,
                                     period.user_submissions_count) }}
                  </Badge>
                </div>
              </div>
            </CardHeader>

            <CardContent>
              <div class="space-y-4">
                <!-- Important Dates -->
                <div v-if="period.submission_dates.length > 0">
                  <h4 class="font-medium text-sm mb-2 flex items-center gap-2">
                    <Clock class="h-4 w-4" />
                    Tanggal Penting
                  </h4>
                  <div class="grid gap-2 sm:grid-cols-2">
                    <div
                      v-for="date in period.submission_dates.slice(0, 4)"
                      :key="date.id"
                      class="text-xs p-2 bg-muted/50 rounded"
                    >
                      <div class="font-medium">
                        {{ date.submission_date_label.name }}
                      </div>
                      <div class="text-muted-foreground">
                        {{ formatDateTime(date.datetime) }}
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Submission Statistics -->
                <div class="grid grid-cols-3 gap-4 text-center">
                  <div class="p-3 bg-blue-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">
                      {{ period.user_submissions_count
                      }}
                    </div>
                    <div class="text-xs text-blue-600">
                      Total Formulir
                    </div>
                  </div>
                  <div class="p-3 bg-green-50 rounded-lg">
                    <div
                      class="text-2xl font-bold text-green-600 flex items-center justify-center gap-1"
                    >
                      {{ period.user_submitted_count }}
                      <CheckCircle class="h-4 w-4" />
                    </div>
                    <div class="text-xs text-green-600">
                      Pengajuan dikirim
                    </div>
                  </div>
                  <div class="p-3 bg-yellow-50 rounded-lg">
                    <div
                      class="text-2xl font-bold text-yellow-600 flex items-center justify-center gap-1"
                    >
                      {{ period.user_draft_count }}
                      <AlertCircle class="h-4 w-4" />
                    </div>
                    <div class="text-xs text-yellow-600">
                      Draft
                    </div>
                  </div>
                </div>

                <!-- Action Button -->
                <div class="flex justify-end pt-2">
                  <Link :href="route('user.submissions.period', period.id)">
                    <Button size="sm">
                      <Eye class="h-4 w-4 mr-2" />
                      Lihat Detail
                    </Button>
                  </Link>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <!-- All Submission Periods (if no submissions) -->
      <div
        v-if="!hasSubmissions && submissionPeriods.length > 0"
        class="mt-8"
      >
        <h3 class="text-lg font-semibold mb-4">
          Periode Pengajuan Tersedia
        </h3>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          <Card
            v-for="period in submissionPeriods"
            :key="period.id"
            class="hover:shadow-lg transition-shadow opacity-75"
          >
            <CardHeader class="pb-3">
              <CardTitle class="text-base">
                {{ period.name }}
              </CardTitle>
              <CardDescription class="text-xs">
                Dibuat Pada {{ formatDate(period.created_at) }}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <p class="text-xs text-muted-foreground mb-3">
                Belum Ada Pengajuan untuk periode ini
              </p>
              <Link :href="route('user.dashboard')">
                <Button
                  size="sm"
                  variant="outline"
                  class="w-full"
                >
                  Mulai Mengajukan
                </Button>
              </Link>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
