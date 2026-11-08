<script setup lang="ts">
// W151 — setiap grafik bisa dibaca sebagai tabel (aksesibilitas, slot warna kontras rendah).
import { Button } from '@/Components/ui/button'

const showTable = defineModel<boolean>({ required: true })
</script>

<template>
    <Button variant="ghost" size="sm" :aria-pressed="showTable" @click="showTable = !showTable">
        {{ showTable ? 'Lihat grafik' : 'Lihat tabel' }}
    </Button>
</template>
