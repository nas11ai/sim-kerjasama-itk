<script setup lang="ts">
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
        `NIDN: ${props.card.nidn ?? '-'}`,
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
