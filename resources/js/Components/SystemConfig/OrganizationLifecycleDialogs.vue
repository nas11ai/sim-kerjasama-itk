<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import type { OrganizationUnitData } from '@/types/system-config'

export type OrganizationLifecycleAction = 'deactivate' | 'delete' | 'reactivate'

// BR-IAM-14 / BR-IAM-08 / BR-IAM-22 — konfirmasi siklus hidup unit; angkanya dari backend.
const props = defineProps<{ unit: OrganizationUnitData }>()
const action = defineModel<OrganizationLifecycleAction | null>('action', { required: true })
const emit = defineEmits<{ failed: [message: string] }>()

const processing = ref(false)
const openFor = (name: OrganizationLifecycleAction) =>
    computed({
        get: () => action.value === name,
        set: (isOpen: boolean) => {
            if (!isOpen) action.value = null
        },
    })
const deactivateOpen = openFor('deactivate')
const deleteOpen = openFor('delete')
const reactivateOpen = openFor('reactivate')

const invitations = computed(() => props.unit.census.pendingInvitations)
// W118 — pratinjau "memutus akses n peneliti" tidak dibangun: BR-IAM-14 membuatnya selalu 0.
const deactivateText = computed(() =>
    [
        `Unit ini akan dinonaktifkan, bukan dihapus. ${props.unit.census.subtreeProfiles} profil dan seluruh riwayat pengajuannya tetap terbaca.`,
        'Unit beserta turunannya berhenti ditawarkan dan memutus rantai akses Formulir bagi unit di bawahnya.',
        invitations.value > 0 ? `${invitations.value} undangan registrasi ikut tidak berlaku.` : '',
    ]
        .filter((part) => part !== '')
        .join(' ')
)
// IAM-REG-10 — undangan yang belum terpakai ikut tercabut, disebut sebelum konfirmasi.
const deleteText = computed(() =>
    invitations.value > 0
        ? `Menghapus unit ini akan mencabut ${invitations.value} undangan registrasi yang belum terpakai. Siapa pun yang membuka tautannya akan melihat halaman undangan tidak berlaku.`
        : `${props.unit.name} beserta seluruh unit di bawahnya dihapus permanen.`
)

// BR-IAM-22 — satu unit saja; undangan yang masih sah disebut sebelum konfirmasi.
const reactivateText = computed(() => {
    const revived = props.unit.revivableInvitations
    const base =
        'Unit ini kembali ditawarkan dan rantai akses Formulirnya tersambung lagi. Unit turunannya tetap nonaktif; aktifkan satu per satu bila perlu.'
    return revived > 0
        ? `${base} ${revived} undangan registrasi yang belum kedaluwarsa berlaku lagi.`
        : base
})

const callbacks = (onSuccess: () => void) => ({
    preserveScroll: true,
    onStart: () => (processing.value = true),
    onFinish: () => (processing.value = false),
    onSuccess: () => {
        action.value = null
        onSuccess()
    },
    onError: (errors: Record<string, string>) => {
        action.value = null
        emit('failed', errors.organization_id ?? Object.values(errors).join(' '))
    },
})

const deactivate = () =>
    router.post(
        route('admin.organizations.deactivate', props.unit.id),
        {},
        callbacks(() => undefined)
    )
const reactivate = () =>
    router.post(
        route('admin.organizations.reactivate', props.unit.id),
        {},
        callbacks(() => undefined)
    )
const destroy = () =>
    router.delete(
        route('admin.organizations.destroy', props.unit.id),
        callbacks(() => router.get(route('admin.organizations.index')))
    )
</script>

<template>
    <ConfirmDialog
        v-model:open="deactivateOpen"
        title="Nonaktifkan unit?"
        :description="deactivateText"
        confirm-label="Nonaktifkan"
        destructive
        :processing="processing"
        @confirm="deactivate"
    />
    <ConfirmDialog
        v-model:open="reactivateOpen"
        title="Aktifkan kembali unit?"
        :description="reactivateText"
        confirm-label="Aktifkan Kembali"
        :processing="processing"
        @confirm="reactivate"
    />
    <ConfirmDialog
        v-model:open="deleteOpen"
        title="Hapus unit?"
        :description="deleteText"
        :confirm-label="
            invitations > 0 ? `Hapus Unit & Cabut ${invitations} Undangan` : 'Hapus Unit'
        "
        destructive
        :processing="processing"
        @confirm="destroy"
    />
</template>
