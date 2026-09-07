<script setup lang="ts">
/**
 * BR-FE-04 — laporan turunan (monev, kelengkapan berkas) hanya lahir dari
 * pengajuan yang sudah disetujui.
 *
 * Daftar fasenya sudah disaring backend: yang muncul hanya fase yang belum
 * punya laporan. Menawarkan fase yang sudah terisi akan membuat peneliti
 * menekan tombol lalu dikembalikan ke laporan yang sama tanpa penjelasan.
 */
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { FilePlus2, Loader2 } from 'lucide-vue-next'

interface ChildPhase {
    id: number
    title: string
}

interface Props {
    submissionId: number
    phases: ChildPhase[]
}

const props = defineProps<Props>()

const pendingId = ref<number | null>(null)

function create(phase: ChildPhase) {
    pendingId.value = phase.id

    router.post(
        route('user.submissions.children.store', props.submissionId),
        { form_phase_detail_id: phase.id },
        {
            preserveScroll: true,
            onFinish: () => {
                pendingId.value = null
            },
        }
    )
}
</script>

<template>
    <Card v-if="phases.length">
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-base">
                <FilePlus2 class="h-4 w-4" />
                Laporan Lanjutan
            </CardTitle>
        </CardHeader>

        <CardContent class="space-y-3">
            <p class="text-sm text-muted-foreground">
                Pengajuan ini sudah disetujui, jadi laporan lanjutannya dapat dimulai. Setiap fase
                hanya punya satu laporan.
            </p>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="phase in phases"
                    :key="phase.id"
                    variant="outline"
                    :disabled="pendingId !== null"
                    @click="create(phase)"
                >
                    <Loader2 v-if="pendingId === phase.id" class="mr-2 h-4 w-4 animate-spin" />
                    <FilePlus2 v-else class="mr-2 h-4 w-4" />
                    {{ phase.title }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
