<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import DataTable from '@/Components/app/DataTable.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import MemberSearch from '@/Components/Submission/Wizard/MemberSearch.vue'
import PartnersCard from '@/Components/Submission/Wizard/PartnersCard.vue'
import StudentMembersCard from '@/Components/Submission/Wizard/StudentMembersCard.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import type { MemberCandidate, WizardMemberRow, WizardMembers } from '@/types/submission-wizard'

const props = defineProps<{
    submissionId: number
    members: WizardMembers
    candidates?: MemberCandidate[]
}>()

const columns = [
    { key: 'name', label: 'Nama' },
    { key: 'role', label: 'Peran' },
    { key: 'unit', label: 'Unit' },
    { key: 'nidn', label: 'NIDN' },
    { key: 'actions', label: '' },
]

const page = usePage()
const removing = ref<WizardMemberRow | null>(null)
const processing = ref(false)
// Kuota skema menghitung anggota aktif tanpa ketua, sama dengan AddResearchMember.
const count = computed(() => props.members.members.filter((m) => m.id !== 0).length)
const full = computed(
    () => props.members.memberLimit !== null && count.value >= props.members.memberLimit
)
const quota = computed(() => [
    { label: 'Anggota saat ini', value: `${count.value} dari ${props.members.memberLimit}` },
])

const remove = () => {
    if (removing.value === null) return
    router.delete(
        route('user.submissions.members.destroy', [
            String(props.submissionId),
            String(removing.value.id),
        ]),
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => {
                processing.value = false
                removing.value = null
            },
        }
    )
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <SectionCard title="Step 4 — Anggota Penelitian">
            <AppBanner v-if="page.props.errors?.id" tone="denial">
                {{ page.props.errors.id }}
            </AppBanner>
            <DataTable :rows="members.members" :columns="columns">
                <template #cell-name="{ row }">{{ row.name }}</template>
                <template #cell-role="{ row }">
                    <Badge variant="outline">{{ row.roleLabel }}</Badge>
                </template>
                <template #cell-unit="{ row }">{{ row.unit ?? '—' }}</template>
                <template #cell-nidn="{ row }">{{ row.nidn ?? '—' }}</template>
                <template #cell-actions="{ row }">
                    <Button
                        v-if="row.removable"
                        variant="ghost"
                        size="sm"
                        data-action="remove-member"
                        @click="removing = row"
                    >
                        Hapus
                    </Button>
                </template>
            </DataTable>
            <!-- BR-SM-18 -->
            <p class="text-xs text-muted-foreground">
                Jumlah anggota dibatasi skema terpilih. Ketua tidak dapat dihapus — gunakan Alihkan
                Kepemilikan lewat Operator.
            </p>
            <!-- BR-SM-08, BR-SCH-03 -->
            <InfoGrid v-if="members.memberLimit !== null" :items="quota" />
            <AppBanner v-if="full" tone="warning">
                Jumlah anggota sudah mencapai batas skema. Keluarkan anggota lain sebelum menambah.
            </AppBanner>
        </SectionCard>
        <MemberSearch
            :submission-id="submissionId"
            :role-options="members.roleOptions"
            :candidates="candidates"
            :disabled="full"
        />
        <StudentMembersCard
            :submission-id="submissionId"
            :students="members.students"
            :limit="members.studentLimit"
        />
        <PartnersCard :submission-id="submissionId" :partners="members.partners" />
        <ConfirmDialog
            :open="removing !== null"
            title="Keluarkan anggota?"
            :description="`${removing?.name ?? ''} dikeluarkan dari pengajuan ini.`"
            confirm-label="Keluarkan"
            destructive
            :processing="processing"
            @update:open="(open) => !open && (removing = null)"
            @confirm="remove"
        />
    </div>
</template>
