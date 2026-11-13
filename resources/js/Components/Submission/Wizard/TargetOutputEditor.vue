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
const editingId = ref<number | null>(null)
const editType = ref<string | null>(null)

const add = () => {
    if (outputType.value === null) return
    router.post(
        route('user.submissions.target-outputs.store', String(props.submissionId)),
        { output_type: outputType.value },
        { preserveScroll: true, onSuccess: () => (outputType.value = null) }
    )
}

const targetRoute = (name: string, target: SubmissionTargetOutput) =>
    route(name, [String(props.submissionId), String(target.id)])

// Target membawa nama tipe, jadi nilai awal dicari dari label.
const startEdit = (target: SubmissionTargetOutput) => {
    editingId.value = target.id
    editType.value = props.types.find((type) => type.label === target.type)?.value ?? null
}

const save = (target: SubmissionTargetOutput) => {
    if (editType.value === null) return
    router.put(
        targetRoute('user.submissions.target-outputs.update', target),
        { output_type: editType.value },
        { preserveScroll: true, onSuccess: () => (editingId.value = null) }
    )
}

const remove = (target: SubmissionTargetOutput) =>
    router.delete(targetRoute('user.submissions.target-outputs.destroy', target), {
        preserveScroll: true,
    })
</script>

<template>
    <SectionCard title="Target Luaran yang Dijanjikan">
        <ul v-if="targetOutputs.length" class="flex flex-col divide-y rounded-md border">
            <li
                v-for="target in targetOutputs"
                :key="target.id"
                class="flex flex-wrap items-center justify-between gap-3 p-3"
            >
                <template v-if="editingId === target.id">
                    <OptionSelect v-model="editType" :options="types" class="sm:max-w-xs" />
                    <div class="flex gap-2">
                        <Button
                            size="sm"
                            data-action="save-target"
                            :disabled="editType === null"
                            @click="save(target)"
                        >
                            Simpan
                        </Button>
                        <Button variant="ghost" size="sm" @click="editingId = null">Batal</Button>
                    </div>
                </template>
                <template v-else>
                    <div class="flex min-w-0 items-center gap-2">
                        <Badge variant="outline">{{ target.type }}</Badge>
                        <span v-if="target.description" class="text-sm">
                            {{ target.description }}
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            data-action="edit-target"
                            @click="startEdit(target)"
                        >
                            Ubah
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            data-action="remove-target"
                            @click="remove(target)"
                        >
                            Hapus
                        </Button>
                    </div>
                </template>
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
            pengajuan disetujui, setiap perubahan dicatat di jejak audit.
        </p>
    </SectionCard>
</template>
