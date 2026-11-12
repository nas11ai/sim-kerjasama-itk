<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Save, Send, Undo2 } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import { Button } from '@/Components/ui/button'

// BR-FE-31 / BR-FE-33 — aksi draf editor berversi: Simpan draf (oleh induk), Buang draf, dan
// Terbitkan dengan ringkasan dampak. Terbitkan memakai draf tersimpan, jadi perubahan yang belum
// disimpan harus disimpan dulu. Penolakan server tampil di atas tombolnya.
const props = defineProps<{
    hasDraft: boolean
    dirty: boolean
    saving: boolean
    publishUrl: string
    discardUrl: string
    impactText: string
    nextLabel: string
    discardText: string
}>()
const emit = defineEmits<{ save: [] }>()

const canPublish = computed(() => props.hasDraft && !props.dirty)
const publishing = ref(false)
const discarding = ref(false)
const processing = ref(false)
const refusals = ref<string[]>([])

const visit = {
    preserveScroll: true,
    onStart: () => {
        processing.value = true
        refusals.value = []
    },
    onSuccess: () => {
        publishing.value = false
        discarding.value = false
    },
    onError: (errors: Record<string, string>) => {
        refusals.value = Object.values(errors)
        publishing.value = false
    },
    onFinish: () => (processing.value = false),
}

const publish = () => router.post(props.publishUrl, {}, visit)
const discard = () => router.delete(props.discardUrl, visit)
</script>

<template>
    <div class="space-y-3">
        <AppBanner v-if="refusals.length > 0" tone="denial">
            <p v-for="message in refusals" :key="message">{{ message }}</p>
        </AppBanner>
        <div class="flex flex-wrap items-center justify-end gap-2">
            <p v-if="dirty" class="mr-auto text-xs text-muted-foreground">
                Simpan draf dulu sebelum menerbitkan.
            </p>
            <Button v-if="hasDraft" type="button" variant="ghost" @click="discarding = true"
                ><Undo2 class="size-4" />Buang draf</Button
            >
            <Button type="button" variant="outline" :disabled="saving" @click="emit('save')"
                ><Save class="size-4" />Simpan draf</Button
            >
            <Button type="button" :disabled="!canPublish" @click="publishing = true"
                ><Send class="size-4" />Terbitkan</Button
            >
        </div>
        <ConfirmDialog
            v-model:open="publishing"
            title="Terbitkan draf?"
            :description="impactText"
            :confirm-label="`Terbitkan ${nextLabel}`"
            :processing="processing"
            @confirm="publish"
        />
        <ConfirmDialog
            v-model:open="discarding"
            title="Buang draf?"
            :description="discardText"
            confirm-label="Buang draf"
            destructive
            :processing="processing"
            @confirm="discard"
        />
    </div>
</template>
