<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import BudgetTable from '@/Components/Submission/BudgetTable.vue'
import BudgetItemForm from '@/Components/Submission/Wizard/BudgetItemForm.vue'
import { Button } from '@/Components/ui/button'
import { formatRupiah } from '@/lib/format'
import type { BudgetItem } from '@/types/submission-page'
import type { WizardBudget } from '@/types/submission-wizard'

const props = defineProps<{ submissionId: number; budget: WizardBudget }>()

const emit = defineEmits<{ goto: [step: number] }>()

const page = usePage()
const editing = ref<BudgetItem | null>(null)
// Dinaikkan setelah tersimpan supaya form tambah kembali kosong.
const saves = ref(0)
const totalLine = computed(() => {
    const total = `Total ${formatRupiah(props.budget.total)}`
    return props.budget.maxBudget === null
        ? total
        : `${total} dari ${formatRupiah(props.budget.maxBudget)}`
})

const saved = () => {
    editing.value = null
    saves.value++
}

const remove = (item: BudgetItem) =>
    router.delete(
        route('user.submissions.budget-items.destroy', [
            String(props.submissionId),
            String(item.id),
        ]),
        { preserveScroll: true }
    )
</script>

<template>
    <SectionCard title="Step 5 — Rencana Anggaran">
        <!-- BR-SM-17, BR-BUD-01 -->
        <template v-if="budget.blockedReason">
            <AppBanner tone="warning">{{ budget.blockedReason }}</AppBanner>
            <Button variant="outline" class="self-start" data-goto="3" @click="emit('goto', 3)">
                Ke Langkah 3
            </Button>
        </template>
        <template v-else>
            <BudgetTable v-if="budget.items.length" :items="budget.items">
                <template v-if="budget.editable" #actions="{ item }">
                    <div class="flex justify-end gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            data-action="edit-item"
                            @click="editing = item"
                        >
                            Ubah
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            data-action="remove-item"
                            @click="remove(item)"
                        >
                            Hapus
                        </Button>
                    </div>
                </template>
            </BudgetTable>
            <EmptyState v-else title="Belum ada item anggaran" />
            <p class="text-sm font-medium">{{ totalLine }}</p>
            <!-- BR-SCH-04 -->
            <p v-if="budget.maxBudget === null" class="text-xs text-muted-foreground">
                Pengajuan tanpa skema tidak punya batas anggaran.
            </p>
            <AppBanner v-if="page.props.errors?.total" tone="denial">
                {{ page.props.errors.total }}
            </AppBanner>
            <template v-if="budget.editable">
                <BudgetItemForm
                    :key="editing ? `edit-${editing.id}` : `new-${saves}`"
                    :submission-id="submissionId"
                    :components="budget.components"
                    :item="editing"
                    @saved="saved"
                    @cancel="editing = null"
                />
                <!-- BR-BUD-01, BR-BUD-05 -->
                <p class="text-xs text-muted-foreground">
                    Total anggaran tidak boleh melebihi batas maksimum skema. Komponen yang sudah
                    dinonaktifkan tidak muncul untuk item baru.
                </p>
            </template>
            <!-- BR-BUD-09 -->
            <p v-else class="text-xs text-muted-foreground">
                Anggaran hanya bisa disunting ketua dan Co-Investigator selama pengajuan belum
                disetujui, ditolak, atau ditarik.
            </p>
        </template>
    </SectionCard>
</template>
