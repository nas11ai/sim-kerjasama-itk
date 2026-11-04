<script setup lang="ts">
import { computed } from 'vue'

import OptionSelect from '@/Components/app/OptionSelect.vue'

// Isian Status Aktif/Nonaktif untuk kolom `is_active` boolean.
defineProps<{ id: string }>()
const active = defineModel<boolean>({ required: true })

const ACTIVE = 'aktif'
const states = [
    { value: ACTIVE, label: 'Aktif' },
    { value: 'nonaktif', label: 'Nonaktif' },
]
const state = computed({
    get: () => (active.value ? ACTIVE : 'nonaktif'),
    set: (value: string | null) => (active.value = value === ACTIVE),
})
</script>

<template>
    <OptionSelect :id="id" v-model="state" :options="states" />
</template>
