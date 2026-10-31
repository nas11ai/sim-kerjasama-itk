<script setup lang="ts">
import AppStepper from '@/Components/app/AppStepper.vue'
import AnswersStep from '@/Components/Submission/Wizard/AnswersStep.vue'
import BudgetStep from '@/Components/Submission/Wizard/BudgetStep.vue'
import MembersStep from '@/Components/Submission/Wizard/MembersStep.vue'
import ReviewStep from '@/Components/Submission/Wizard/ReviewStep.vue'
import SchemeStep from '@/Components/Submission/Wizard/SchemeStep.vue'
import StartStep from '@/Components/Submission/Wizard/StartStep.vue'
import WizardFooter from '@/Components/Submission/Wizard/WizardFooter.vue'
import { useWizardStep } from '@/composables/useWizardStep'
import type {
    FilterOption,
    SubmissionAbilities,
    SubmissionTargetOutput,
} from '@/types/submission-page'
import type {
    MemberCandidate,
    WizardBudget,
    WizardField,
    WizardFieldValue,
    WizardMembers,
    WizardReview,
    WizardStep,
    WizardSubmission,
} from '@/types/submission-wizard'

// Satu langkah draft yang sudah ada. Pemanggil memberinya `key` per langkah: Inertia memakai ulang
// instance halaman, jadi tanpa itu isian langkah sebelumnya ikut terkirim ke langkah berikutnya.
const props = defineProps<{
    step: number
    steps: WizardStep[]
    label: string
    submission: WizardSubmission
    fields: WizardField[]
    targetOutputs: SubmissionTargetOutput[]
    targetOutputTypes: FilterOption[]
    review: WizardReview | null
    members: WizardMembers | null
    budget: WizardBudget | null
    memberCandidates?: MemberCandidate[]
    can: SubmissionAbilities
}>()

const { values, go, processing, errors } = useWizardStep(props.submission, props.step, props.fields)

const update = (fieldId: number, value: WizardFieldValue) => (values[fieldId] = value)
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
        <AppStepper :steps="steps" :active="step" :disabled="processing" @select="go" />
        <div class="min-w-0">
            <StartStep v-if="step === 1" :submission="submission" :options="[]" />
            <AnswersStep
                v-else-if="step === 2 || step === 6"
                :step="step"
                :label="label"
                :fields="fields"
                :values="values"
                :errors="errors"
                @update="update"
            />
            <SchemeStep
                v-else-if="step === 3"
                :fields="fields"
                :values="values"
                :errors="errors"
                :submission-id="submission.id"
                :target-outputs="targetOutputs"
                :target-output-types="targetOutputTypes"
                @update="update"
            />
            <MembersStep
                v-else-if="step === 4 && members"
                :submission-id="submission.id"
                :members="members"
                :candidates="memberCandidates"
            />
            <BudgetStep
                v-else-if="step === 5 && budget"
                :submission-id="submission.id"
                :budget="budget"
                @goto="go"
            />
            <ReviewStep
                v-else-if="step === 7 && review"
                :submission="submission"
                :review="review"
                :can="can"
                @goto="go"
            />
            <WizardFooter
                :step="step"
                :saved-at="submission.lastUpdatedAt"
                :processing="processing"
                :show-next="step < 7"
                @back="go(step - 1)"
                @next="go(step + 1)"
            />
        </div>
    </div>
</template>
