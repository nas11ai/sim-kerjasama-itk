<script setup lang="ts">
// W152 — kinerja per skema; semua angka dan teks (persen, rupiah) dari backend (W150).
import { Link } from '@inertiajs/vue3'

import type { SchemePerformance } from '@/types/reporting'

defineProps<{ rows: SchemePerformance[] }>()

const counts = ['submitted', 'approved', 'rejected', 'withdrawn'] as const
</script>

<template>
    <p v-if="rows.length === 0" class="text-sm text-muted-foreground">Tidak ada data</p>
    <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-left text-muted-foreground">
                    <th class="py-1.5 font-medium" scope="col">Skema</th>
                    <th class="py-1.5 text-right font-medium" scope="col">Diajukan</th>
                    <th class="py-1.5 text-right font-medium" scope="col">Disetujui</th>
                    <th class="py-1.5 text-right font-medium" scope="col">Ditolak</th>
                    <th class="py-1.5 text-right font-medium" scope="col">Ditarik</th>
                    <th class="py-1.5 text-right font-medium" scope="col">Tingkat Persetujuan</th>
                    <th class="py-1.5 text-right font-medium" scope="col">Anggaran Disetujui</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.scheme" class="border-b last:border-0">
                    <th class="py-1.5 pr-3 text-left font-normal" scope="row">
                        <Link v-if="row.href" :href="row.href" class="hover:underline">
                            {{ row.scheme }}
                        </Link>
                        <template v-else>{{ row.scheme }}</template>
                    </th>
                    <td v-for="key in counts" :key="key" class="py-1.5 text-right tabular-nums">
                        {{ row[key] }}
                    </td>
                    <td class="py-1.5 text-right tabular-nums">{{ row.approvalRate }}</td>
                    <td class="py-1.5 text-right whitespace-nowrap tabular-nums">
                        {{ row.approvedBudget }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
