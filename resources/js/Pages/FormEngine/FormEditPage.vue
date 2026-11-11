<script setup lang="ts">
// Frame: ADMIN-08-06, ADMIN-08-07, ADMIN-08-08
import { Head, Link } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { computed } from 'vue'

import PageHeader from '@/Components/app/PageHeader.vue'
import FormAccessTab from '@/Components/FormEngine/FormAccessTab.vue'
import FormEditTabs from '@/Components/FormEngine/FormEditTabs.vue'
import FormGeneralTab from '@/Components/FormEngine/FormGeneralTab.vue'
import FormPhaseTab from '@/Components/FormEngine/FormPhaseTab.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { FormEditPageProps } from '@/types/form-engine'

const props = defineProps<FormEditPageProps>()

const title = computed(() =>
    props.form === null ? 'Buat Form Baru' : `Edit Form — ${props.form.title}`
)

const subtitles = {
    general: 'General & Fields',
    access: 'Access Control',
    phases: 'Phases & Deadlines',
}
const subtitle = computed(() => subtitles[props.tab])
// Penyempitan union per tab: template tidak menyempitkan props yang dipisah satu per satu.
const generalProps = computed(() => (props.tab === 'general' ? props : null))
const accessProps = computed(() => (props.tab === 'access' ? props : null))
const phasesProps = computed(() => (props.tab === 'phases' ? props : null))
</script>

<template>
    <Head :title="title" />
    <AppLayout>
        <PageHeader
            :title="title"
            :subtitle="subtitle"
            :back-href="route('admin.forms.index')"
            back-label="Form Builder"
        >
            <template v-if="tab === 'phases' && form" #actions>
                <Button as-child>
                    <Link :href="route('admin.phases.create')"
                        ><Plus class="size-4" />Buat Tahap Baru</Link
                    >
                </Button>
            </template>
            <template v-if="form" #badge>
                <Badge :variant="form.isActive ? 'success' : 'outline'">{{
                    form.isActive ? 'Aktif' : 'Nonaktif'
                }}</Badge>
            </template>
        </PageHeader>
        <FormEditTabs :form="form" :active="tab" />
        <!-- Trap S2b — Inertia memakai ulang halaman antar-tab; state tab hidup di child ber-key -->
        <FormGeneralTab
            v-if="generalProps"
            :key="`${tab}-${form?.id ?? 'new'}`"
            :form="generalProps.form"
            :general="generalProps.general"
            :field-type-options="generalProps.fieldTypeOptions"
            :form-type-options="generalProps.formTypeOptions"
        />
        <FormAccessTab
            v-else-if="accessProps"
            :key="`${tab}-${form?.id ?? 'new'}`"
            :form="accessProps.form"
            :rules="accessProps.rules"
            :permission-options="accessProps.permissionOptions"
            :organization-options="accessProps.organizationOptions"
        />
        <FormPhaseTab
            v-else-if="phasesProps"
            :key="`${tab}-${form?.id ?? 'new'}`"
            :form="phasesProps.form"
            :phases="phasesProps.phases"
            :can="phasesProps.can"
        />
    </AppLayout>
</template>
