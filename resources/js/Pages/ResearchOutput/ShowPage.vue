<script setup lang="ts">
// Frame: RESEARCHER-06-03
import { Head, Link } from '@inertiajs/vue3'
import { SquarePen } from 'lucide-vue-next'

import AppBanner from '@/Components/app/AppBanner.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import OutputFileList from '@/Components/ResearchOutput/OutputFileList.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { ResearchOutputShowPageProps } from '@/types/research-output'

defineProps<ResearchOutputShowPageProps>()
</script>

<template>
    <Head title="Detail Luaran" />
    <AppLayout>
        <PageHeader
            title="Detail Luaran"
            :subtitle="output.submissionTitle"
            :back-href="route('research-outputs.index')"
            back-label="Luaran Penelitian"
        >
            <template #badge>
                <Badge variant="outline">{{ output.typeName }}</Badge>
            </template>
            <!-- W146 — tombol mengikuti definisi yang sama dengan 403 di server. -->
            <template v-if="output.access.canWrite" #actions>
                <Button variant="outline" as-child>
                    <Link :href="route('research-outputs.edit', output.id)">
                        <SquarePen class="size-4" /> Edit
                    </Link>
                </Button>
            </template>
        </PageHeader>
        <div class="mx-auto flex max-w-2xl flex-col gap-4">
            <AppBanner v-if="output.access.blockedReason" tone="lock">
                {{ output.access.blockedReason }}
            </AppBanner>
            <SectionCard :title="output.headline">
                <InfoGrid :items="output.fields" />
            </SectionCard>
            <OutputFileList :files="output.files" />
            <!-- BR-RO-06/07 -->
            <AppBanner tone="lock">
                Di dalam tim penelitian, hanya ketua dan co-investigator yang dapat mengubah luaran
                ini — anggota biasa dan reviewer membaca saja. Operator LPPM tetap dapat mengoreksi
                datanya. Luaran dari pengajuan yang sudah ditarik tetap tersimpan sebagai arsip
                baca-saja.
            </AppBanner>
        </div>
    </AppLayout>
</template>
