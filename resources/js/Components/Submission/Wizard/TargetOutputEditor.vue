<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { ref } from 'vue'

import OptionSelect from '@/Components/app/OptionSelect.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import type { FilterOption, SubmissionTargetOutput } from '@/types/submission-page'

// Target tersimpan per baris, bukan lewat perpindahan langkah wizard.
const props = defineProps<{
    submissionId: number
    targetOutputs: SubmissionTargetOutput[]
    types: FilterOption[]
}>()

const outputType = ref<string | null>(null)

const add = () => {
    if (outputType.value === null) return
    router.post(
        route('user.submissions.target-outputs.store', String(props.submissionId)),
        { output_type: outputType.value },
        { preserveScroll: true, onSuccess: () => (outputType.value = null) }
    )
}

const remove = (target: SubmissionTargetOutput) =>
    router.delete(
        route('user.submissions.target-outputs.destroy', [
            String(props.submissionId),
            String(target.id),
        ]),
        { preserveScroll: true }
    )
</script>

<template>
    <SectionCard title="Target Luaran yang Dijanjikan">
        <ul v-if="targetOutputs.length" class="flex flex-col divide-y rounded-md border">
            <li
                v-for="target in targetOutputs"
                :key="target.id"
                class="flex items-center justify-between gap-3 p-3"
            >
                <div class="flex min-w-0 items-center gap-2">
                    <Badge variant="outline">{{ target.type }}</Badge>
                    <span v-if="target.description" class="text-sm">{{ target.description }}</span>
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    data-action="remove-target"
                    @click="remove(target)"
                >
                    Hapus
                </Button>
            </li>
        </ul>
        <div class="flex flex-col gap-2 sm:flex-row">
            <OptionSelect
                v-model="outputType"
                :options="types"
                placeholder="Pilih tipe luaran"
                class="sm:max-w-xs"
            />
            <Button data-action="add-target" :disabled="outputType === null" @click="add">
                <Plus class="size-4" /> Tambah Target
            </Button>
        </div>
        <!-- BR-SM-14, BR-SM-15 -->
        <p class="text-xs text-muted-foreground">
            Tipe yang tersedia dibatasi skema terpilih. Target ini masih bisa diubah setelah
            pengajuan disetujui — setiap perubahan dicatat di jejak audit.
        </p>
    </SectionCard>
</template>
