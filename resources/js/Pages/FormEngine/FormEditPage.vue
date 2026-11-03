<script setup lang="ts">
// Frame: ADMIN-08-06, ADMIN-08-07
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

import PageHeader from '@/Components/app/PageHeader.vue'
import FormEditTabs from '@/Components/FormEngine/FormEditTabs.vue'
import FormGeneralTab from '@/Components/FormEngine/FormGeneralTab.vue'
import { Badge } from '@/Components/ui/badge'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { FormEditPageProps } from '@/types/form-engine'

const props = defineProps<FormEditPageProps>()

const title = computed(() =>
    props.form === null ? 'Buat Form Baru' : `Edit Form — ${props.form.title}`
)
</script>

<template>
    <Head :title="title" />
    <AppLayout>
        <PageHeader
            :title="title"
            subtitle="General & Fields"
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
            :key="`${tab}-${form?.id ?? 'new'}`"
            :form="form"
            :general="general"
            :field-type-options="fieldTypeOptions"
            :form-type-options="formTypeOptions"
        />
    </AppLayout>
</template>
