<script setup lang="ts">
// Frame: RESEARCHER-06-02, RESEARCHER-06-03
import { Head, Link, usePage } from '@inertiajs/vue3'
import { Check } from 'lucide-vue-next'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import OutputFileInput from '@/Components/ResearchOutput/OutputFileInput.vue'
import OutputMetadataFields from '@/Components/ResearchOutput/OutputMetadataFields.vue'
import OutputTypeStep from '@/Components/ResearchOutput/OutputTypeStep.vue'
import { Button } from '@/Components/ui/button'
import { useOutputForm } from '@/composables/useOutputForm'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { ResearchOutputFormPageProps } from '@/types/research-output'

const props = defineProps<ResearchOutputFormPageProps>()

const { form, definition, canSave, changeType, submit } = useOutputForm(props)

// Galat bertingkat (`metadata.title`, `files.0`) dibaca dari prop errors halaman.
const page = usePage()
const errors = computed<Record<string, string>>(() => page.props.errors ?? {})

const title = computed(() => (props.outputId === null ? 'Tambah Luaran' : 'Edit Luaran'))
const subtitle = computed(() => [props.submissionTitle, props.scheme].filter(Boolean).join(' · '))
const cancelHref = computed(() =>
    props.outputId === null
        ? route('research-outputs.index')
        : route('research-outputs.show', props.outputId)
)
</script>

<template>
    <Head :title="title" />
    <AppLayout>
        <PageHeader
            :title="title"
            :subtitle="subtitle"
            :back-href="route('research-outputs.index')"
            back-label="Luaran Penelitian"
        />
        <div class="mx-auto flex max-w-2xl flex-col gap-4">
            <!-- BR-RO-01 — penolakan keadaan induk dari Guard, bukan milik satu field. -->
            <AppBanner v-if="errors.form_submission_id" tone="denial">
                {{ errors.form_submission_id }}
            </AppBanner>
            <OutputTypeStep
                :types="types"
                :code="form.output_type"
                :error="errors.output_type"
                @change="changeType"
            />
            <SectionCard v-if="definition" :title="`Langkah 2: Detail ${definition.name}`">
                <OutputMetadataFields
                    v-model="form.metadata"
                    :fields="definition.fields"
                    :errors="errors"
                />
                <OutputFileInput
                    v-model="form.files"
                    :existing="files"
                    :requires-files="definition.requiresFiles"
                    :errors="errors"
                />
            </SectionCard>
            <div class="flex items-center justify-between gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="cancelHref">Batalkan</Link>
                </Button>
                <Button :disabled="!canSave" @click="submit">
                    <Check class="size-4" /> Simpan Luaran
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
