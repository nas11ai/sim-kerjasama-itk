<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { Check } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import ReviewAttachmentLinks from '@/Components/Submission/Revision/ReviewAttachmentLinks.vue'
import ReviewCommentItem from '@/Components/Submission/Revision/ReviewCommentItem.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Textarea } from '@/Components/ui/textarea'
import { formatDate } from '@/lib/format'
import type { ReviewThread } from '@/types/submission-revision'

// `canReply` dari Policy discussReview (ADR 0015); `canResolve` hanya diberikan layar reviewer.
const props = withDefaults(
    defineProps<{ thread: ReviewThread; canReply: boolean; canResolve?: boolean }>(),
    { canResolve: false }
)

const form = useForm({ comment_text: '' })
const replyId = `reply-${props.thread.id}`

const reply = () =>
    form.post(route('review-comments.store', String(props.thread.id)), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    })

// BR-REV-15 — pengganti perlu tahu kenapa ia berhak me-resolve ulir orang lain.
const title = computed(() =>
    props.thread.inheritedBy
        ? `${props.thread.reviewer} · diwarisi ${props.thread.inheritedBy}`
        : props.thread.reviewer
)

const confirming = ref(false)
const resolving = ref(false)

const resolve = () =>
    router.post(
        route('review-summaries.resolve', String(props.thread.id)),
        {},
        {
            preserveScroll: true,
            onStart: () => (resolving.value = true),
            onFinish: () => {
                resolving.value = false
                confirming.value = false
            },
        }
    )
</script>

<template>
    <SectionCard :title="title" :description="formatDate(thread.at)">
        <template #actions>
            <Button
                v-if="canResolve && thread.isOpen"
                data-test="resolve-thread"
                variant="outline"
                size="sm"
                @click="confirming = true"
            >
                <Check class="size-4" /> Tandai Selesai
            </Button>
            <Badge :variant="thread.isOpen ? 'default' : 'secondary'">
                {{ thread.isOpen ? 'Terbuka' : 'Selesai' }}
            </Badge>
        </template>
        <p v-if="thread.notes" class="text-sm whitespace-pre-line">{{ thread.notes }}</p>
        <ReviewAttachmentLinks :attachments="thread.attachments" />

        <ul v-if="thread.comments.length > 0" class="flex flex-col gap-4 border-t pt-4">
            <ReviewCommentItem v-for="item in thread.comments" :key="item.id" :comment="item" />
        </ul>

        <!-- BR-REV-06 — ulir ditutup reviewer yang membukanya, bukan peneliti -->
        <AppBanner v-if="thread.isOpen && canReply && !canResolve" tone="lock">
            Hanya reviewer yang membuka catatan ini yang dapat menandainya selesai. Balas untuk
            menyampaikan perbaikan Anda.
        </AppBanner>

        <form v-if="canReply" class="flex flex-col gap-2" @submit.prevent="reply">
            <label :for="replyId" class="sr-only">Balas catatan {{ thread.reviewer }}</label>
            <Textarea
                :id="replyId"
                v-model="form.comment_text"
                rows="3"
                placeholder="Tulis balasan…"
                :aria-invalid="form.errors.comment_text ? true : undefined"
            />
            <p v-if="form.errors.comment_text" class="text-xs text-destructive">
                {{ form.errors.comment_text }}
            </p>
            <Button
                type="submit"
                size="sm"
                class="self-end"
                :disabled="form.processing || form.comment_text.trim() === ''"
            >
                Kirim Balasan
            </Button>
        </form>

        <ConfirmDialog
            v-if="canResolve"
            v-model:open="confirming"
            title="Tandai catatan selesai?"
            description="Bila ini catatan terbuka terakhir dan seluruh evaluasi sudah masuk, pengajuan langsung disetujui."
            confirm-label="Tandai Selesai"
            :processing="resolving"
            @confirm="resolve"
        />
    </SectionCard>
</template>
