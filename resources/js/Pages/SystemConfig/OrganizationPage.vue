<script setup lang="ts">
// Frame: ADMIN-08-02
import { Head, router } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { ref } from 'vue'

import PageHeader from '@/Components/app/PageHeader.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import MoveUserDialog from '@/Components/SystemConfig/MoveUserDialog.vue'
import OrganizationTree from '@/Components/SystemConfig/OrganizationTree.vue'
import OrganizationUnitDialog, {
    type OrganizationUnitDialogMode,
} from '@/Components/SystemConfig/OrganizationUnitDialog.vue'
import OrganizationUnitPanel from '@/Components/SystemConfig/OrganizationUnitPanel.vue'
import { Button } from '@/Components/ui/button'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { OrganizationPageProps } from '@/types/system-config'

defineProps<OrganizationPageProps>()

const dialogOpen = ref(false)
const dialogMode = ref<OrganizationUnitDialogMode>('root')
const moveOpen = ref(false)

function openDialog(mode: OrganizationUnitDialogMode) {
    dialogMode.value = mode
    dialogOpen.value = true
}

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
        >
            <template #actions>
                <Button @click="openDialog('root')"
                    ><Plus class="size-4" />Tambah Unit Induk</Button
                >
            </template>
        </PageHeader>
        <div class="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <SectionCard title="Struktur Organisasi">
                <OrganizationTree
                    :nodes="tree"
                    :selected-id="selected?.id ?? null"
                    @select="select"
                />
            </SectionCard>
            <OrganizationUnitPanel
                :unit="selected"
                @add-child="openDialog('child')"
                @edit="openDialog('edit')"
                @move-user="moveOpen = true"
            />
        </div>
        <OrganizationUnitDialog
            v-model:open="dialogOpen"
            :mode="dialogMode"
            :unit="selected"
            :type-options="typeOptions"
            :parent-options="parentOptions"
        />
        <MoveUserDialog
            v-if="selected"
            v-model:open="moveOpen"
            :candidates="memberCandidates"
            :parent-options="parentOptions"
        />
    </AppLayout>
</template>
