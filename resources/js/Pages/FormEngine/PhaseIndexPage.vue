<script setup lang="ts">
// Frame: ADMIN-08-08
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { Layers, Plus } from 'lucide-vue-next'

import EmptyState from '@/Components/app/EmptyState.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PhaseCard from '@/Components/FormEngine/PhaseCard.vue'
import PhasePeriodDialog from '@/Components/FormEngine/PhasePeriodDialog.vue'
import PhaseDeleteBlockedDialog from '@/Components/FormEngine/PhaseDeleteBlockedDialog.vue'
import ToggleDeleteDialogs from '@/Components/FormEngine/ToggleDeleteDialogs.vue'
import { Button } from '@/Components/ui/button'
import { usePhaseActions } from '@/composables/usePhaseActions'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { PhaseCardData, PhaseIndexPageProps } from '@/types/form-engine'

// R2 — pustaka fase: tahap mandiri, bukan lagi tab di bawah satu Form. `filterForm` datang dari
// tab Phases Form Builder (`?form=`): tahap yang langkah mana pun memakai Form itu.
const props = defineProps<PhaseIndexPageProps>()

const actions = usePhaseActions()
const { target, toggling, deleting, blocked, processing } = actions

const linking = ref(false)
const linkTarget = ref<PhaseCardData | null>(null)
const requestLink = (phase: PhaseCardData) => {
    linkTarget.value = phase
    linking.value = true
}

const emptyTitle = computed(() =>
    props.filterForm === null
        ? 'Belum ada tahap.'
        : `Belum ada tahap yang memakai ${props.filterForm.title}.`
)
</script>

<template>
    <Head title="Fase" />
    <AppLayout>
        <PageHeader title="Fase" subtitle="Pustaka tahap yang bisa dipakai banyak periode.">
            <template #actions>
                <Button as-child>
                    <Link :href="route('admin.phases.create')"
                        ><Plus class="size-4" />Buat Tahap Baru</Link
                    >
                </Button>
            </template>
        </PageHeader>
        <div class="max-w-3xl space-y-6">
            <p v-if="filterForm" class="flex flex-wrap items-center gap-2 text-sm">
                <span>Tahap yang memakai {{ filterForm.title }}</span>
                <Link class="underline" :href="route('admin.phases.index')">Tampilkan semua</Link>
            </p>
            <EmptyState v-if="phases.length === 0" :icon="Layers" :title="emptyTitle" />
            <div v-else class="space-y-3">
                <PhaseCard
                    v-for="phase in phases"
                    :key="phase.id"
                    :phase="phase"
                    :can-link-period="can.linkPeriod"
                    @toggle-active="actions.requestToggle"
                    @delete="actions.requestDelete"
                    @link-period="requestLink"
                />
            </div>
        </div>
        <PhasePeriodDialog v-model:open="linking" :phase="linkTarget" />
        <PhaseDeleteBlockedDialog
            v-model:open="blocked"
            :phase="target"
            @deactivate="target && actions.requestToggle(target)"
        />
        <ToggleDeleteDialogs
            v-model:toggling="toggling"
            v-model:deleting="deleting"
            :toggle-copy="actions.toggleCopy.value"
            delete-title="Hapus tahap?"
            :delete-description="actions.deleteDescription.value"
            delete-confirm-label="Hapus Tahap"
            :destructive-toggle="target?.isActive ?? false"
            :processing="processing"
            @confirm-toggle="actions.confirmToggle"
            @confirm-delete="actions.confirmDelete"
        />
    </AppLayout>
</template>
