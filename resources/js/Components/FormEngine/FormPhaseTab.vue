<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Layers } from 'lucide-vue-next'

import EmptyState from '@/Components/app/EmptyState.vue'
import PhaseCard from '@/Components/FormEngine/PhaseCard.vue'
import PhasePeriodDialog from '@/Components/FormEngine/PhasePeriodDialog.vue'
import PhaseDeleteBlockedDialog from '@/Components/FormEngine/PhaseDeleteBlockedDialog.vue'
import ToggleDeleteDialogs from '@/Components/FormEngine/ToggleDeleteDialogs.vue'
import { Button } from '@/Components/ui/button'
import { usePhaseActions } from '@/composables/usePhaseActions'
import type { FormHeaderData, PhaseCardData } from '@/types/form-engine'

const props = defineProps<{
    form: FormHeaderData
    phases: PhaseCardData[]
    can: { linkPeriod: boolean }
}>()

const actions = usePhaseActions(props.form.id)
const { target, toggling, deleting, blocked, processing } = actions

const linking = ref(false)
const linkTarget = ref<PhaseCardData | null>(null)
const requestLink = (phase: PhaseCardData) => {
    linkTarget.value = phase
    linking.value = true
}
</script>

<template>
    <div class="max-w-3xl space-y-6">
        <EmptyState
            v-if="phases.length === 0"
            :icon="Layers"
            title="Belum ada tahap yang memakai form ini."
        />
        <div v-else class="space-y-3">
            <PhaseCard
                v-for="phase in phases"
                :key="phase.id"
                :phase="phase"
                :form-id="form.id"
                :can-link-period="can.linkPeriod"
                @toggle-active="actions.requestToggle"
                @delete="actions.requestDelete"
                @link-period="requestLink"
            />
        </div>
        <Button as-child>
            <Link :href="route('admin.forms.index')">Selesai — ke Daftar Form</Link>
        </Button>
        <PhasePeriodDialog v-model:open="linking" :phase="linkTarget" :form-id="form.id" />
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
    </div>
</template>
