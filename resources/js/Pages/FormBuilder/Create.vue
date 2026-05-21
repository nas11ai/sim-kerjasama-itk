<!-- resources/js/Pages/FormBuilder/Create.vue -->
<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Progress } from '@/Components/ui/progress';
import { CheckCircle2, Circle, ArrowLeft, ArrowRight, Save } from 'lucide-vue-next';

// Step Components
import Step1BasicForm from './Steps/Step1BasicForm.vue';
import Step2AccessControl from './Steps/Step2AccessControl.vue';
import Step3FormPhase from './Steps/Step3FormPhase.vue';
import Step4ReviewSettings from './Steps/Step4ReviewSettings.vue';
import Step5SubmissionPeriod from './Steps/Step5SubmissionPeriod.vue';
import Step6Review from './Steps/Step6Review.vue';

interface Props {
    formTypes: any[];
    fieldTypes: any[];
    roles: any[];
    faculties: any[];
    phaseTypes: any[];
    formPhases: any[];
    submissionPeriods: any[];
}

const props = defineProps<Props>();

const currentStep = ref(1);
const totalSteps = 6;

const form = useForm({
    form: {
        title: '',
        description: '',
        form_type_id: null,
        is_active: true,
        fields: [],
    },
    access_controls: [],
    phase: {
        use_existing: false,
        existing_phase_id: null,
        new_phase_title: '',
        new_phase_description: '',
        phase_type_id: null,
        needs_review: false as boolean,
    },
    evaluation_forms: [],
    submission_period: {
        use_existing: true,
        existing_period_id: null,
        new_period_name: '',
        dates: [],
    },
});

const steps = [
    { number: 1, title: 'Form Dasar', description: 'Buat struktur formulir' },
    { number: 2, title: 'Kontrol Akses', description: 'Atur izin akses' },
    { number: 3, title: 'Tahap Formulir', description: 'Konfigurasi tahap' },
    { number: 4, title: 'Pengaturan Review', description: 'Atur penilaian' },
    { number: 5, title: 'Periode Pengajuan', description: 'Atur timeline' },
    { number: 6, title: 'Review & Submit', description: 'Konfirmasi detail' },
];

const progress = computed(() => (currentStep.value / totalSteps) * 100);

const canGoNext = computed(() => {
    switch (currentStep.value) {
        case 1:
            return form.form.title && form.form.form_type_id;
        case 2:
            return form.access_controls.length > 0;
        case 3:
            if (form.phase.use_existing) {
                return form.phase.existing_phase_id && form.phase.phase_type_id;
            }
            return form.phase.new_phase_title && form.phase.phase_type_id;
        case 4:
            if (!form.phase.needs_review) return true;
            return form.evaluation_forms.length > 0;
        case 5:
            if (form.submission_period.use_existing) {
                return form.submission_period.existing_period_id;
            }
            return form.submission_period.new_period_name && form.submission_period.dates.length > 0;
        default:
            return true;
    }
});

const nextStep = () => {
    if (currentStep.value < totalSteps && canGoNext.value) {
        currentStep.value++;
    }
};

const prevStep = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
};

const goToStep = (step: number) => {
    // Only allow going to completed steps or next available step
    if (step <= currentStep.value || (step === currentStep.value + 1 && canGoNext.value)) {
        currentStep.value = step;
    }
};

const submit = () => {
    form.post(route('admin.form-builder.store'));
};
</script>

