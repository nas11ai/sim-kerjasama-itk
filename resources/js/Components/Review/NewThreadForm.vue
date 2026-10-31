<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import { Textarea } from '@/Components/ui/textarea'

// `canCreate` dari payload (BR-FE-06, satu definisi dengan ReviewSummaryGuard).
const props = defineProps<{ submissionId: number; canCreate: boolean }>()

const form = useForm<{ summary_notes: string; attachments: File[] }>({
    summary_notes: '',
    attachments: [],
})

// Lampiran (`attachments.N`) dan penolakan guard (`review_summary`) tak punya kolom sendiri.
const otherErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => key !== 'summary_notes')
        .map(([, message]) => message)
)

const pickFiles = (event: Event) => {
    const input = event.target
    form.attachments = input instanceof HTMLInputElement ? Array.from(input.files ?? []) : []
}

const open = () =>
    form.post(route('reviewer.review-threads.store', String(props.submissionId)), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    })
</script>

<template>
    <SectionCard title="Buat Ringkasan Review">
        <template v-if="!canCreate">
            <!-- BR-FE-06 — gerbangnya evaluation_status penugasan, bukan status pengajuan -->
            <AppBanner tone="lock">
                Selesaikan evaluasi Anda sebelum membuat catatan ringkasan.
            </AppBanner>
            <Button variant="outline" class="self-start" disabled>
                <Plus class="size-4" /> Buat Ringkasan
            </Button>
        </template>
        <form v-else class="flex flex-col gap-3" @submit.prevent="open">
            <FormField
                id="summary-notes"
                label="Catatan ringkasan"
                required
                :error="form.errors.summary_notes"
            >
                <template #default="{ describedBy }">
                    <Textarea
                        id="summary-notes"
                        v-model="form.summary_notes"
                        rows="4"
                        :aria-describedby="describedBy"
                    />
                </template>
            </FormField>
            <FormField id="summary-attachments" label="Lampiran" hint="Maks. 5 berkas, 10 MB">
                <input
                    id="summary-attachments"
                    type="file"
                    multiple
                    class="text-sm file:mr-3 file:rounded-md file:border file:px-3 file:py-1"
                    @change="pickFiles"
                />
            </FormField>
            <AppBanner v-for="message in otherErrors" :key="message" tone="denial">
                {{ message }}
            </AppBanner>
            <Button
                type="submit"
                class="self-end"
                :disabled="form.processing || form.summary_notes.trim() === ''"
            >
                <Plus class="size-4" /> Buat Ringkasan
            </Button>
        </form>
    </SectionCard>
</template>
