<script setup lang="ts">
import { Badge } from '@/Components/ui/badge'
import type { SubmissionStatusView } from '@/types/submission-status'

defineProps<{ status: SubmissionStatusView }>()
</script>

<template>
    <Badge :variant="status.variant">{{ status.label }}</Badge>
</template>
