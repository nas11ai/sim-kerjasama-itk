<script setup lang="ts">
// Frame: OPERATOR-07-01 — tab Tindak Lanjut (W152): apa tindakan berikutnya, terlama dulu.
import FollowUpListCard from '@/Components/Reporting/FollowUpListCard.vue'
import type { FollowUpList } from '@/types/reporting'

defineProps<{ lists: FollowUpList[] }>()
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-3">
        <FollowUpListCard v-for="list in lists" :key="list.key" :list="list" />
    </div>
</template>
