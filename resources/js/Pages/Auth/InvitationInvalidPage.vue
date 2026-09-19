<script setup lang="ts">
/**
 * BR-IAM-06 — SHARED-01-04, tautan undangan yang tidak berlaku.
 *
 * Sebabnya sengaja tidak dirinci. Keempat sebab — kuota habis, kedaluwarsa,
 * dicabut, organisasinya nonaktif — hanya berguna bagi Operator, sementara
 * bagi penebak token ia memberi tahu seberapa dekat tebakannya. Yang perlu
 * diketahui pendaftar adalah kepada siapa ia bertanya.
 */
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { AlertTriangle } from 'lucide-vue-next'
</script>

<template>
    <Head title="Undangan Tidak Berlaku" />

    <div class="flex min-h-screen items-center justify-center p-4">
        <Card class="w-full max-w-md">
            <CardHeader class="space-y-2">
                <AlertTriangle class="text-destructive size-8" />
                <CardTitle>Undangan tidak berlaku</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p class="text-muted-foreground text-sm">
                    Tautan undangan ini sudah tidak dapat dipakai untuk mendaftar. Hubungi
                    penghubung Anda di LPPM untuk meminta undangan baru.
                </p>
                <Button :as="Link" :href="route('login')" variant="outline" class="w-full">
                    Kembali ke Halaman Masuk
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
