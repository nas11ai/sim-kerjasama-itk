<script setup lang="ts">
import AppBanner from '@/Components/app/AppBanner.vue'
import { Checkbox } from '@/Components/ui/checkbox'
import { Label } from '@/Components/ui/label'

// BR-FE-13 / FE-CHANGE-04 — hanya muncul bila ada pemilik draft berjalan (W85); BR-FE-34 — di
// dialog Terbitkan, saat perubahan field benar-benar berlaku.
defineProps<{ count: number }>()
const notify = defineModel<boolean>({ required: true })
</script>

<template>
    <div v-if="count > 0" class="space-y-3">
        <AppBanner tone="warning">
            {{ count }} peneliti punya draft berjalan pada Form ini. Centang untuk mengirimkan
            pemberitahuan.
        </AppBanner>
        <div class="flex items-center gap-2">
            <Checkbox
                id="notify-affected"
                :model-value="notify"
                @update:model-value="(checked) => (notify = checked === true)"
            />
            <Label for="notify-affected" class="font-normal"
                >Kirim notifikasi ke {{ count }} peneliti terdampak</Label
            >
        </div>
    </div>
</template>
