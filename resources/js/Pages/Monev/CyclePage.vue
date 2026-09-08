<script setup lang="ts">
/**
 * BR-MON-02, BR-MON-04, BR-MON-07, BR-MON-10 — pengelolaan siklus monev.
 *
 * Membuka siklus adalah aksi tersendiri, bukan efek samping dari tanggal yang
 * tiba: jadwal monev berjarak bulanan, dan pemberitahuan ke peneliti serta
 * reviewer menempel pada pembukaan itu.
 *
 * Tombol buka hanya aktif untuk siklus yang tahap sebelumnya sudah tuntas —
 * `can_proceed` dihitung backend sebagai turunan keadaan (BR-MON-02), bukan
 * kolom yang bisa disetel dari sini.
 */
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Alert, AlertDescription } from '@/Components/ui/alert'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { BellRing, CircleCheck, Info, Loader2, Lock, PlayCircle } from 'lucide-vue-next'

interface Cycle {
    id: number
    title: string
    order: number
    needs_review: boolean
    report_submitted: boolean
    can_proceed: boolean
}

interface SuggestedReviewer {
    id: number
    name: string | null
    reviewer_type: string
}

interface Props {
    submission: { id: number; status: unknown }
    cycles: Cycle[]
    /** BR-MON-07 — saran, bukan batas. Operator tetap bebas memilih yang lain. */
    suggestedReviewers: SuggestedReviewer[]
}

const props = defineProps<Props>()

const pendingId = ref<number | null>(null)

/**
 * Siklus terbuka kalau seluruh siklus sebelumnya sudah `can_proceed`.
 * Dihitung ulang di sini hanya untuk menonaktifkan tombol lebih awal —
 * penolakan sesungguhnya tetap dari backend, supaya keduanya tidak bisa
 * menyimpang tanpa ketahuan.
 */
const openableUpTo = computed(() => {
    const ordered = [...props.cycles].sort((a, b) => a.order - b.order)
    const firstBlocked = ordered.findIndex((c) => !c.can_proceed)

    return firstBlocked === -1 ? ordered.length : firstBlocked
})

const isOpenable = (cycle: Cycle) => {
    const ordered = [...props.cycles].sort((a, b) => a.order - b.order)

    return ordered.indexOf(cycle) <= openableUpTo.value
}

function open(cycle: Cycle) {
    pendingId.value = cycle.id

    router.post(
        route('admin.submissions.monev.open', props.submission.id),
        { form_phase_detail_id: cycle.id },
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
    <Head title="Siklus Monev" />

    <AuthenticatedLayout>
        <div class="space-y-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold">Siklus Monev</h1>
                <p class="text-sm text-muted-foreground">
                    Pengajuan #{{ submission.id }} — aktivasi siklus dan penugasan reviewernya.
                </p>
            </div>

            <Alert>
                <BellRing class="h-4 w-4" />
                <AlertDescription>
                    Mengaktifkan siklus memberi tahu peneliti dan reviewer yang ditugaskan pada
                    siklus itu. Selama siklus belum diaktifkan, tidak ada pemberitahuan yang
                    terkirim — jadwal monev berjarak bulanan, dan tenggatnya bisa terlanjur berjalan
                    tanpa ada yang tahu.
                </AlertDescription>
            </Alert>

            <Card v-for="cycle in cycles" :key="cycle.id">
                <CardHeader>
                    <CardTitle class="flex flex-wrap items-center justify-between gap-2 text-base">
                        <span class="flex items-center gap-2">
                            {{ cycle.title }}
                            <Badge v-if="cycle.needs_review" variant="outline"
                                >Butuh evaluasi</Badge
                            >
                        </span>
                        <Badge :variant="cycle.can_proceed ? 'secondary' : 'outline'">
                            {{ cycle.can_proceed ? 'Tuntas' : 'Berjalan' }}
                        </Badge>
                    </CardTitle>
                </CardHeader>

                <CardContent class="space-y-3">
                    <div class="grid gap-2 text-sm sm:grid-cols-3">
                        <div>
                            <span class="text-muted-foreground">Laporan peneliti</span>
                            <div>
                                {{ cycle.report_submitted ? 'Sudah disubmit' : 'Belum masuk' }}
                            </div>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Evaluasi</span>
                            <div>{{ cycle.needs_review ? 'Diperlukan' : 'Tidak diperlukan' }}</div>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Siklus berikutnya</span>
                            <div>{{ cycle.can_proceed ? 'Terbuka' : 'Menunggu siklus ini' }}</div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="!isOpenable(cycle) || pendingId !== null"
                            @click="open(cycle)"
                        >
                            <Loader2
                                v-if="pendingId === cycle.id"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            <PlayCircle v-else class="mr-2 h-4 w-4" />
                            Buka Siklus
                        </Button>
                        <span
                            v-if="!isOpenable(cycle)"
                            class="flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Lock class="h-3 w-3" />
                            Tahapannya berurutan — selesaikan siklus sebelumnya lebih dulu.
                        </span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <CircleCheck class="h-4 w-4" />
                        Saran Reviewer
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p class="text-sm text-muted-foreground">
                        Reviewer pengajuan awal yang masa tugasnya masih berjalan. Yang sudah habis
                        tidak muncul di sini — menyarankannya berarti mengarahkan ke penugasan yang
                        pasti ditolak.
                    </p>

                    <div v-if="suggestedReviewers.length" class="flex flex-wrap gap-2">
                        <Badge
                            v-for="reviewer in suggestedReviewers"
                            :key="reviewer.id"
                            variant="outline"
                        >
                            {{ reviewer.name }} —
                            {{ reviewer.reviewer_type === 'internal' ? 'Internal' : 'Eksternal' }}
                        </Badge>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        Tidak ada reviewer pengajuan awal yang masih aktif.
                    </p>

                    <Alert>
                        <Info class="h-4 w-4" />
                        <AlertDescription>
                            Ini saran, bukan batas. Anda tetap dapat menugaskan reviewer di luar
                            daftar ini — termasuk memperpanjang masa tugas reviewer lama lebih dulu.
                        </AlertDescription>
                    </Alert>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
