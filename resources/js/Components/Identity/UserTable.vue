<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ArrowRight, Users } from 'lucide-vue-next'

import DataTable from '@/Components/app/DataTable.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import AccountStatusBadges from '@/Components/Identity/AccountStatusBadges.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import type { UserRowData } from '@/types/identity'

// IAM-DEACT-07, W49 — baris hanya menaut detail; deaktivasi tinggal di sana.
defineProps<{ rows: UserRowData[]; filtered: boolean }>()

const columns = [
    { key: 'name', label: 'Nama' },
    { key: 'email', label: 'Email' },
    { key: 'nidn', label: 'NIDN' },
    { key: 'organization', label: 'Organisasi' },
    { key: 'roles', label: 'Role' },
    { key: 'status', label: 'Status' },
    { key: 'action', label: '' },
]
</script>

<template>
    <DataTable :rows="rows" :columns="columns">
        <template #cell-name="{ row }">
            <span class="font-medium">{{ row.name }}</span>
        </template>
        <template #cell-email="{ row }">{{ row.email }}</template>
        <template #cell-nidn="{ row }">{{ row.nidn ?? '—' }}</template>
        <template #cell-organization="{ row }">{{ row.organization ?? '—' }}</template>
        <template #cell-roles="{ row }">
            <span class="inline-flex flex-wrap gap-1">
                <Badge v-for="role in row.roles" :key="role.value" variant="outline">{{
                    role.label
                }}</Badge>
            </span>
        </template>
        <template #cell-status="{ row }"><AccountStatusBadges :status="row.status" /></template>
        <template #cell-action="{ row }">
            <Button as-child variant="ghost" size="sm">
                <Link :href="row.showUrl">Detail <ArrowRight class="size-4" /></Link>
            </Button>
        </template>
        <template #empty>
            <EmptyState
                :icon="Users"
                :title="filtered ? 'Tidak ada user yang cocok dengan filter.' : 'Belum ada user.'"
            />
        </template>
    </DataTable>
</template>
