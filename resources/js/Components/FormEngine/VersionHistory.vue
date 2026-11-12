<script setup lang="ts">
import { formatDate } from '@/lib/format'

// Riwayat versi terbit, terbaru dulu. `cohortCount` hanya untuk versi struktur tahap (BR-FE-32).
defineProps<{
    versions: {
        label: string
        publishedAt: string
        publishedBy: string | null
        cohortCount?: number
    }[]
}>()
</script>

<template>
    <section class="space-y-2">
        <h3 class="text-sm font-semibold">Riwayat versi</h3>
        <ul v-if="versions.length > 0" class="divide-y rounded-lg border">
            <li
                v-for="version in versions"
                :key="version.label"
                class="flex flex-wrap justify-between gap-2 p-3 text-sm"
            >
                <span class="font-medium">{{ version.label }}</span>
                <span class="text-muted-foreground">
                    Terbit {{ formatDate(version.publishedAt) }}
                    <template v-if="version.publishedBy"> oleh {{ version.publishedBy }}</template>
                    <template v-if="version.cohortCount !== undefined">
                        ·
                        {{
                            version.cohortCount > 0
                                ? `dipakai ${version.cohortCount} angkatan`
                                : 'belum dipakai angkatan'
                        }}
                    </template>
                </span>
            </li>
        </ul>
        <p v-else class="text-sm text-muted-foreground">Belum pernah diterbitkan.</p>
    </section>
</template>
