<script setup lang="ts">
import { FileText } from 'lucide-vue-next'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import { Badge } from '@/Components/ui/badge'
import { Card, CardContent } from '@/Components/ui/card'
import { formatDate } from '@/lib/format'
import type { VerificationCardData } from '@/types/identity'

// BR-IAM-13 — kartu ditolak tetap bisa diverifikasi; tombolnya diisi halaman lewat slot `actions`.
const props = defineProps<{ card: VerificationCardData }>()

const rejected = computed(() => props.card.rejectedAt !== null)

const meta = computed(() =>
    [
        `NIDN: ${props.card.nidn ?? '—'}`,
        props.card.organization,
        rejected.value
            ? `ditolak ${formatDate(props.card.rejectedAt)}`
            : `daftar ${formatDate(props.card.registeredAt)}`,
    ]
        .filter((part) => part !== null)
        .join(' · ')
)
</script>

<template>
    <Card>
        <CardContent class="flex flex-col gap-3 pt-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <strong>{{ card.name }}</strong>
                        <Badge v-if="rejected" variant="destructive">Ditolak</Badge>
                    </div>
                    <div class="text-sm text-muted-foreground">{{ card.email }}</div>
                    <div class="mt-0.5 text-xs text-muted-foreground">{{ meta }}</div>
                    <ul v-if="card.files.length > 0" class="mt-2 flex flex-wrap gap-3 text-sm">
                        <li v-for="file in card.files" :key="file.url">
                            <a
                                :href="file.url"
                                class="inline-flex items-center gap-1 text-primary hover:underline"
                                ><FileText class="size-4" />{{ file.label }}</a
                            >
                        </li>
                    </ul>
                </div>
                <div class="flex items-center gap-2">
                    <slot name="actions" />
                </div>
            </div>
            <AppBanner v-if="card.rejectionReason" tone="info">
                Alasan penolakan sebelumnya: {{ card.rejectionReason }}
            </AppBanner>
        </CardContent>
    </Card>
</template>
