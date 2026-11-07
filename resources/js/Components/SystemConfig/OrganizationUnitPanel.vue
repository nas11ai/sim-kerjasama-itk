<script setup lang="ts">
import { ArrowRightLeft, Pencil, Plus, Trash2, X } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'

import ActionPanel from '@/Components/app/ActionPanel.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import OrganizationLifecycleDialogs, {
    type OrganizationLifecycleAction,
} from '@/Components/SystemConfig/OrganizationLifecycleDialogs.vue'
import { Button } from '@/Components/ui/button'
import type { ActionItem } from '@/types/action'
import type { OrganizationUnitData } from '@/types/system-config'

const props = defineProps<{ unit: OrganizationUnitData | null }>()
const emit = defineEmits<{ addChild: []; edit: []; moveUser: [] }>()

const pending = ref<OrganizationLifecycleAction | null>(null)
const failure = ref('')
watch(
    () => props.unit?.id,
    () => (failure.value = '')
)

const directUsers = (unit: OrganizationUnitData) => {
    const { directProfiles, directActiveUsers } = unit.census
    if (directProfiles === 0) {
        return '0'
    }

    return directActiveUsers === directProfiles
        ? `${directProfiles} — seluruhnya aktif`
        : `${directProfiles} — ${directActiveUsers} aktif`
}

const facts = (unit: OrganizationUnitData) => [
    { label: 'Unit', value: unit.name },
    { label: 'Tipe', value: unit.typeLabel },
    { label: 'Status', value: unit.isActive ? 'Aktif' : 'Nonaktif' },
    { label: 'User di Unit Ini', value: directUsers(unit) },
    { label: 'User di Seluruh Subtree', value: String(unit.census.subtreeProfiles) },
]

// BR-IAM-14 — ambang deaktivasi: user aktif di seluruh subtree.
const deactivateHint = (unit: OrganizationUnitData) => {
    const { subtreeActiveUsers, unitsWithActiveUsers } = unit.census
    const spread =
        unitsWithActiveUsers > 0 ? `, tersebar di ${unitsWithActiveUsers} unit turunan` : ''
    const blocked = unit.canDeactivate
        ? ''
        : `Terhalang ${subtreeActiveUsers} user aktif${spread} — pindahkan atau nonaktifkan orangnya lebih dulu. `

    return `${blocked}Menonaktifkan unit memerlukan konfirmasi.`
}

// BR-IAM-08 — ambang penghapusan: profil apa pun, termasuk milik user nonaktif.
const deleteHint = (unit: OrganizationUnitData) =>
    unit.canDelete
        ? 'Menghapus unit memerlukan konfirmasi.'
        : `Terhalang ${unit.census.subtreeProfiles} profil user. Penghapusan menuntut subtree benar-benar kosong — termasuk profil yang user-nya sudah nonaktif.`

const actions = computed<ActionItem[]>(() => {
    const unit = props.unit
    if (unit === null) return []

    const items: ActionItem[] = []
    if (unit.isActive) {
        items.push({
            key: 'deactivate',
            label: 'Nonaktifkan',
            icon: X,
            available: unit.canDeactivate,
            hint: deactivateHint(unit),
            onSelect: () => (pending.value = 'deactivate'),
        })
    }
    items.push({
        key: 'delete',
        label: 'Hapus Unit',
        icon: Trash2,
        available: unit.canDelete,
        hint: deleteHint(unit),
        onSelect: () => (pending.value = 'delete'),
    })
    if (unit.census.subtreeProfiles > 0) {
        items.push({
            key: 'move-user',
            label: 'Pindahkan User',
            icon: ArrowRightLeft,
            available: true,
            hint: 'Pindahkan anggota unit ini atau turunannya ke unit aktif lain.',
            onSelect: () => emit('moveUser'),
        })
    }

    return items
})
</script>

<template>
    <div class="flex flex-col gap-4">
        <SectionCard title="Unit Terpilih">
            <EmptyState
                v-if="unit === null"
                title="Pilih unit di pohon untuk melihat rinciannya."
            />
            <template v-else>
                <InfoGrid :items="facts(unit)" />
                <div class="flex flex-col gap-2">
                    <!-- BR-IAM-14 — unit nonaktif berhenti ditawarkan, juga sebagai induk (W117). -->
                    <Button v-if="unit.isActive" variant="outline" @click="emit('addChild')">
                        <Plus class="size-4" />Tambah Sub-unit
                    </Button>
                    <Button variant="outline" @click="emit('edit')">
                        <Pencil class="size-4" />Edit Nama / Tipe
                    </Button>
                </div>
                <!-- BR-IAM-08 / BR-IAM-14 — kelayakan dan angka hint dari sensus backend. -->
                <ActionPanel :actions="actions" />
                <AppBanner v-if="failure !== ''" tone="denial">{{ failure }}</AppBanner>
                <OrganizationLifecycleDialogs
                    v-model:action="pending"
                    :unit="unit"
                    @failed="failure = $event"
                />
            </template>
        </SectionCard>
        <AppBanner tone="info">
            Nonaktifkan dan Hapus adalah dua operasi dengan ambang berbeda: nonaktif melihat user
            aktif, hapus melihat profil apa pun. Unit yang seluruh user-nya sudah nonaktif boleh
            dinonaktifkan, tapi belum boleh dihapus.
        </AppBanner>
    </div>
</template>
