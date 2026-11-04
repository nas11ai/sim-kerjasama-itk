<script setup lang="ts">
import { Plus } from 'lucide-vue-next'
import { ref } from 'vue'

import EvaluationFormCard from '@/Components/FormEngine/EvaluationFormCard.vue'
import { Button } from '@/Components/ui/button'
import { evaluationCardKey } from '@/lib/evaluationDefinition'
import type { EvaluationSectionData, FieldTypeOption } from '@/types/form-engine'

// Ubiquitous language — form evaluasi attached ke detail ber-needs_review; satu bagian per detail.
defineProps<{ section: EvaluationSectionData; fieldTypeOptions: FieldTypeOption[] }>()

// Kartu baru belum punya id: key lokal, dibuang setelah tersimpan (kartunya kini ada di `forms`).
let counter = 0
const drafts = ref<string[]>([])
const add = () => {
    counter += 1
    drafts.value = [...drafts.value, `draft-${counter}`]
}
const discard = (key: string) => (drafts.value = drafts.value.filter((draft) => draft !== key))
</script>

<template>
    <section class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-semibold">Form evaluasi untuk {{ section.detailLabel }}</h2>
            <Button type="button" size="sm" variant="outline" @click="add"
                ><Plus class="size-4" />Tambah Form Evaluasi</Button
            >
        </div>
        <EvaluationFormCard
            v-for="form in section.forms"
            :key="evaluationCardKey(form)"
            :detail-id="section.detailId"
            :form="form"
            :field-type-options="fieldTypeOptions"
        />
        <EvaluationFormCard
            v-for="key in drafts"
            :key="key"
            :detail-id="section.detailId"
            :form="null"
            :field-type-options="fieldTypeOptions"
            @created="discard(key)"
            @discard="discard(key)"
        />
        <p
            v-if="section.forms.length === 0 && drafts.length === 0"
            class="text-sm text-muted-foreground"
        >
            Belum ada form evaluasi.
        </p>
        <p class="text-xs text-muted-foreground">
            Form evaluasi hanya tersedia pada tahapan reviewer yang ditandai "Perlu Review". Urutan
            field dapat diubah dengan menyeret.
        </p>
    </section>
</template>
