<script setup lang="ts">
// Frame: ADMIN-08-02
import { Head, router } from '@inertiajs/vue3'

import PageHeader from '@/Components/app/PageHeader.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import OrganizationTree from '@/Components/SystemConfig/OrganizationTree.vue'
import OrganizationUnitPanel from '@/Components/SystemConfig/OrganizationUnitPanel.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { OrganizationPageProps } from '@/types/system-config'

defineProps<OrganizationPageProps>()

function select(id: number) {
    router.get(
        route('admin.organizations.index'),
        { unit: id },
        { only: ['selected'], preserveState: true, preserveScroll: true, replace: true }
    )
}
</script>

<template>
    <Head title="Struktur Organisasi" />
    <AppLayout>
        <PageHeader
            title="Struktur Organisasi"
            subtitle="Susun unit kerja secara berjenjang — klik untuk membuka atau menutup cabang."
        />
        <div class="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <SectionCard title="Struktur Organisasi">
                <OrganizationTree
                    :nodes="tree"
                    :selected-id="selected?.id ?? null"
                    @select="select"
                />
            </SectionCard>
            <OrganizationUnitPanel :unit="selected" />
        </div>
    </AppLayout>
</template>
