<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import { Button } from '@/Components/ui/button'
import { formatDate } from '@/lib/format'
import type { PhaseAccess } from '@/types/submission-page'

type PhaseOverride = NonNullable<PhaseAccess['override']>

// BR-FE-16 — Operator membaca alasan override; FE-OVERRIDE-05 — yang dicabut berhenti berlaku seketika.
const props = defineProps<{ submissionId: number; override: PhaseOverride }>()

const revoking = ref(false)
const processing = ref(false)

const revoke = () => {
    processing.value = true
    router.delete(
        route('admin.submissions.overrides.destroy', [
            String(props.submissionId),
            String(props.override.id),
        ]),
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false
                revoking.value = false
            },
        }
    )
}
</script>

<template>
    <div class="flex items-start justify-between gap-3 rounded-md border p-3 text-sm">
        <div class="min-w-0">
            <p class="font-medium">
                {{
                    override.status === 'active'
                        ? `Aktif sampai ${formatDate(override.expires_at)}`
                        : 'Kedaluwarsa'
                }}
            </p>
            <p v-if="override.reason" class="break-words text-muted-foreground">
                {{ override.reason }}
            </p>
        </div>
        <Button
            v-if="override.status === 'active'"
            type="button"
            variant="outline"
            size="sm"
            @click="revoking = true"
            >Cabut</Button
        >
    </div>
    <ConfirmDialog
        v-model:open="revoking"
        title="Cabut perpanjangan?"
        description="Akses yang dibuka perpanjangan ini langsung tertutup kembali."
        confirm-label="Cabut Perpanjangan"
        destructive
        :processing="processing"
        @confirm="revoke"
    />
</template>