<template>
  <Head title="Penyusun Formulir Lengkap" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Button
          variant="ghost"
          size="sm"
          @click="$inertia.visit(route('admin.forms.index'))"
        >
          <ArrowLeft class="h-4 w-4 mr-2" />
          Kembali
        </Button>
        <div>
          <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Penyusun Formulir Lengkap
          </h2>
          <p class="text-sm text-muted-foreground">
            Buat formulir lengkap dengan seluruh konfigurasi dalam satu tempat
          </p>
        </div>
      </div>
    </template>

    <div class="max-w-6xl mx-auto space-y-6">
      <!-- Progress Bar -->
      <Card>
        <CardContent class="pt-6">
          <div class="space-y-4">
            <div class="flex items-center justify-between text-sm">
              <span class="font-medium">Langkah {{ currentStep }} dari {{ totalSteps }}</span>
              <span class="text-muted-foreground">{{ Math.round(progress) }}% Selesai</span>
            </div>
            <Progress
              :model-value="progress"
              class="h-2"
            />
          </div>
        </CardContent>
      </Card>

      <!-- Step Navigation -->
      <Card>
        <CardContent class="p-6">
          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div
              v-for="step in steps"
              :key="step.number"
              :class="[
                'relative p-4 rounded-lg border-2 transition-all cursor-pointer',
                currentStep === step.number
                  ? 'border-primary bg-primary/5'
                  : step.number < currentStep
                    ? 'border-green-500 bg-green-50 hover:bg-green-100'
                    : 'border-gray-200 hover:border-gray-300',
                step.number > currentStep && !canGoNext ? 'opacity-50 cursor-not-allowed' : ''
              ]"
              @click="goToStep(step.number)"
            >
              <div class="flex items-start space-x-3">
                <div class="shrink-0">
                  <CheckCircle2
                    v-if="step.number < currentStep"
                    class="h-5 w-5 text-green-600"
                  />
                  <Circle
                    v-else
                    :class="[
                      'h-5 w-5',
                      currentStep === step.number ? 'text-primary' : 'text-gray-400'
                    ]"
                  />
                </div>
                <div class="flex-1 min-w-0">
                  <p
                    :class="[
                      'text-sm font-medium',
                      currentStep === step.number ? 'text-primary' : 'text-gray-900'
                    ]"
                  >
                    {{ step.title }}
                  </p>
                  <p class="text-xs text-muted-foreground mt-1">
                    {{ step.description }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Step Content -->
      <Card>
        <CardHeader>
          <CardTitle>{{ steps[currentStep - 1].title }}</CardTitle>
          <CardDescription>{{ steps[currentStep - 1].description }}</CardDescription>
        </CardHeader>
        <CardContent class="min-h-[400px]">
          <Step1BasicForm
            v-if="currentStep === 1"
            v-model="form.form"
            :form-types="formTypes"
            :field-types="fieldTypes"
            :errors="form.errors"
          />

          <Step2AccessControl
            v-if="currentStep === 2"
            v-model="form.access_controls"
            :roles="roles"
            :faculties="faculties"
            :errors="form.errors"
          />

          <Step3FormPhase
            v-if="currentStep === 3"
            v-model="form.phase"
            :form-phases="formPhases"
            :phase-types="phaseTypes"
            :errors="form.errors"
          />

          <Step4ReviewSettings
            v-if="currentStep === 4"
            v-model="form.evaluation_forms"
            :needs-review="form.phase.needs_review"
            :field-types="fieldTypes"
            :errors="form.errors"
            @update:needs-review="form.phase.needs_review = $event"
          />

          <Step5SubmissionPeriod
            v-if="currentStep === 5"
            v-model="form.submission_period"
            :submission-periods="submissionPeriods"
            :errors="form.errors"
          />

          <Step6Review
            v-if="currentStep === 6"
            :form-data="form"
            :form-types="formTypes"
            :roles="roles"
            :faculties="faculties"
            :phase-types="phaseTypes"
            :form-phases="formPhases"
            :submission-periods="submissionPeriods"
          />
        </CardContent>
      </Card>

      <!-- Navigation Buttons -->
      <Card>
        <CardContent class="p-4">
          <div class="flex items-center justify-between">
            <Button
              v-if="currentStep > 1"
              variant="outline"
              @click="prevStep"
            >
              <ArrowLeft class="h-4 w-4 mr-2" />
              Sebelumnya
            </Button>
            <div v-else />

            <div class="flex items-center space-x-2">
              <Button
                v-if="currentStep < totalSteps"
                :disabled="!canGoNext"
                @click="nextStep"
              >
                Berikutnya
                <ArrowRight class="h-4 w-4 ml-2" />
              </Button>

              <Button
                v-if="currentStep === totalSteps"
                :disabled="form.processing"
                class="bg-green-600 hover:bg-green-700"
                @click="submit"
              >
                <Save class="h-4 w-4 mr-2" />
                {{ form.processing ? 'Membuat...' : 'Buat Formulir Lengkap' }}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AuthenticatedLayout>
</template>
