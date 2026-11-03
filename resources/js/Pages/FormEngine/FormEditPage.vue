<script setup lang="ts">
// Frame: ADMIN-08-06, ADMIN-08-07
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

import PageHeader from '@/Components/app/PageHeader.vue'
import FormAccessTab from '@/Components/FormEngine/FormAccessTab.vue'
import FormEditTabs from '@/Components/FormEngine/FormEditTabs.vue'
import FormGeneralTab from '@/Components/FormEngine/FormGeneralTab.vue'
import { Badge } from '@/Components/ui/badge'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { FormEditPageProps } from '@/types/form-engine'

const props = defineProps<FormEditPageProps>()

const title = computed(() =>
    props.form === null ? 'Buat Form Baru' : `Edit Form — ${props.form.title}`
)

const subtitle = computed(() => (props.tab === 'access' ? 'Access Control' : 'General & Fields'))
// Penyempitan union per tab: template tidak menyempitkan props yang dipisah satu per satu.
const generalProps = computed(() => (props.tab === 'general' ? props : null))
const accessProps = computed(() => (props.tab === 'access' ? props : null))
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
    </AppLayout>
</template>
