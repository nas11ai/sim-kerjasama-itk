<script setup lang="ts">
import { Copy } from 'lucide-vue-next'

import AppBanner from '@/Components/app/AppBanner.vue'
import { Button } from '@/Components/ui/button'
import { toast } from '@/Components/ui/toast'

// W51 — token hanya terbaca sekali, saat terbit; daftar undangan tidak membawanya.
const props = defineProps<{ url: string }>()

const copy = async () => {
    await navigator.clipboard.writeText(props.url)
    toast({ title: 'Tautan undangan disalin.' })
}
</script>

<template>
    <AppBanner tone="success" title="Undangan diterbitkan">
        <div class="flex flex-col gap-2">
            <span
                >Salin tautan ini sekarang, tautan tidak ditampilkan lagi setelah halaman ini
                ditinggalkan.</span
            >
            <div class="flex flex-wrap items-center gap-2">
                <code class="rounded bg-muted px-2 py-1 text-xs break-all">{{ url }}</code>
                <Button size="sm" variant="outline" type="button" @click="copy"
                    ><Copy class="size-4" />Salin Tautan</Button
                >
            </div>
        </div>
    </AppBanner>
</template>
