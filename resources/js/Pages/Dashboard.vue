<script setup lang="ts">
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import type {
    FormPhaseStats,
    FormSubmissionStats,
    UserStats,
    SubmissionReviewerStats
} from "@/types/statistics";
import StatisticSection from "@/Components/StatisticSection.vue";

const props = defineProps<{
    formPhase: FormPhaseStats;
    formSubmission: FormSubmissionStats;
    user: UserStats;
    submissionReviewer: SubmissionReviewerStats;
}>();

</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <h2 class="text-xl font-semibold leading-tight text-foreground">
                    Dashboard
                </h2>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Welcome Card -->
            <div>
                <h3
                    class="text-3xl font-semibold leading-none tracking-tight mb-1"
                >
                    Welcome to SIM Kerjasama ITK!
                </h3>
                <p class="text-lg text-muted-foreground">
                    You're successfully logged in to your dashboard.
                </p>
            </div>

            <StatisticSection
                :form-phase="props.formPhase"
                :form-submission="props.formSubmission"
                :user="props.user"
                :submission-reviewer="props.submissionReviewer"
            />
        </div>
    </AuthenticatedLayout>
</template>
