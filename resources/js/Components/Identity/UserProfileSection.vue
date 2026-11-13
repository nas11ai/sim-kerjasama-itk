<script setup lang="ts">
import { computed } from 'vue'

import InfoGrid from '@/Components/app/InfoGrid.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import type { UserDetailData } from '@/types/identity'

const props = defineProps<{ user: UserDetailData }>()

const items = computed(() => [
    { label: 'Nama', value: props.user.name },
    { label: 'Email', value: props.user.email },
    { label: 'NIDN', value: props.user.nidn ?? '-' },
    { label: 'Telepon', value: props.user.phone ?? '-' },
    { label: 'Organisasi', value: props.user.organization ?? '-' },
    { label: 'Bidang Keahlian', value: props.user.expertise ?? '-' },
])
</script>

<template>
    <SectionCard title="Data Personal"><InfoGrid :items="items" /></SectionCard>
</template>
