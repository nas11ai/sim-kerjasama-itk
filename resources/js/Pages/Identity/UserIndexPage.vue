<script setup lang="ts">
// Frame: OPERATOR-08-01
import { Head, Link } from '@inertiajs/vue3'
import { MailPlus, UserCheck } from 'lucide-vue-next'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import FilterToolbar from '@/Components/app/FilterToolbar.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PaginationBar from '@/Components/app/PaginationBar.vue'
import UserTable from '@/Components/Identity/UserTable.vue'
import { Button } from '@/Components/ui/button'
import { useFilters } from '@/composables/useFilters'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { UserIndexFilters, UserIndexPageProps } from '@/types/identity'
import type { FilterSelectSpec } from '@/types/submission-page'

const props = defineProps<UserIndexPageProps>()

const { filters } = useFilters('admin.users.index', props.filters)

const filtered = computed(() => Object.values(props.filters).some((value) => value !== ''))

const selects = computed((): FilterSelectSpec<Exclude<keyof UserIndexFilters, 'search'>>[] => [
    { key: 'role', label: 'Role', allLabel: 'Semua role', options: props.roleOptions },
    { key: 'account', label: 'Status', allLabel: 'Semua status', options: props.accountOptions },
    {
        key: 'verification',
        label: 'Verifikasi',
        allLabel: 'Semua verifikasi',
        options: props.verificationOptions,
    },
])
</script>

<template>
    <Head title="Daftar User" />
    <AppLayout>
        <PageHeader
            title="Daftar User"
            subtitle="Semua user sistem — kelola status, role, dan verifikasi."
        >
            <template #actions>
                <Button v-if="can.manageInvitations" as-child variant="outline">
                    <Link :href="route('admin.invitations.index')">
                        <MailPlus class="size-4" /> Undangan Registrasi
                    </Link>
                </Button>
                <Button v-if="can.verify" as-child>
                    <Link :href="route('admin.verifications.index')">
                        <UserCheck class="size-4" /> Antrian Verifikasi NIDN ({{
                            awaitingVerification
                        }})
                    </Link>
                </Button>
            </template>
        </PageHeader>
        <FilterToolbar
            v-model:search="filters.search"
            :selects="selects"
            :values="filters"
            search-placeholder="Cari nama / email / NIDN..."
            search-label="Cari nama, email, atau NIDN"
            @select="(key, value) => (filters[key] = value)"
        />
        <UserTable :rows="users.data" :filtered="filtered" />
        <PaginationBar :page="users" />
        <div class="mt-4">
            <AppBanner tone="info">
                User yang masih menjadi ketua pengajuan aktif tidak dapat dinonaktifkan sebelum
                kepemilikannya dialihkan.
            </AppBanner>
        </div>
    </AppLayout>
</template>
