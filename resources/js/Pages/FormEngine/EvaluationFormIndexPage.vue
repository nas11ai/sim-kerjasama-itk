<script setup lang="ts">
// Frame: ADMIN-08-09
import { Head, Link, router } from '@inertiajs/vue3'
import { ClipboardCheck, Pencil, Plus, Trash2 } from 'lucide-vue-next'
import { ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import DeleteBlockedDialog from '@/Components/FormEngine/DeleteBlockedDialog.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import AppLayout from '@/Layouts/AppLayout.vue'
import { draftBannerText } from '@/lib/evaluationDefinition'
import type { EvaluationFormCardData, EvaluationFormIndexPageProps } from '@/types/form-engine'

// R3 — pustaka Formulir Evaluasi: lembar penilaian berversi yang dipasang di banyak tahap (BR-FE-30).
defineProps<EvaluationFormIndexPageProps>()

const target = ref<EvaluationFormCardData | null>(null)
const deleting = ref(false)
const blocked = ref(false)
const processing = ref(false)

// BR-FE-22 — yang masih terpasang atau pernah ditugaskan tidak dihapus; kelayakannya dari backend.
const requestDelete = (form: EvaluationFormCardData) => {
    target.value = form
    if (form.stepCount > 0 || form.assignmentCount > 0) blocked.value = true
    else deleting.value = true
}

const confirmDelete = () => {
    if (target.value === null) return
    router.delete(route('admin.evaluation-forms.destroy', target.value.id), {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onSuccess: () => (deleting.value = false),
        onFinish: () => (processing.value = false),
    })
}

const usage = (form: EvaluationFormCardData) =>
    form.stepCount === 0
        ? 'Belum dipasang di tahap mana pun'
        : `Dipasang di ${form.stepCount} langkah · ${form.assignmentCount} penugasan penilai`
</script>

<template>
    <Head title="Formulir Evaluasi" />
    <AppLayout>
        <PageHeader
            title="Formulir Evaluasi"
            subtitle="Lembar penilaian dari penilai yang bisa dipasang di banyak tahap."
        >
            <template #actions>
                <Button as-child>
                    <Link :href="route('admin.evaluation-forms.create')"
                        ><Plus class="size-4" />Buat Formulir Evaluasi</Link
                    >
                </Button>
            </template>
        </PageHeader>
        <div class="max-w-3xl">
            <EmptyState
                v-if="forms.length === 0"
                :icon="ClipboardCheck"
                title="Belum ada Formulir Evaluasi."
                description="Buat lembar penilaian, terbitkan, lalu pasang di langkah tahap yang dinilai."
            />
            <div v-else class="space-y-3">
                <article
                    v-for="form in forms"
                    :key="form.id"
                    class="flex flex-wrap items-start justify-between gap-4 rounded-lg border p-4"
                >
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-medium">{{ form.title }}</h3>
                            <Badge variant="outline">{{
                                form.latestVersionLabel ?? 'Belum terbit'
                            }}</Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">{{ usage(form) }}</p>
                        <!-- BR-FE-31 — draf yang lupa diterbitkan terlihat dan tidak merusak (§4.5) -->
                        <AppBanner v-if="form.hasDraft" tone="warning">
                            {{ draftBannerText(form.latestVersionLabel) }}
                        </AppBanner>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        <Button as-child size="sm" variant="outline">
                            <Link :href="route('admin.evaluation-forms.edit', form.id)"
                                ><Pencil class="size-4" />Ubah</Link
                            >
                        </Button>
                        <Button size="sm" variant="ghost" @click="requestDelete(form)"
                            ><Trash2 class="size-4" />Hapus</Button
                        >
                    </div>
                </article>
            </div>
        </div>
        <ConfirmDialog
            v-model:open="deleting"
            title="Hapus formulir evaluasi?"
            :description="`${target?.title ?? ''} beserta semua versinya dihapus permanen.`"
            confirm-label="Hapus"
            destructive
            :processing="processing"
            @confirm="confirmDelete"
        />
        <DeleteBlockedDialog
            v-model:open="blocked"
            title="Formulir evaluasi tidak bisa dihapus"
            deactivate-label="Nonaktifkan"
            :can-deactivate="false"
        >
            <!-- BR-FE-22 -->
            {{ target?.title }} masih dipasang di {{ target?.stepCount }} langkah dan pernah
            ditugaskan {{ target?.assignmentCount }} kali. Formulir evaluasi adalah dasar keputusan
            penilaian; lepas atau nonaktifkan pemasangannya dari editor tahap.
        </DeleteBlockedDialog>
    </AppLayout>
</template>
