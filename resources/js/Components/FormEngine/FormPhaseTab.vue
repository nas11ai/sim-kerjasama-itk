<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Layers } from 'lucide-vue-next'

import EmptyState from '@/Components/app/EmptyState.vue'
import PhaseCard from '@/Components/FormEngine/PhaseCard.vue'
import { Button } from '@/Components/ui/button'
import type { FormHeaderData, PhaseCardData } from '@/types/form-engine'

defineProps<{
    form: FormHeaderData
    phases: PhaseCardData[]
    can: { linkPeriod: boolean }
}>()
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
            />
        </div>
        <Button as-child>
            <Link :href="route('admin.forms.index')">Selesai — ke Daftar Form</Link>
        </Button>
    </div>
</template>
