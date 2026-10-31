<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ClipboardList, MessagesSquare } from 'lucide-vue-next'
import { computed } from 'vue'

import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent } from '@/Components/ui/card'
import { formatDate } from '@/lib/format'
import type { AssignmentCard } from '@/types/review'

const props = defineProps<{ card: AssignmentCard }>()

const subtitle = computed(() =>
    [props.card.leadName, props.card.scheme].filter(Boolean).join(' · ')
)

const action = computed(() => {
    const id = String(props.card.submissionId)

    if (props.card.state === 'evaluation_pending') {
        return { label: 'Mulai Evaluasi', href: route('reviewer.assignments.evaluation', id) }
    }

    const label = props.card.state === 'discussion_open' ? 'Buka Diskusi' : 'Lihat Summary'
    return { label, href: route('reviewer.assignments.summary', id) }
})
</script>

<template>
    <Card>
        <CardContent class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="font-semibold">{{ card.title }}</p>
                <p class="text-sm text-muted-foreground">{{ subtitle }}</p>
                <p v-if="card.dueDate" class="mt-0.5 text-xs text-muted-foreground">
                    Deadline evaluasi: {{ formatDate(card.dueDate) }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Badge :variant="card.state === 'done' ? 'secondary' : 'outline'">
                    {{ card.stateLabel }}
                </Badge>
                <Button
                    as-child
                    size="sm"
                    :variant="card.state === 'evaluation_pending' ? 'default' : 'outline'"
                >
                    <Link :href="action.href">
                        <ClipboardList v-if="card.state === 'evaluation_pending'" class="size-4" />
                        <MessagesSquare v-else class="size-4" />
                        {{ action.label }}
                    </Link>
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
