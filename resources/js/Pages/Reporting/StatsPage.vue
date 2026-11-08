<script setup lang="ts">
// Frame: OPERATOR-07-01
import { Head } from '@inertiajs/vue3'

import EmptyState from '@/Components/app/EmptyState.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
</script>

<template>
    <Head title="Dashboard Statistik" />
    <AppLayout>
        <PageHeader
            title="Dashboard Statistik"
            subtitle="Statistik ditampilkan langsung dari data terkini dan bersifat hanya-baca."
        />
        <EmptyState title="Tidak ada data" />
    </AppLayout>
</template>
