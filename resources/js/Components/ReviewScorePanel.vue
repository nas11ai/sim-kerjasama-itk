<script setup lang="ts">
/**
 * BR-REV-11, BR-REV-12 — hasil evaluasi reviewer.
 *
 * Dua sumbu yang sengaja tidak dicampur: reviewer **internal** melihat skor
 * rekannya setelah seluruh evaluasi selesai, sementara **peneliti** melihatnya
 * setelah keputusan akhir. Reviewer eksternal tidak pernah, kapan pun.
 *
 * Penjagaannya ada di payload, bukan di sini: skor yang tidak boleh dilihat
 * tidak dikirim sama sekali. Komponen ini hanya menampilkan apa yang sampai,
 * dan menjelaskan kalau yang sampai memang kosong — ruang kosong tanpa
 * penjelasan membuat orang mengira sistemnya rusak.
 */
import { computed } from 'vue'
import { Alert, AlertDescription } from '@/Components/ui/alert'
import { Badge } from '@/Components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { ClipboardList, Lock } from 'lucide-vue-next'

interface Answer {
    label: string
    value: string | null
}

interface ReviewerScore {
    reviewer: string
    reviewer_type: string
    submitted_at: string | null
    answers: Answer[]
}

interface Props {
    visible: boolean
    /** Alasan skor ditahan — hanya terisi saat `visible` false. */
    reason: string | null
    scores: ReviewerScore[]
}

const props = defineProps<Props>()

const hasScores = computed(() => props.visible && props.scores.length > 0)

const formatted = (value: string | null) =>
    value === null
        ? null
        : new Date(value.replace(' ', 'T')).toLocaleString('id-ID', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-base">
                <component :is="visible ? ClipboardList : Lock" class="h-4 w-4" />
                Hasil Evaluasi Reviewer
            </CardTitle>
        </CardHeader>

        <CardContent class="space-y-4">
            <!-- Ditahan aturan: jelaskan sebabnya, jangan biarkan kosong. -->
            <Alert v-if="!visible">
                <Lock class="h-4 w-4" />
                <AlertDescription>{{ reason }}</AlertDescription>
            </Alert>

            <p v-else-if="!hasScores" class="text-sm text-muted-foreground">
                Belum ada reviewer yang menyelesaikan evaluasinya. Penilaian yang masih berjalan
                belum ditampilkan — ia belum menjadi penilaian.
            </p>

            <div
                v-for="(score, index) in scores"
                :key="index"
                class="space-y-2 rounded-md border p-3"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="flex items-center gap-2 text-sm font-medium">
                        {{ score.reviewer }}
                        <Badge variant="outline">
                            {{ score.reviewer_type === 'internal' ? 'Internal' : 'Eksternal' }}
                        </Badge>
                    </span>
                    <span v-if="score.submitted_at" class="text-xs text-muted-foreground">
                        {{ formatted(score.submitted_at) }}
                    </span>
                </div>

                <dl class="grid gap-2 text-sm sm:grid-cols-2">
                    <div v-for="answer in score.answers" :key="answer.label">
                        <dt class="text-muted-foreground">{{ answer.label }}</dt>
                        <dd>{{ answer.value ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </CardContent>
    </Card>
</template>
