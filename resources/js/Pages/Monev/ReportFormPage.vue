<script setup lang="ts">
// Frame: RESEARCHER-05-02
import { Head, Link } from '@inertiajs/vue3'
import { Check } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import WizardFieldList from '@/Components/Submission/Wizard/WizardFieldList.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { useMonevReport } from '@/composables/useMonevReport'
import AppLayout from '@/Layouts/AppLayout.vue'
import { formatDate, formatDateTime } from '@/lib/format'
import type { MonevReportFormPageProps } from '@/types/monev'

const props = defineProps<MonevReportFormPageProps>()

const { values, errors, generalErrors, processing, update, save, submit } = useMonevReport(
    props.report,
    props.fields
)

const subtitle = computed(() =>
    [props.report.title, props.report.scheme].filter((part) => part !== null).join(' · ')
)
// BR-FE-16 — peneliti melihat perpanjangan dan batasnya, tanpa alasan override.
const override = computed(() =>
    props.phaseAccess.override?.status === 'active' ? props.phaseAccess.override : null
)

const confirmOpen = ref(false)
const confirmSubmit = () => submit(() => (confirmOpen.value = false))
</script>

<template>
    <Head :title="`Isi Laporan ${report.cycleName}`" />
    <AppLayout>
        <PageHeader
            :title="`Isi Laporan ${report.cycleName}`"
            :subtitle="subtitle"
            :back-href="route('monev.index')"
            back-label="Daftar Monev"
        >
            <template #badge>
                <Badge variant="outline">Deadline {{ formatDate(report.deadline) }}</Badge>
            </template>
        </PageHeader>
        <div class="mx-auto flex max-w-3xl flex-col gap-4">
            <AppBanner tone="info">
                Setiap siklus monev hanya menerima satu laporan. Setelah batas waktu terlewat, form
                ditutup — hubungi Operator LPPM bila membutuhkan perpanjangan.
            </AppBanner>
            <AppBanner v-if="override" tone="warning">
                Anda mendapatkan perpanjangan akses hingga
                {{ override.expires_at ? formatDateTime(override.expires_at) : '—' }}. Akses
                diberikan oleh operator.
            </AppBanner>
            <!-- BR-MON-02/05/08 — blok penuh: tanpa form selama siklusnya tidak terbuka -->
            <AppBanner v-if="!can.save" tone="lock">
                {{ cycle.blockedReason ?? 'Laporan ini tidak dapat disunting.' }}
            </AppBanner>
            <template v-else>
                <AppBanner v-for="message in generalErrors" :key="message" tone="denial">
                    {{ message }}
                </AppBanner>
                <SectionCard title="Laporan Kemajuan">
                    <WizardFieldList
                        :fields="fields"
                        :values="values"
                        :errors="errors"
                        @update="update"
                    />
                </SectionCard>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <Button variant="ghost" as-child>
                        <Link :href="route('monev.index')">Batalkan</Link>
                    </Button>
                    <div class="flex gap-2">
                        <Button variant="outline" :disabled="processing" @click="save">
                            Simpan Draft
                        </Button>
                        <Button :disabled="processing || !can.submit" @click="confirmOpen = true">
                            <Check class="size-4" /> Submit Laporan
                        </Button>
                    </div>
                </div>
            </template>
        </div>
        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="`Submit laporan ${report.cycleName}?`"
            description="Laporan yang sudah disubmit tidak bisa disunting lagi."
            confirm-label="Submit Laporan"
            :processing="processing"
            @confirm="confirmSubmit"
        />
    </AppLayout>
</template>
